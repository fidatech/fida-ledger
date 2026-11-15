<?php

namespace App\Accounting;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Company;
use App\Models\ExchangeRate;
use App\Models\Sequence;
use App\Models\Transfer;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;

/**
 * Transfers between the company's own cash and bank accounts, and currency exchange.
 *
 * Same currency: debit the receiving account, credit the paying account. No income, no expense.
 *
 * Different currencies (an exchange): the currency given leaves the books at what it is carried
 * at (the weighted average cost of that account in the base currency), the currency received
 * comes in at its value in the base currency, and the difference is the realized exchange gain
 * or loss. See docs/ARCHITECTURE.md, "Currency exchange".
 */
class Transfers
{
    /**
     * @param  array{date: string, from_account_id: int, to_account_id: int, from_amount: mixed, to_amount?: mixed,
     *     to_rate?: mixed, description?: ?string, idempotency_key?: ?string}  $data
     *
     * 'to_rate' is the value of one unit of the currency received in the base currency. It is only
     * needed when neither side of an exchange is the base currency and no rate is saved for that day.
     */
    public static function record(Company $company, array $data): Transfer
    {
        return DB::transaction(function () use ($company, $data) {
            $transfers = Transfer::withoutGlobalScope('company')->where('company_id', $company->id);
            $key = $data['idempotency_key'] ?? null;
            if ($key !== null && ($existing = (clone $transfers)->where('idempotency_key', $key)->first())) {
                return $existing;
            }

            // the paying account is locked: its carrying value must not change while this is worked out
            $accounts = Account::withoutGlobalScope('company')->where('company_id', $company->id)->cashOrBank()->postable();
            $from = (clone $accounts)->lockForUpdate()->find($data['from_account_id']);
            $to = (clone $accounts)->find($data['to_account_id']);
            if (! $from || ! $to) {
                throw new UserError(__('Choose two cash or bank accounts.'));
            }
            if ($from->id === $to->id) {
                throw new UserError(__('Choose two different accounts.'));
            }

            $base = $company->base_currency;
            $date = substr((string) $data['date'], 0, 10);
            $fromCurrency = $from->currency ?? $base;
            $toCurrency = $to->currency ?? $base;
            $exchange = $fromCurrency !== $toCurrency;

            $fromAmount = Dec::round($data['from_amount']);
            $toAmount = $exchange ? Dec::round($data['to_amount'] ?? '0') : $fromAmount;
            if (! Dec::isPositive($fromAmount) || ! Dec::isPositive($toAmount)) {
                throw new UserError(__('The amounts must be more than zero.'));
            }

            // what the money given is carried at, and what the money received is worth, in the base currency
            $fromBase = self::carryingValue($company, $from, $fromCurrency, $fromAmount, $date);
            if (! $exchange) {
                $toBase = $fromBase;
            } elseif ($toCurrency === $base) {
                $toBase = $toAmount;
            } elseif ($fromCurrency === $base) {
                $toBase = $fromAmount; // bought with base currency: it comes in at what was paid
            } else {
                $toRate = Dec::valid($data['to_rate'] ?? null) && Dec::isPositive($data['to_rate']) ? $data['to_rate'] : ExchangeRate::rateOn($company, $toCurrency, $date);
                if ($toRate === null || ! Dec::isPositive($toRate)) {
                    throw new UserError(__('Enter the exchange rate of :currency.', ['currency' => $toCurrency]));
                }
                $toBase = Dec::mul($toAmount, $toRate);
            }
            $difference = Dec::sub($toBase, $fromBase); // more came in than went out: a gain

            $transfer = new Transfer([
                'type' => $exchange ? Transfer::EXCHANGE : Transfer::TRANSFER,
                'number' => Sequence::next($company->id, 'transfer'),
                'date' => $date,
                'from_account_id' => $from->id,
                'to_account_id' => $to->id,
                'from_amount' => $fromAmount,
                'from_currency' => $fromCurrency,
                'to_amount' => $toAmount,
                'to_currency' => $toCurrency,
                'rate' => Dec::div($toAmount, $fromAmount, (int) config('ledger.rate_scale')),
                'exchange_difference' => $difference,
                'description' => $data['description'] ?? null,
                'idempotency_key' => $key,
                'created_by' => auth('web')->id(),
            ]);
            $transfer->company_id = $company->id;
            $transfer->save();

            $text = $transfer->description ?: ($exchange ? __('Currency exchange') : __('Transfer')).' · '.$from->name.' → '.$to->name;
            $lines = [
                ['account' => $to, 'debit' => $toAmount, 'currency' => $toCurrency, 'base' => $toBase, 'rate' => Dec::div($toBase, $toAmount, (int) config('ledger.rate_scale')), 'description' => $text],
                ['account' => $from, 'credit' => $fromAmount, 'currency' => $fromCurrency, 'base' => $fromBase, 'rate' => Dec::div($fromBase, $fromAmount, (int) config('ledger.rate_scale')), 'description' => $text],
            ];
            if (! Dec::isZero($difference)) {
                $lines[] = [
                    'account' => Account::system($company->id, 'exchange_difference'),
                    Dec::isPositive($difference) ? 'credit' : 'debit' => Dec::abs($difference),
                    'currency' => $base,
                    'description' => $text,
                ];
            }

            $entry = Ledger::post($company, [
                'date' => $date,
                'description' => $text,
                'reference' => $transfer->code(),
                'source' => $transfer,
                'lines' => $lines,
            ]);
            $transfer->update(['journal_entry_id' => $entry->id]);

            return $transfer;
        });
    }

    /**
     * What an amount of a cash account is carried at in the base currency: its share of the
     * account's base value (weighted average). When the account does not hold that much
     * (it would go negative) the saved rate of the day is used instead.
     */
    private static function carryingValue(Company $company, Account $account, string $currency, string $amount, string $date): string
    {
        if ($currency === $company->base_currency) {
            return $amount;
        }

        $held = Balances::forAccounts($company->id, [$account->id])->get($account->id);
        $balance = $held['currencies'][$currency] ?? '0';
        if (Dec::cmp($balance, $amount) >= 0 && Dec::isPositive($held['base'] ?? '0')) {
            return Dec::div(Dec::mul($held['base'], $amount, 12), $balance);
        }

        $rate = ExchangeRate::rateOn($company, $currency, $date);
        if ($rate === null || ! Dec::isPositive($rate)) {
            throw new UserError(__(':account does not hold this much. Enter today\'s rate of :currency under Exchange Rates first.', ['account' => $account->name, 'currency' => $currency]));
        }

        return Dec::mul($amount, $rate);
    }
}
