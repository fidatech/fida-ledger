<?php

namespace App\Inventory;

use App\Accounting\Ledger;
use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Company;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\PurchaseEvent;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Sequence;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;

/**
 * The purchase flow. Ordering, receiving, billing and paying are four separate events:
 *
 *   Order      nothing is posted.
 *   Receipt    stock rises at the order price; debit Inventory, credit "Goods received not invoiced".
 *   Bill       debit "Goods received not invoiced" (stocked lines) or a purchase expense (other
 *              lines), credit the supplier. Stock does not move again, so goods are counted once.
 *   Payment    a Money Out to the supplier (the Money Out screen); it settles the payable.
 *   Return     stock leaves at its average cost; the supplier (if billed) or the accrual is debited
 *              at the order price and any difference goes to Inventory adjustments.
 */
class Purchasing
{
    /** @param  array{supplier_account_id: int, warehouse_id?: ?int, date: string, expected_date?: ?string, currency: string, rate?: mixed, notes?: ?string, lines: list<array>}  $data */
    public static function save(Company $company, array $data, ?PurchaseOrder $order = null): PurchaseOrder
    {
        return DB::transaction(function () use ($company, $data, $order) {
            if ($order && $order->status !== 'draft') {
                throw new UserError(__('Only a draft order can be changed.'));
            }
            $supplier = Account::withoutGlobalScope('company')->where('company_id', $company->id)->postable()->where('kind', 'supplier')->find($data['supplier_account_id']);
            if (! $supplier) {
                throw new UserError(__('Choose a supplier.'));
            }
            $currency = strtoupper($data['currency']);
            $total = '0';
            $rows = [];
            foreach ($data['lines'] as $line) {
                $rows[] = ['product_id' => $line['product_id'] ?? null, 'description' => $line['description'], 'quantity' => Dec::round($line['quantity']), 'unit_price' => Dec::round($line['unit_price'])];
                $total = Dec::add($total, Dec::mul($line['quantity'], $line['unit_price']));
            }
            if (! $rows) {
                throw new UserError(__('Add at least one line.'));
            }

            $order ??= new PurchaseOrder(['status' => 'draft', 'created_by' => auth('web')->id()]);
            if (! $order->exists) {
                $order->company_id = $company->id;
                $order->number = Sequence::next($company->id, 'purchase_order');
            }
            $order->fill([
                'supplier_account_id' => $supplier->id,
                'warehouse_id' => $data['warehouse_id'] ?? Stock::defaultWarehouse($company)->id,
                'date' => substr((string) $data['date'], 0, 10),
                'expected_date' => ! empty($data['expected_date']) ? substr((string) $data['expected_date'], 0, 10) : null,
                'currency' => $currency,
                'rate' => $currency === $company->base_currency ? '1' : Dec::round($data['rate'] ?? '0', 8),
                'total' => $total,
                'notes' => $data['notes'] ?? null,
            ])->save();

            PurchaseOrderLine::withoutGlobalScope('company')->where('purchase_order_id', $order->id)->delete();
            foreach ($rows as $row) {
                $line = new PurchaseOrderLine($row + ['purchase_order_id' => $order->id]);
                $line->company_id = $company->id;
                $line->save();
            }

            return $order;
        });
    }

    /** Approval fixes the order and its exchange rate: receipts and bills use that rate, so the accrual clears exactly. */
    public static function approve(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $order = self::locked($order);
            if ($order->status !== 'draft') {
                return $order;
            }
            $company = Company::findOrFail($order->company_id);
            $rate = '1';
            if ($order->currency !== $company->base_currency) {
                $rate = Dec::isPositive($order->rate) ? $order->rate : ExchangeRate::on($company, $order->currency, $order->date->toDateString());
                if ($rate === null || ! Dec::isPositive($rate)) {
                    throw new UserError(__('Enter the exchange rate of :currency.', ['currency' => $order->currency]));
                }
            }
            $order->update(['status' => 'approved', 'rate' => $rate, 'approved_by' => auth('web')->id()]);

            return $order;
        });
    }

    public static function cancel(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $order = self::locked($order);
            foreach ($order->lines()->withoutGlobalScope('company')->get() as $line) {
                if (! Dec::isZero($line->received_qty) || ! Dec::isZero($line->billed_qty)) {
                    throw new UserError(__('This order has receipts or bills. Return the goods instead of cancelling.'));
                }
            }
            $order->update(['status' => 'cancelled']);

            return $order;
        });
    }

    /**
     * Goods arrived. Only lines of stocked products are received; services and non-stock items go straight to the bill.
     *
     * @param  array<int, mixed>  $quantities  order line id => quantity received now
     */
    public static function receive(PurchaseOrder $order, array $quantities, string $date): PurchaseEvent
    {
        return DB::transaction(function () use ($order, $quantities, $date) {
            [$order, $company, $lines] = self::open($order);
            $event = self::event($order, 'receipt', $date);
            $done = [];
            $value = '0';
            foreach ($lines as $line) {
                $quantity = Dec::round($quantities[$line->id] ?? '0');
                if (! Dec::isPositive($quantity)) {
                    continue;
                }
                $product = self::stocked($line);
                if (! $product) {
                    throw new UserError(__(':line is not kept in stock, so it is not received. Bill it directly.', ['line' => $line->description]));
                }
                if (Dec::cmp(Dec::add($line->received_qty, $quantity), $line->quantity) > 0) {
                    throw new UserError(__('More :line is being received than was ordered.', ['line' => $line->description]));
                }
                // stock comes in at the order price turned into the base currency
                $unitCost = Dec::mul($line->unit_price, $order->rate, 8);
                $movement = Stock::in($company, $product, (int) $order->warehouse_id, $quantity, $unitCost, ['date' => $date, 'type' => 'purchase', 'source' => $event, 'reference' => $order->code()]);
                $value = Dec::add($value, $movement->value);
                $line->update(['received_qty' => Dec::add($line->received_qty, $quantity)]);
                $done[] = ['line_id' => $line->id, 'description' => $line->description, 'quantity' => $quantity, 'unit_price' => $line->unit_price];
            }
            if (! $done) {
                throw new UserError(__('Enter the quantity received on at least one line.'));
            }

            $text = __('Goods received').' · '.$order->code();
            $entry = Dec::isZero($value) ? null : Ledger::post($company, ['date' => $date, 'description' => $text, 'reference' => $event->code(), 'source' => $event, 'lines' => [
                ['account' => Account::system($company->id, 'inventory'), 'debit' => $value],
                ['account' => Account::system($company->id, 'grni'), 'credit' => $value],
            ]]);
            $event->update(['lines' => $done, 'journal_entry_id' => $entry?->id]);
            self::closeIfDone($order);

            return $event;
        });
    }

    /**
     * The supplier's bill. A stocked line can be billed only for what was received and not yet billed;
     * other lines for what was ordered and not yet billed. Empty $quantities bills everything billable.
     */
    public static function bill(PurchaseOrder $order, ?array $quantities, string $date, ?string $reference = null, ?string $dueDate = null): PurchaseEvent
    {
        return DB::transaction(function () use ($order, $quantities, $date, $reference, $dueDate) {
            [$order, $company, $lines] = self::open($order);
            $event = self::event($order, 'bill', $date, ['reference' => $reference, 'due_date' => $dueDate]);
            $done = [];
            $accrued = '0'; // base value of stocked goods: clears "Goods received not invoiced"
            $expensed = '0'; // base value of everything else
            $amount = '0';
            foreach ($lines as $line) {
                $billable = Dec::sub(self::stocked($line) ? $line->received_qty : $line->quantity, $line->billed_qty);
                $quantity = $quantities === null ? $billable : Dec::round($quantities[$line->id] ?? '0');
                if (! Dec::isPositive($quantity)) {
                    continue;
                }
                if (Dec::cmp($quantity, $billable) > 0) {
                    throw new UserError(__(':line: only :quantity can be billed now.', ['line' => $line->description, 'quantity' => Stock::qty($billable)]));
                }
                $lineAmount = Dec::mul($quantity, $line->unit_price);
                $base = Dec::mul($quantity, Dec::mul($line->unit_price, $order->rate, 8));
                if (self::stocked($line)) {
                    $accrued = Dec::add($accrued, $base);
                } else {
                    $expensed = Dec::add($expensed, $base);
                }
                $amount = Dec::add($amount, $lineAmount);
                $line->update(['billed_qty' => Dec::add($line->billed_qty, $quantity)]);
                $done[] = ['line_id' => $line->id, 'description' => $line->description, 'quantity' => $quantity, 'unit_price' => $line->unit_price];
            }
            if (! $done) {
                throw new UserError(__('There is nothing to bill yet. Receive the goods first.'));
            }

            $text = __('Supplier bill').' · '.$order->code().($reference ? ' · '.$reference : '');
            $entryLines = [];
            if (! Dec::isZero($accrued)) {
                $entryLines[] = ['account' => Account::system($company->id, 'grni'), 'debit' => $accrued];
            }
            if (! Dec::isZero($expensed)) {
                $entryLines[] = ['account' => Account::system($company->id, 'purchase_expenses'), 'debit' => $expensed];
            }
            // the supplier's line carries the exact base value of the other lines, so the entry always balances
            $entryLines[] = ['account' => (int) $order->supplier_account_id, 'credit' => $amount, 'currency' => $order->currency, 'rate' => $order->rate, 'base' => Dec::add($accrued, $expensed)];
            $entry = Ledger::post($company, ['date' => $date, 'description' => $text, 'reference' => $event->code(), 'source' => $event, 'lines' => $entryLines]);

            $event->update(['lines' => $done, 'amount' => $amount, 'journal_entry_id' => $entry->id]);
            self::closeIfDone($order);

            return $event;
        });
    }

    /** Send received goods back to the supplier. */
    public static function returnGoods(PurchaseOrder $order, array $quantities, string $date): PurchaseEvent
    {
        return DB::transaction(function () use ($order, $quantities, $date) {
            [$order, $company, $lines] = self::open($order, true);
            $event = self::event($order, 'return', $date);
            $done = [];
            $cost = '0';      // what leaves Inventory (average cost)
            $accrual = '0';   // returned before it was billed: the accrual is undone
            $payable = '0';   // returned after it was billed: the supplier owes it back (base)
            $payableAmount = '0';
            foreach ($lines as $line) {
                $quantity = Dec::round($quantities[$line->id] ?? '0');
                if (! Dec::isPositive($quantity)) {
                    continue;
                }
                $product = self::stocked($line);
                if (! $product || Dec::cmp($quantity, $line->received_qty) > 0) {
                    throw new UserError(__(':line: more is being returned than was received.', ['line' => $line->description]));
                }
                $movement = Stock::out($company, $product, (int) $order->warehouse_id, $quantity, ['date' => $date, 'type' => 'purchase_return', 'source' => $event, 'reference' => $order->code()]);
                $cost = Dec::add($cost, Dec::abs($movement->value));

                // unbilled goods are returned first; the rest was billed and comes off the supplier's account
                $unbilled = Dec::sub($line->received_qty, $line->billed_qty);
                $fromUnbilled = Dec::cmp($quantity, $unbilled) <= 0 ? $quantity : (Dec::isPositive($unbilled) ? $unbilled : '0');
                $fromBilled = Dec::sub($quantity, $fromUnbilled);
                $unitBase = Dec::mul($line->unit_price, $order->rate, 8);
                $accrual = Dec::add($accrual, Dec::mul($fromUnbilled, $unitBase));
                $payable = Dec::add($payable, Dec::mul($fromBilled, $unitBase));
                $payableAmount = Dec::add($payableAmount, Dec::mul($fromBilled, $line->unit_price));

                $line->update(['received_qty' => Dec::sub($line->received_qty, $quantity), 'billed_qty' => Dec::sub($line->billed_qty, $fromBilled)]);
                $done[] = ['line_id' => $line->id, 'description' => $line->description, 'quantity' => $quantity, 'unit_price' => $line->unit_price];
            }
            if (! $done) {
                throw new UserError(__('Enter the quantity returned on at least one line.'));
            }

            $entryLines = [];
            if (! Dec::isZero($accrual)) {
                $entryLines[] = ['account' => Account::system($company->id, 'grni'), 'debit' => $accrual];
            }
            if (! Dec::isZero($payableAmount)) {
                $entryLines[] = ['account' => (int) $order->supplier_account_id, 'debit' => $payableAmount, 'currency' => $order->currency, 'rate' => $order->rate, 'base' => $payable];
            }
            $entryLines[] = ['account' => Account::system($company->id, 'inventory'), 'credit' => $cost];
            // goods bought at one price and carried at another average: the difference is a stock gain or loss
            $difference = Dec::sub(Dec::add($accrual, $payable), $cost);
            if (! Dec::isZero($difference)) {
                $entryLines[] = ['account' => Account::system($company->id, 'inventory_adjustments'), Dec::isPositive($difference) ? 'credit' : 'debit' => Dec::abs($difference)];
            }
            $entry = Ledger::post($company, ['date' => $date, 'description' => __('Purchase return').' · '.$order->code(), 'reference' => $event->code(), 'source' => $event, 'lines' => $entryLines]);
            $event->update(['lines' => $done, 'amount' => $payableAmount, 'journal_entry_id' => $entry->id]);

            return $event;
        });
    }

    /** The product of a line when it is kept in stock, else null. */
    public static function stocked(PurchaseOrderLine $line): ?Product
    {
        if (! $line->product_id) {
            return null;
        }
        $product = Product::withoutGlobalScope('company')->find($line->product_id);

        return $product?->isStocked() ? $product : null;
    }

    private static function locked(PurchaseOrder $order): PurchaseOrder
    {
        return PurchaseOrder::withoutGlobalScope('company')->lockForUpdate()->findOrFail($order->id);
    }

    /** @return array{0: PurchaseOrder, 1: Company, 2: \Illuminate\Support\Collection} */
    private static function open(PurchaseOrder $order, bool $closedToo = false): array
    {
        $order = self::locked($order);
        if (! in_array($order->status, $closedToo ? ['approved', 'closed'] : ['approved'], true)) {
            throw new UserError($order->status === 'draft' ? __('Approve the order first.') : __('This order is :status.', ['status' => __($order->status)]));
        }

        return [$order, Company::findOrFail($order->company_id), $order->lines()->withoutGlobalScope('company')->lockForUpdate()->get()];
    }

    private static function event(PurchaseOrder $order, string $type, string $date, array $extra = []): PurchaseEvent
    {
        $event = new PurchaseEvent(['purchase_order_id' => $order->id, 'type' => $type, 'number' => Sequence::next($order->company_id, 'purchase.'.$type), 'date' => substr($date, 0, 10), 'lines' => [], 'created_by' => auth('web')->id()] + $extra);
        $event->company_id = $order->company_id;
        $event->save();

        return $event;
    }

    /** Everything received (where it applies) and billed: the order is closed. */
    private static function closeIfDone(PurchaseOrder $order): void
    {
        foreach ($order->lines()->withoutGlobalScope('company')->get() as $line) {
            if (Dec::cmp($line->billed_qty, $line->quantity) < 0) {
                return;
            }
        }
        $order->update(['status' => 'closed']);
    }
}
