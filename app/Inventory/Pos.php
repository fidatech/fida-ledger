<?php

namespace App\Inventory;

use App\Accounting\Chart;
use App\Accounting\Invoices;
use App\Accounting\Ledger;
use App\Accounting\Money;
use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\Sequence;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;

/**
 * Point of Sale. It keeps no ledger of its own: one sale is, in one database transaction,
 *
 *   an invoice         issued by App\Accounting\Invoices  (debit customer, credit income and tax)
 *   a stock issue      by App\Inventory\Stock             (debit cost of goods sold, credit inventory)
 *   one Money In per payment method, linked to the invoice (debit cash or bank, credit customer)
 *
 * so stock, income, the customer and the cash drawer are each touched once, by the same code
 * every other module uses. POS sells in the base currency.
 */
class Pos
{
    public static function open(PosRegister $register, mixed $openingCash): PosSession
    {
        return DB::transaction(function () use ($register, $openingCash) {
            // the register row is locked, so two cashiers cannot open it at the same moment
            $register = PosRegister::withoutGlobalScope('company')->lockForUpdate()->findOrFail($register->id);
            if (! $register->is_active) {
                throw new UserError(__('This register is switched off.'));
            }
            if (PosSession::withoutGlobalScope('company')->where('pos_register_id', $register->id)->where('status', 'open')->exists()) {
                throw new UserError(__('This register is already open.'));
            }
            $session = new PosSession(['status' => 'open', 'pos_register_id' => $register->id, 'user_id' => auth('web')->id(), 'opened_at' => now(), 'opening_cash' => Dec::round($openingCash ?? '0')]);
            $session->company_id = $register->company_id;
            $session->save();

            return $session;
        });
    }

    /**
     * @param  list<array{product_id: int, quantity: mixed, unit_price?: mixed, discount?: mixed}>  $items
     * @param  list<array{account_id: int, amount: mixed}>  $payments  must add up to the total exactly (change is given from what was tendered, before it gets here)
     */
    public static function sell(PosSession $session, array $items, array $payments, ?int $customerId = null, ?string $key = null): PosSale
    {
        return DB::transaction(function () use ($session, $items, $payments, $customerId, $key) {
            $session = PosSession::withoutGlobalScope('company')->lockForUpdate()->findOrFail($session->id);
            $company = Company::findOrFail($session->company_id);
            $sales = PosSale::withoutGlobalScope('company')->where('company_id', $company->id);
            // the same sale sent twice (double tap, retry) is one sale
            if ($key !== null && ($existing = (clone $sales)->where('idempotency_key', $key)->first())) {
                return $existing;
            }
            if (! $session->isOpen()) {
                throw new UserError(__('This cashier session is closed. Open the register again.'));
            }
            $register = PosRegister::withoutGlobalScope('company')->findOrFail($session->pos_register_id);
            if (! $items) {
                throw new UserError(__('The cart is empty.'));
            }

            $customer = $customerId
                ? Account::withoutGlobalScope('company')->where('company_id', $company->id)->postable()->where('kind', 'customer')->findOrFail($customerId)
                : self::walkIn($company);
            $salesAccount = Account::system($company->id, 'sales');
            $today = $company->today();

            // the invoice lines, from the catalogue: the price may be overridden at the till, the tax may not
            $products = Product::withoutGlobalScope('company')->where('company_id', $company->id)->where('is_active', true)->whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');
            $lines = [];
            foreach ($items as $item) {
                $product = $products->get((int) $item['product_id']);
                if (! $product) {
                    throw new UserError(__('A product in the cart is no longer sold.'));
                }
                $lines[] = [
                    'product_id' => $product->id, 'description' => $product->name, 'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'] ?? $product->sale_price, 'discount' => $item['discount'] ?? '0', 'tax_rate' => $product->tax_rate,
                    'income_account_id' => $product->income_account_id ?: $salesAccount->id,
                ];
            }

            $invoice = Invoices::saveDraft($company, ['customer_account_id' => $customer->id, 'date' => $today, 'due_date' => $today, 'currency' => $company->base_currency, 'lines' => $lines]);
            $total = Dec::round($invoice->total);

            // payments must cover the sale exactly; each account must be the company's own cash or bank in the base currency
            $paid = Dec::sum(array_column($payments, 'amount'));
            if (Dec::cmp($paid, $total) !== 0) {
                throw new UserError(__('The payments (:paid) do not equal the total (:total).', ['paid' => money($paid), 'total' => money($total)]));
            }

            $sale = new PosSale([
                'pos_session_id' => $session->id, 'number' => Sequence::next($company->id, 'pos_sale'), 'invoice_id' => $invoice->id, 'customer_account_id' => $customer->id,
                'total' => $total, 'items' => [], 'payments' => [], 'idempotency_key' => $key, 'created_by' => auth('web')->id(),
            ]);
            $sale->company_id = $company->id;
            $sale->save();

            $invoice->update(['reference' => $sale->code()]);
            $invoice = Invoices::issue($invoice);

            // goods leave the register's warehouse at average cost
            $cost = '0';
            $soldItems = [];
            foreach ($lines as $line) {
                $product = $products->get($line['product_id']);
                $lineCost = '0';
                if ($product->isStocked()) {
                    $lineCost = Dec::abs(Stock::out($company, $product, (int) $register->warehouse_id, $line['quantity'], ['date' => $today, 'type' => 'sale', 'source' => $sale, 'reference' => $sale->code()])->value);
                }
                $cost = Dec::add($cost, $lineCost);
                $soldItems[] = ['product_id' => $product->id, 'name' => $product->name, 'quantity' => Dec::round($line['quantity']), 'unit_price' => Dec::round($line['unit_price']), 'discount' => Dec::round($line['discount']), 'cost' => $lineCost];
            }
            $costEntry = Dec::isZero($cost) ? null : Ledger::post($company, ['date' => $today, 'description' => __('Cost of sale').' · '.$sale->code(), 'reference' => $sale->code(), 'source' => $sale, 'lines' => [
                ['account' => Account::system($company->id, 'cogs'), 'debit' => $cost],
                ['account' => Account::system($company->id, 'inventory'), 'credit' => $cost],
            ]]);

            $taken = [];
            $cash = '0';
            foreach ($payments as $payment) {
                if (! Dec::isPositive($payment['amount'])) {
                    continue;
                }
                $movement = Money::record($company, [
                    'direction' => 'in', 'kind' => 'customer_receipt', 'date' => $today, 'cash_account_id' => (int) $payment['account_id'],
                    'counter_account_id' => $customer->id, 'amount' => $payment['amount'], 'description' => $sale->code(), 'reference' => $sale->code(),
                ]);
                if ($movement->currency !== $company->base_currency) {
                    throw new UserError(__('Point of Sale takes payments in :currency only.', ['currency' => $company->base_currency]));
                }
                Invoices::allocate($invoice, $movement, $payment['amount']);
                if ((int) $payment['account_id'] === (int) $register->cash_account_id) {
                    $cash = Dec::add($cash, $payment['amount']);
                }
                $taken[] = ['account_id' => (int) $payment['account_id'], 'name' => $movement->cashAccount()->withoutGlobalScope('company')->value('name'), 'amount' => Dec::round($payment['amount']), 'movement_id' => $movement->id];
            }

            $sale->update(['items' => $soldItems, 'payments' => $taken, 'cost' => $cost, 'cash_paid' => $cash, 'cost_entry_id' => $costEntry?->id]);

            return $sale;
        });
    }

    /**
     * Refund a whole sale: a credit note for the invoice, the goods back into stock at what they
     * cost, and the money back out of the accounts it was paid into.
     */
    public static function refund(PosSale $sale, PosSession $session): PosSale
    {
        return DB::transaction(function () use ($sale, $session) {
            $sale = PosSale::withoutGlobalScope('company')->lockForUpdate()->findOrFail($sale->id);
            if ($sale->status !== 'paid') {
                throw new UserError(__('This sale was already refunded.'));
            }
            if (! $session->isOpen() || (int) $session->company_id !== (int) $sale->company_id) {
                throw new UserError(__('Open the register to give a refund.'));
            }
            $company = Company::findOrFail($sale->company_id);
            $register = PosRegister::withoutGlobalScope('company')->findOrFail($session->pos_register_id);
            $invoice = Invoice::withoutGlobalScope('company')->with(['lines' => fn ($q) => $q->withoutGlobalScope('company')])->findOrFail($sale->invoice_id);
            $today = $company->today();

            $note = Invoices::saveDraft($company, [
                'type' => Invoice::CREDIT_NOTE, 'customer_account_id' => $sale->customer_account_id, 'date' => $today, 'currency' => $invoice->currency, 'rate' => $invoice->rate, 'reference' => $sale->code(),
                'lines' => $invoice->lines->map(fn ($line) => $line->only(['product_id', 'description', 'quantity', 'unit_price', 'discount', 'tax_rate', 'income_account_id']))->all(),
            ]);
            Invoices::issue($note);

            $cost = '0';
            foreach ($sale->items as $item) {
                if (Dec::isPositive($item['cost'])) {
                    $product = Product::withoutGlobalScope('company')->findOrFail($item['product_id']);
                    $movement = Stock::in($company, $product, (int) $register->warehouse_id, $item['quantity'], Dec::div($item['cost'], $item['quantity'], 8), ['date' => $today, 'type' => 'sale_return', 'source' => $sale, 'reference' => $sale->code()]);
                    $cost = Dec::add($cost, $movement->value);
                }
            }
            if (! Dec::isZero($cost)) {
                Ledger::post($company, ['date' => $today, 'description' => __('Refund').' · '.$sale->code(), 'reference' => $sale->code(), 'source' => $sale, 'lines' => [
                    ['account' => Account::system($company->id, 'inventory'), 'debit' => $cost],
                    ['account' => Account::system($company->id, 'cogs'), 'credit' => $cost],
                ]]);
            }

            // the money goes back the way it came, except that cash taken at another drawer is paid out of the drawer open now
            $soldAt = PosRegister::withoutGlobalScope('company')->find(PosSession::withoutGlobalScope('company')->whereKey($sale->pos_session_id)->value('pos_register_id'));
            foreach ($sale->payments as $payment) {
                $accountId = (int) $payment['account_id'] === (int) $soldAt?->cash_account_id ? (int) $register->cash_account_id : (int) $payment['account_id'];
                Money::record($company, [
                    'direction' => 'out', 'kind' => 'refund_paid', 'date' => $today, 'cash_account_id' => $accountId,
                    'counter_account_id' => $sale->customer_account_id, 'amount' => $payment['amount'], 'description' => __('Refund').' '.$sale->code(), 'reference' => $sale->code(),
                ]);
            }
            $sale->update(['status' => 'refunded']);

            return $sale;
        });
    }

    /** What should be in the drawer now: the opening cash, plus cash sales, minus cash refunds, of this session. */
    public static function expectedCash(PosSession $session): string
    {
        $register = PosRegister::withoutGlobalScope('company')->findOrFail($session->pos_register_id);
        $expected = Dec::round($session->opening_cash);
        foreach (PosSale::withoutGlobalScope('company')->where('pos_session_id', $session->id)->get(['cash_paid', 'status', 'payments']) as $sale) {
            $expected = Dec::add($expected, $sale->cash_paid);
        }
        // refunds given in this session, whichever session sold them: Money Out from the drawer referenced to a POS sale
        $refunds = \App\Models\MoneyMovement::withoutGlobalScope('company')->where('company_id', $session->company_id)
            ->where('direction', 'out')->where('kind', 'refund_paid')->where('status', 'posted')
            ->where('cash_account_id', $register->cash_account_id)->where('reference', 'like', 'POS-%')
            ->where('created_at', '>=', $session->opened_at)->when($session->closed_at, fn ($q) => $q->where('created_at', '<=', $session->closed_at))
            ->sum('amount');

        return Dec::sub($expected, $refunds);
    }

    /**
     * Close the shift: the cashier counts the drawer. A shortage is posted as an expense and a
     * surplus as income, so the cash account in the books equals what is really in the drawer.
     */
    public static function close(PosSession $session, mixed $countedCash, ?string $notes = null): PosSession
    {
        return DB::transaction(function () use ($session, $countedCash, $notes) {
            $session = PosSession::withoutGlobalScope('company')->lockForUpdate()->findOrFail($session->id);
            if (! $session->isOpen()) {
                return $session;
            }
            $company = Company::findOrFail($session->company_id);
            $register = PosRegister::withoutGlobalScope('company')->findOrFail($session->pos_register_id);
            $expected = self::expectedCash($session);
            $counted = Dec::round($countedCash);
            $difference = Dec::sub($counted, $expected);

            $entry = null;
            if (! Dec::isZero($difference)) {
                $short = Dec::isNegative($difference);
                $other = Account::system($company->id, $short ? 'other_expenses' : 'other_income');
                $entry = Ledger::post($company, ['date' => $company->today(), 'description' => ($short ? __('Cash shortage') : __('Cash surplus')).' · '.$register->name, 'source' => $session, 'idempotency_key' => 'pos-close-'.$session->id, 'lines' => [
                    ['account' => $short ? $other : (int) $register->cash_account_id, 'debit' => Dec::abs($difference)],
                    ['account' => $short ? (int) $register->cash_account_id : $other, 'credit' => Dec::abs($difference)],
                ]]);
            }
            $session->update(['status' => 'closed', 'closed_at' => now(), 'expected_cash' => $expected, 'counted_cash' => $counted, 'difference' => $difference, 'difference_entry_id' => $entry?->id, 'notes' => $notes]);

            return $session;
        });
    }

    /** The customer account that sales without a named customer are put on. */
    public static function walkIn(Company $company): Account
    {
        return Account::withoutGlobalScope('company')->where('company_id', $company->id)->where('kind', 'customer')->where('name', 'Walk-in customer')->first()
            ?? Chart::open($company, ['kind' => 'customer', 'name' => 'Walk-in customer']);
    }
}
