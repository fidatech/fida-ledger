<?php

namespace App\Accounting;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Company;
use App\Models\ExchangeRate;
use App\Models\MoneyMovement;
use App\Models\Sequence;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;

/**
 * Money In and Money Out.
 *
 *   Money In:   debit the cash or bank account, credit the other account.
 *   Money Out:  debit the other account, credit the cash or bank account.
 *
 * The class of the other account decides what the movement means: a customer account lowers
 * a receivable, a loan account changes a liability, and only an income or expense account
 * touches profit. So money received is not counted as income unless it is income.
 */
class Money
{
    /**
     * @param  array{direction: string, kind: string, date: string, cash_account_id: int, counter_account_id: int, amount: mixed,
     *     rate?: mixed, description?: ?string, reference?: ?string, attachment_path?: ?string, idempotency_key?: ?string}  $data
     */
    public static function record(Company $company, array $data): MoneyMovement
    {
        $direction = $data['direction'];
        $kind = config('ledger.money.'.$direction.'.'.$data['kind']);
        if (! $kind) {
            throw new UserError(__('Choose what this money is for.'));
        }

        return DB::transaction(function () use ($company, $data, $direction, $kind) {
            $movements = MoneyMovement::withoutGlobalScope('company')->where('company_id', $company->id);

            // the same form sent twice (double click, retry after a timeout) records one movement
            $key = $data['idempotency_key'] ?? null;
            if ($key !== null && ($existing = (clone $movements)->where('idempotency_key', $key)->first())) {
                return $existing;
            }

            $accounts = Account::withoutGlobalScope('company')->where('company_id', $company->id);
            $cash = (clone $accounts)->cashOrBank()->postable()->find($data['cash_account_id']);
            $counter = (clone $accounts)->postable()->find($data['counter_account_id']);
            if (! $cash) {
                throw new UserError(__('Choose a cash or bank account.'));
            }
            if (! $counter || $counter->isCash()) {
                throw new UserError(__('Choose the account this money is for. To move money between cash and bank, use a transfer.'));
            }
            if ($kind['kinds'] !== null && ! in_array($counter->kind, $kind['kinds'], true)) {
                throw new UserError(__(':account cannot be used for ":kind".', ['account' => $counter->name, 'kind' => __($kind['label'])]));
            }

            $amount = Dec::round($data['amount']);
            if (! Dec::isPositive($amount)) {
                throw new UserError(__('The amount must be more than zero.'));
            }

            $currency = $cash->currency ?? $company->base_currency;
            $date = substr((string) $data['date'], 0, 10);
            $rate = '1';
            if ($currency !== $company->base_currency) {
                $rate = Dec::valid($data['rate'] ?? null) && Dec::isPositive($data['rate'])
                    ? Dec::round($data['rate'], (int) config('ledger.rate_scale'))
                    : ExchangeRate::rateOn($company, $currency, $date);
                if ($rate === null || ! Dec::isPositive($rate)) {
                    throw new UserError(__('Enter the exchange rate of :currency.', ['currency' => $currency]));
                }
            }

            $movement = new MoneyMovement([
                'direction' => $direction,
                'kind' => $data['kind'],
                'number' => Sequence::next($company->id, 'money.'.$direction),
                'date' => $date,
                'cash_account_id' => $cash->id,
                'counter_account_id' => $counter->id,
                'amount' => $amount,
                'currency' => $currency,
                'rate' => $rate,
                'base_amount' => Dec::mul($amount, $rate),
                'description' => $data['description'] ?? null,
                'reference' => $data['reference'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
                'idempotency_key' => $key,
                'created_by' => auth('web')->id(),
            ]);
            $movement->company_id = $company->id;
            $movement->save();

            $in = $direction === MoneyMovement::IN;
            $text = $movement->description ?: __($kind['label']).' · '.$counter->name;
            $entry = Ledger::post($company, [
                'date' => $date,
                'description' => $text,
                'reference' => $movement->code(),
                'source' => $movement,
                'lines' => [
                    ['account' => $in ? $cash : $counter, 'debit' => $amount, 'currency' => $currency, 'rate' => $rate, 'description' => $text],
                    ['account' => $in ? $counter : $cash, 'credit' => $amount, 'currency' => $currency, 'rate' => $rate, 'description' => $text],
                ],
            ]);

            $movement->update(['journal_entry_id' => $entry->id]);

            return $movement;
        });
    }

    /** Cancel a movement: its journal entry is reversed and the movement is kept, marked void. */
    public static function void(MoneyMovement $movement, ?string $reason = null): MoneyMovement
    {
        return DB::transaction(function () use ($movement, $reason) {
            $movement = MoneyMovement::withoutGlobalScope('company')->lockForUpdate()->findOrFail($movement->id);
            if ($movement->isVoid()) {
                throw new UserError(__('This was already cancelled.'));
            }

            Ledger::reverse($movement->entry()->withoutGlobalScope('company')->firstOrFail(), null, $reason);
            $movement->update(['status' => MoneyMovement::VOID]);
            Invoices::release($movement); // invoices it settled are due again

            return $movement;
        });
    }
}
