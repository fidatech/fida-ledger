<?php

namespace App\Accounting;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Company;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use App\Models\MoneyMovement;
use App\Models\Sequence;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;

/**
 * Invoices and credit notes.
 *
 *   Issuing an invoice:   debit the customer, credit income (per line) and taxes payable.
 *   Issuing a credit note: the same entry with the sides swapped.
 *   A payment:            is a Money In from the customer (debit cash, credit the customer).
 *                         It is only linked to the invoice here; no second entry is posted,
 *                         so the income is counted once, when the invoice is issued.
 */
class Invoices
{
    /**
     * Save a draft (new, or replacing the lines of an existing draft).
     *
     * @param  array{type?: string, customer_account_id: int, date: string, due_date?: ?string, currency: string, rate?: mixed, reference?: ?string, notes?: ?string,
     *     lines: list<array{description: string, quantity: mixed, unit_price: mixed, discount?: mixed, tax_rate?: mixed, income_account_id: int, product_id?: ?int}>}  $data
     */
    public static function saveDraft(Company $company, array $data, ?Invoice $invoice = null): Invoice
    {
        return DB::transaction(function () use ($company, $data, $invoice) {
            if ($invoice && $invoice->status !== Invoice::DRAFT) {
                throw new UserError(__('Only a draft can be changed. Cancel the invoice and make a new one.'));
            }

            $customer = Account::withoutGlobalScope('company')->where('company_id', $company->id)->postable()->where('kind', 'customer')->find($data['customer_account_id']);
            if (! $customer) {
                throw new UserError(__('Choose a customer.'));
            }
            $incomeIds = Account::withoutGlobalScope('company')->where('company_id', $company->id)->postable()->where('class', 'income')->pluck('id')->all();

            $currency = strtoupper($data['currency']);
            $subtotal = '0';
            $tax = '0';
            $rows = [];
            foreach ($data['lines'] as $line) {
                if (! in_array((int) $line['income_account_id'], $incomeIds, true)) {
                    throw new UserError(__('Choose an income account for every line.'));
                }
                $gross = Dec::mul($line['quantity'], $line['unit_price']);
                $net = Dec::sub($gross, $line['discount'] ?? '0');
                if (Dec::isNegative($net)) {
                    throw new UserError(__('A discount cannot be more than the line it is on.'));
                }
                $rate = Dec::round($line['tax_rate'] ?? '0', 4);
                $lineTax = Dec::div(Dec::mul($net, $rate, 8), '100');
                $rows[] = [
                    'product_id' => $line['product_id'] ?? null,
                    'income_account_id' => (int) $line['income_account_id'],
                    'description' => $line['description'],
                    'quantity' => Dec::round($line['quantity']),
                    'unit_price' => Dec::round($line['unit_price']),
                    'discount' => Dec::round($line['discount'] ?? '0'),
                    'tax_rate' => $rate,
                    'net' => $net,
                    'tax' => $lineTax,
                    'total' => Dec::add($net, $lineTax),
                ];
                $subtotal = Dec::add($subtotal, $net);
                $tax = Dec::add($tax, $lineTax);
            }
            if (! $rows || ! Dec::isPositive(Dec::add($subtotal, $tax))) {
                throw new UserError(__('Add at least one line with an amount.'));
            }

            $invoice ??= new Invoice([
                'type' => ($data['type'] ?? null) === Invoice::CREDIT_NOTE ? Invoice::CREDIT_NOTE : Invoice::INVOICE,
                'status' => Invoice::DRAFT,
                'created_by' => auth('web')->id(),
            ]);
            if (! $invoice->exists) {
                $invoice->company_id = $company->id;
                $invoice->number = Sequence::next($company->id, 'invoice.'.$invoice->type);
            }
            $invoice->fill([
                'customer_account_id' => $customer->id,
                'date' => substr((string) $data['date'], 0, 10),
                'due_date' => ! empty($data['due_date']) ? substr((string) $data['due_date'], 0, 10) : null,
                'currency' => $currency,
                'rate' => $currency === $company->base_currency ? '1' : Dec::round($data['rate'] ?? '0', (int) config('ledger.rate_scale')),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => Dec::add($subtotal, $tax),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();

            InvoiceLine::withoutGlobalScope('company')->where('invoice_id', $invoice->id)->delete();
            foreach ($rows as $row) {
                $line = new InvoiceLine($row + ['invoice_id' => $invoice->id]);
                $line->company_id = $company->id;
                $line->save();
            }

            return $invoice;
        });
    }

    /** Issue a draft: from here on it is in the ledger and the customer owes it. */
    public static function issue(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::withoutGlobalScope('company')->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== Invoice::DRAFT) {
                return $invoice; // pressed twice: it is issued once
            }
            $company = Company::findOrFail($invoice->company_id);
            $date = $invoice->date->toDateString();

            $rate = '1';
            if ($invoice->currency !== $company->base_currency) {
                $rate = Dec::isPositive($invoice->rate) ? $invoice->rate : ExchangeRate::on($company, $invoice->currency, $date);
                if ($rate === null || ! Dec::isPositive($rate)) {
                    throw new UserError(__('Enter the exchange rate of :currency.', ['currency' => $invoice->currency]));
                }
            }

            // an invoice credits income and debits the customer; a credit note does the opposite
            [$customerSide, $otherSide] = $invoice->isCreditNote() ? ['credit', 'debit'] : ['debit', 'credit'];
            $text = $invoice->code().' · '.$invoice->customer()->withoutGlobalScope('company')->value('name');

            $lines = [];
            $baseTotal = '0';
            $perAccount = [];
            foreach ($invoice->lines()->withoutGlobalScope('company')->get() as $line) {
                $perAccount[$line->income_account_id] = Dec::add($perAccount[$line->income_account_id] ?? '0', $line->net);
            }
            foreach ($perAccount as $accountId => $amount) {
                if (Dec::isZero($amount)) {
                    continue;
                }
                $base = Dec::mul($amount, $rate);
                $baseTotal = Dec::add($baseTotal, $base);
                $lines[] = ['account' => (int) $accountId, $otherSide => $amount, 'currency' => $invoice->currency, 'rate' => $rate, 'base' => $base, 'description' => $text];
            }
            if (Dec::isPositive($invoice->tax)) {
                $base = Dec::mul($invoice->tax, $rate);
                $baseTotal = Dec::add($baseTotal, $base);
                $lines[] = ['account' => Account::system($company->id, 'taxes_payable'), $otherSide => $invoice->tax, 'currency' => $invoice->currency, 'rate' => $rate, 'base' => $base, 'description' => $text];
            }
            // the customer's line carries the sum of the other lines in the base currency, so rounding never unbalances the entry
            array_unshift($lines, ['account' => (int) $invoice->customer_account_id, $customerSide => $invoice->total, 'currency' => $invoice->currency, 'rate' => $rate, 'base' => $baseTotal, 'description' => $text]);

            $entry = Ledger::post($company, [
                'date' => $date,
                'description' => $text,
                'reference' => $invoice->code(),
                'source' => $invoice,
                'idempotency_key' => 'invoice-'.$invoice->id,
                'lines' => $lines,
            ]);

            $invoice->update(['status' => Invoice::ISSUED, 'rate' => $rate, 'journal_entry_id' => $entry->id]);

            return $invoice;
        });
    }

    /** Cancel an issued invoice that has no payment on it: its entry is reversed and the invoice is kept, marked void. */
    public static function void(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::withoutGlobalScope('company')->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status === Invoice::VOID) {
                throw new UserError(__('This was already cancelled.'));
            }
            if (Dec::isPositive($invoice->paid)) {
                throw new UserError(__('This invoice has payments. Cancel them first, or issue a credit note.'));
            }
            if ($invoice->status === Invoice::ISSUED) {
                Ledger::reverse($invoice->entry()->withoutGlobalScope('company')->firstOrFail());
            }
            $invoice->update(['status' => Invoice::VOID]);

            return $invoice;
        });
    }

    /**
     * Link a Money In from the customer to an invoice. Nothing is posted: the Money In already
     * credited the customer. The amount cannot be more than what is due or than the movement.
     */
    public static function allocate(Invoice $invoice, MoneyMovement $movement, mixed $amount = null): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $movement, $amount) {
            $invoice = Invoice::withoutGlobalScope('company')->lockForUpdate()->findOrFail($invoice->id);
            if ((int) $invoice->company_id !== (int) $movement->company_id || $movement->direction !== MoneyMovement::IN || $movement->isVoid()
                || (int) $movement->counter_account_id !== (int) $invoice->customer_account_id) {
                throw new UserError(__('This payment does not belong to the customer of the invoice.'));
            }
            if ($movement->currency !== $invoice->currency) {
                throw new UserError(__('The invoice is in :currency. Receive the payment into an account of that currency.', ['currency' => $invoice->currency]));
            }

            $used = InvoicePayment::withoutGlobalScope('company')->where('money_movement_id', $movement->id)->sum('amount');
            $free = Dec::sub($movement->amount, $used);
            $amount = Dec::round($amount ?? (Dec::cmp($free, $invoice->due()) < 0 ? $free : $invoice->due()));
            if (! Dec::isPositive($amount) || Dec::cmp($amount, $invoice->due()) > 0 || Dec::cmp($amount, $free) > 0) {
                throw new UserError(__('The amount is more than what is due on :invoice.', ['invoice' => $invoice->code()]));
            }

            $payment = new InvoicePayment(['invoice_id' => $invoice->id, 'money_movement_id' => $movement->id, 'amount' => $amount]);
            $payment->company_id = $invoice->company_id;
            $payment->save();
            $invoice->update(['paid' => Dec::add($invoice->paid, $amount)]);

            return $payment;
        });
    }

    /** A cancelled Money In no longer settles anything: its links are removed and the invoices are due again. */
    public static function release(MoneyMovement $movement): void
    {
        foreach (InvoicePayment::withoutGlobalScope('company')->where('money_movement_id', $movement->id)->get() as $payment) {
            $invoice = Invoice::withoutGlobalScope('company')->lockForUpdate()->findOrFail($payment->invoice_id);
            $invoice->update(['paid' => Dec::sub($invoice->paid, $payment->amount)]);
            $payment->delete();
        }
    }
}
