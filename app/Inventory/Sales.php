<?php

namespace App\Inventory;

use App\Accounting\Invoices;
use App\Accounting\Ledger;
use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesEvent;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Sequence;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;

/**
 * The sales flow: quotation → order → delivery → invoice → collection.
 *
 *   Quotation, order   nothing is posted.
 *   Delivery           stock leaves at its average cost; debit Cost of goods sold, credit Inventory.
 *   Invoice            made by the shared invoicing service (App\Accounting\Invoices): debit the
 *                      customer, credit income. There is one invoicing code path for the whole system.
 *   Collection         a Money In linked to the invoice. It settles the receivable; it is not income.
 *   Return             stock comes back at what it cost when delivered; the cost of goods sold is
 *                      reversed, and a credit note is issued for what had been invoiced.
 */
class Sales
{
    /** @param  array{type?: string, customer_account_id: int, warehouse_id?: ?int, date: string, valid_until?: ?string, currency: string, rate?: mixed, notes?: ?string, lines: list<array>}  $data */
    public static function save(Company $company, array $data, ?SalesOrder $order = null): SalesOrder
    {
        return DB::transaction(function () use ($company, $data, $order) {
            if ($order && $order->status !== 'draft') {
                throw new UserError(__('Only a draft can be changed.'));
            }
            $customer = Account::withoutGlobalScope('company')->where('company_id', $company->id)->postable()->where('kind', 'customer')->find($data['customer_account_id']);
            if (! $customer) {
                throw new UserError(__('Choose a customer.'));
            }
            $incomeIds = Account::withoutGlobalScope('company')->where('company_id', $company->id)->postable()->where('class', 'income')->pluck('id')->all();
            $currency = strtoupper($data['currency']);
            $total = '0';
            $rows = [];
            foreach ($data['lines'] as $line) {
                if (! in_array((int) $line['income_account_id'], $incomeIds, true)) {
                    throw new UserError(__('Choose an income account for every line.'));
                }
                $net = Dec::sub(Dec::mul($line['quantity'], $line['unit_price']), $line['discount'] ?? '0');
                if (Dec::isNegative($net)) {
                    throw new UserError(__('A discount cannot be more than the line it is on.'));
                }
                $taxRate = Dec::round($line['tax_rate'] ?? '0', 4);
                $total = Dec::add($total, Dec::add($net, Dec::div(Dec::mul($net, $taxRate, 8), '100')));
                $rows[] = [
                    'product_id' => $line['product_id'] ?? null, 'income_account_id' => (int) $line['income_account_id'], 'description' => $line['description'],
                    'quantity' => Dec::round($line['quantity']), 'unit_price' => Dec::round($line['unit_price']), 'discount' => Dec::round($line['discount'] ?? '0'), 'tax_rate' => $taxRate,
                ];
            }
            if (! $rows) {
                throw new UserError(__('Add at least one line.'));
            }

            $order ??= new SalesOrder(['type' => ($data['type'] ?? null) === 'quotation' ? 'quotation' : 'order', 'status' => 'draft', 'created_by' => auth('web')->id()]);
            if (! $order->exists) {
                $order->company_id = $company->id;
                $order->number = Sequence::next($company->id, 'sales.'.$order->type);
            }
            $order->fill([
                'customer_account_id' => $customer->id,
                'warehouse_id' => $data['warehouse_id'] ?? Stock::defaultWarehouse($company)->id,
                'date' => substr((string) $data['date'], 0, 10),
                'valid_until' => ! empty($data['valid_until']) ? substr((string) $data['valid_until'], 0, 10) : null,
                'currency' => $currency,
                'rate' => $currency === $company->base_currency ? '1' : Dec::round($data['rate'] ?? '0', 8),
                'total' => $total,
                'notes' => $data['notes'] ?? null,
            ])->save();

            SalesOrderLine::withoutGlobalScope('company')->where('sales_order_id', $order->id)->delete();
            foreach ($rows as $row) {
                $line = new SalesOrderLine($row + ['sales_order_id' => $order->id]);
                $line->company_id = $company->id;
                $line->save();
            }

            return $order;
        });
    }

    /** A quotation the customer accepted becomes a confirmed sales order (a new document; the quotation is closed). */
    public static function confirm(SalesOrder $order): SalesOrder
    {
        return DB::transaction(function () use ($order) {
            $order = self::locked($order);
            if ($order->status !== 'draft') {
                return $order;
            }
            if (! $order->isQuotation()) {
                $order->update(['status' => 'confirmed']);

                return $order;
            }

            $new = $order->replicate(['number', 'type', 'status']);
            $new->forceFill(['type' => 'order', 'status' => 'confirmed', 'number' => Sequence::next($order->company_id, 'sales.order'), 'notes' => trim($order->notes."\n".__('From quotation :code', ['code' => $order->code()]))]);
            $new->company_id = $order->company_id;
            $new->save();
            foreach ($order->lines()->withoutGlobalScope('company')->get() as $line) {
                $copy = $line->replicate();
                $copy->sales_order_id = $new->id;
                $copy->company_id = $order->company_id;
                $copy->save();
            }
            $order->update(['status' => 'closed']);

            return $new;
        });
    }

    public static function cancel(SalesOrder $order): SalesOrder
    {
        return DB::transaction(function () use ($order) {
            $order = self::locked($order);
            foreach ($order->lines()->withoutGlobalScope('company')->get() as $line) {
                if (! Dec::isZero($line->delivered_qty) || ! Dec::isZero($line->invoiced_qty)) {
                    throw new UserError(__('This order has deliveries or invoices. Record a return instead of cancelling.'));
                }
            }
            $order->update(['status' => 'cancelled']);

            return $order;
        });
    }

    /**
     * Deliver goods. Empty $quantities delivers everything still open.
     *
     * @param  array<int, mixed>|null  $quantities  order line id => quantity delivered now
     */
    public static function deliver(SalesOrder $order, ?array $quantities, string $date): SalesEvent
    {
        return DB::transaction(function () use ($order, $quantities, $date) {
            [$order, $company, $lines] = self::open($order);
            $event = self::event($order, 'delivery', $date);
            $done = [];
            $cost = '0';
            foreach ($lines as $line) {
                $open = Dec::sub($line->quantity, $line->delivered_qty);
                $quantity = $quantities === null ? $open : Dec::round($quantities[$line->id] ?? '0');
                if (! Dec::isPositive($quantity)) {
                    continue;
                }
                if (Dec::cmp($quantity, $open) > 0) {
                    throw new UserError(__(':line: only :quantity is left to deliver.', ['line' => $line->description, 'quantity' => Stock::qty($open)]));
                }
                $lineCost = '0';
                if ($product = self::stocked($line)) {
                    $movement = Stock::out($company, $product, (int) $order->warehouse_id, $quantity, ['date' => $date, 'type' => 'sale', 'source' => $event, 'reference' => $order->code()]);
                    $lineCost = Dec::abs($movement->value);
                }
                $cost = Dec::add($cost, $lineCost);
                $line->update(['delivered_qty' => Dec::add($line->delivered_qty, $quantity), 'delivered_cost' => Dec::add($line->delivered_cost, $lineCost)]);
                $done[] = ['line_id' => $line->id, 'description' => $line->description, 'quantity' => $quantity];
            }
            if (! $done) {
                throw new UserError(__('There is nothing left to deliver.'));
            }

            $entry = Dec::isZero($cost) ? null : Ledger::post($company, ['date' => $date, 'description' => __('Delivery').' · '.$order->code(), 'reference' => $event->code(), 'source' => $event, 'lines' => [
                ['account' => Account::system($company->id, 'cogs'), 'debit' => $cost],
                ['account' => Account::system($company->id, 'inventory'), 'credit' => $cost],
            ]]);
            $event->update(['lines' => $done, 'cost' => $cost, 'journal_entry_id' => $entry?->id]);

            return $event;
        });
    }

    /** Invoice everything delivered and not yet invoiced, through the shared invoicing service. */
    public static function invoice(SalesOrder $order, string $date, ?string $dueDate = null): Invoice
    {
        return DB::transaction(function () use ($order, $date, $dueDate) {
            [$order, $company, $lines] = self::open($order);
            $invoiceLines = [];
            foreach ($lines as $line) {
                $quantity = Dec::sub($line->delivered_qty, $line->invoiced_qty);
                if (! Dec::isPositive($quantity)) {
                    continue;
                }
                $invoiceLines[] = self::invoiceLine($line, $quantity);
                $line->update(['invoiced_qty' => Dec::add($line->invoiced_qty, $quantity)]);
            }
            if (! $invoiceLines) {
                throw new UserError(__('There is nothing to invoice yet. Deliver the goods first.'));
            }

            $invoice = Invoices::saveDraft($company, [
                'customer_account_id' => $order->customer_account_id, 'date' => $date, 'due_date' => $dueDate,
                'currency' => $order->currency, 'rate' => $order->rate, 'reference' => $order->code(), 'lines' => $invoiceLines,
            ]);
            $invoice->update(['sales_order_id' => $order->id]);
            $invoice = Invoices::issue($invoice);
            self::closeIfDone($order);

            return $invoice;
        });
    }

    /** The customer sends goods back. */
    public static function returnGoods(SalesOrder $order, array $quantities, string $date): SalesEvent
    {
        return DB::transaction(function () use ($order, $quantities, $date) {
            [$order, $company, $lines] = self::open($order, true);
            $event = self::event($order, 'return', $date);
            $done = [];
            $cost = '0';
            $creditLines = [];
            foreach ($lines as $line) {
                $quantity = Dec::round($quantities[$line->id] ?? '0');
                if (! Dec::isPositive($quantity)) {
                    continue;
                }
                if (Dec::cmp($quantity, $line->delivered_qty) > 0) {
                    throw new UserError(__(':line: more is being returned than was delivered.', ['line' => $line->description]));
                }
                // it comes back at what it cost when it left, so the cost of goods sold is reversed exactly
                $lineCost = Dec::cmp($quantity, $line->delivered_qty) === 0 ? Dec::round($line->delivered_cost) : Dec::div(Dec::mul($line->delivered_cost, $quantity, 12), $line->delivered_qty);
                if ($product = self::stocked($line)) {
                    $movement = Stock::in($company, $product, (int) $order->warehouse_id, $quantity, Dec::div($lineCost, $quantity, 8), ['date' => $date, 'type' => 'sale_return', 'source' => $event, 'reference' => $order->code()]);
                    // the ledger takes exactly what went into stock, so the two always agree
                    $lineCost = Dec::round($movement->value);
                    $cost = Dec::add($cost, $lineCost);
                }

                // what was already invoiced of the returned quantity is credited; uninvoiced goods need no credit note
                $uninvoiced = Dec::sub($line->delivered_qty, $line->invoiced_qty);
                $credited = Dec::sub($quantity, Dec::cmp($quantity, $uninvoiced) <= 0 ? $quantity : (Dec::isPositive($uninvoiced) ? $uninvoiced : '0'));
                if (Dec::isPositive($credited)) {
                    $creditLines[] = self::invoiceLine($line, $credited);
                }
                $line->update([
                    'delivered_qty' => Dec::sub($line->delivered_qty, $quantity),
                    'delivered_cost' => Dec::sub($line->delivered_cost, $lineCost),
                    'invoiced_qty' => Dec::sub($line->invoiced_qty, $credited),
                ]);
                $done[] = ['line_id' => $line->id, 'description' => $line->description, 'quantity' => $quantity];
            }
            if (! $done) {
                throw new UserError(__('Enter the quantity returned on at least one line.'));
            }

            $entry = Dec::isZero($cost) ? null : Ledger::post($company, ['date' => $date, 'description' => __('Sales return').' · '.$order->code(), 'reference' => $event->code(), 'source' => $event, 'lines' => [
                ['account' => Account::system($company->id, 'inventory'), 'debit' => $cost],
                ['account' => Account::system($company->id, 'cogs'), 'credit' => $cost],
            ]]);
            $event->update(['lines' => $done, 'cost' => $cost, 'journal_entry_id' => $entry?->id]);

            if ($creditLines) {
                $note = Invoices::saveDraft($company, [
                    'type' => Invoice::CREDIT_NOTE, 'customer_account_id' => $order->customer_account_id, 'date' => $date,
                    'currency' => $order->currency, 'rate' => $order->rate, 'reference' => $event->code(), 'lines' => $creditLines,
                ]);
                $note->update(['sales_order_id' => $order->id]);
                Invoices::issue($note);
            }
            if ($order->status === 'closed') {
                $order->update(['status' => 'confirmed']); // something is open again
            }

            return $event;
        });
    }

    public static function stocked(SalesOrderLine $line): ?Product
    {
        if (! $line->product_id) {
            return null;
        }
        $product = Product::withoutGlobalScope('company')->find($line->product_id);

        return $product?->isStocked() ? $product : null;
    }

    /** A part of an order line as an invoice line: the discount of the line is shared by quantity. */
    private static function invoiceLine(SalesOrderLine $line, string $quantity): array
    {
        return [
            'product_id' => $line->product_id, 'income_account_id' => $line->income_account_id, 'description' => $line->description,
            'quantity' => $quantity, 'unit_price' => $line->unit_price, 'tax_rate' => $line->tax_rate,
            'discount' => Dec::isZero($line->discount) ? '0' : Dec::div(Dec::mul($line->discount, $quantity, 12), $line->quantity),
        ];
    }

    private static function locked(SalesOrder $order): SalesOrder
    {
        return SalesOrder::withoutGlobalScope('company')->lockForUpdate()->findOrFail($order->id);
    }

    private static function open(SalesOrder $order, bool $closedToo = false): array
    {
        $order = self::locked($order);
        if ($order->isQuotation() || ! in_array($order->status, $closedToo ? ['confirmed', 'closed'] : ['confirmed'], true)) {
            throw new UserError($order->status === 'draft' || $order->isQuotation() ? __('Confirm the order first.') : __('This order is :status.', ['status' => __($order->status)]));
        }

        return [$order, Company::findOrFail($order->company_id), $order->lines()->withoutGlobalScope('company')->lockForUpdate()->get()];
    }

    private static function event(SalesOrder $order, string $type, string $date): SalesEvent
    {
        $event = new SalesEvent(['sales_order_id' => $order->id, 'type' => $type, 'number' => Sequence::next($order->company_id, 'sales_event.'.$type), 'date' => substr($date, 0, 10), 'lines' => [], 'created_by' => auth('web')->id()]);
        $event->company_id = $order->company_id;
        $event->save();

        return $event;
    }

    private static function closeIfDone(SalesOrder $order): void
    {
        foreach ($order->lines()->withoutGlobalScope('company')->get() as $line) {
            if (Dec::cmp($line->invoiced_qty, $line->quantity) < 0) {
                return;
            }
        }
        $order->update(['status' => 'closed']);
    }
}
