<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalLine;
use App\Support\Dec;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Balances are always read from the journal lines: there is no cached balance that could
 * disagree with the ledger. All sums are done by the database on DECIMAL columns.
 *
 * A balance here is "debit minus credit". natural() turns it into the sign people expect
 * (a supplier we owe 500 shows 500, not -500).
 */
class Balances
{
    /** Balance of one account in each currency it was posted in: ['USD' => '120.0000', ...]. */
    public static function byCurrency(Account $account, ?string $until = null): array
    {
        return self::lines($account->company_id)
            ->where('account_id', $account->id)
            ->when($until, fn (Builder $query) => $query->where('date', '<=', $until))
            ->groupBy('currency')
            ->orderBy('currency')
            ->selectRaw('currency, SUM(debit) - SUM(credit) AS balance')
            ->pluck('balance', 'currency')
            ->map(fn ($balance) => Dec::round($balance))
            ->all();
    }

    /**
     * Balances of many accounts at once.
     *
     * @return Collection<int, array{currencies: array<string, string>, base: string}> keyed by account id
     */
    public static function forAccounts(int $companyId, ?array $accountIds = null, ?string $until = null): Collection
    {
        $rows = self::lines($companyId)
            ->when($accountIds !== null, fn (Builder $query) => $query->whereIn('account_id', $accountIds))
            ->when($until, fn (Builder $query) => $query->where('date', '<=', $until))
            ->groupBy('account_id', 'currency')
            ->selectRaw('account_id, currency, SUM(debit) - SUM(credit) AS balance, SUM(base_debit) - SUM(base_credit) AS base_balance')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->account_id] ??= ['currencies' => [], 'base' => '0'];
            if (! Dec::isZero($row->balance)) {
                $out[$row->account_id]['currencies'][$row->currency] = Dec::round($row->balance);
            }
            $out[$row->account_id]['base'] = Dec::add($out[$row->account_id]['base'], $row->base_balance);
        }

        return collect($out);
    }

    /**
     * Totals of the accounts of some kinds (all customers, all cash and bank ...).
     *
     * @return array{currencies: array<string, string>, base: string}
     */
    public static function ofKinds(Company $company, array $kinds, ?string $until = null): array
    {
        $rows = self::lines($company->id)
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->whereIn('accounts.kind', $kinds)
            ->when($until, fn (Builder $query) => $query->where('journal_lines.date', '<=', $until))
            ->groupBy('journal_lines.currency')
            ->orderBy('journal_lines.currency')
            ->selectRaw('journal_lines.currency AS currency, SUM(journal_lines.debit) - SUM(journal_lines.credit) AS balance, SUM(journal_lines.base_debit) - SUM(journal_lines.base_credit) AS base_balance')
            ->get();

        $currencies = [];
        $base = '0';
        foreach ($rows as $row) {
            if (! Dec::isZero($row->balance)) {
                $currencies[$row->currency] = Dec::round($row->balance);
            }
            $base = Dec::add($base, $row->base_balance);
        }

        return ['currencies' => $currencies, 'base' => $base];
    }

    /**
     * Movement of every class of account in the base currency between two days (either may be null).
     *
     * @return array<string, string> class => debit minus credit
     */
    public static function byClass(Company $company, ?string $from, ?string $until): array
    {
        return self::lines($company->id)
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->when($from, fn (Builder $query) => $query->where('journal_lines.date', '>=', $from))
            ->when($until, fn (Builder $query) => $query->where('journal_lines.date', '<=', $until))
            ->groupBy('accounts.class')
            ->selectRaw('accounts.class AS class, SUM(journal_lines.base_debit) - SUM(journal_lines.base_credit) AS balance')
            ->pluck('balance', 'class')
            ->map(fn ($balance) => Dec::round($balance))
            ->all();
    }

    /**
     * Movement of every account in the base currency between two days (either may be null),
     * debit minus credit. Accounts that did not move are left out.
     *
     * @return Collection<int, string> keyed by account id
     */
    public static function baseByAccount(int $companyId, ?string $from, ?string $until): Collection
    {
        return self::lines($companyId)
            ->when($from, fn (Builder $query) => $query->where('date', '>=', $from))
            ->when($until, fn (Builder $query) => $query->where('date', '<=', $until))
            ->groupBy('account_id')
            ->selectRaw('account_id, SUM(base_debit) - SUM(base_credit) AS balance')
            ->pluck('balance', 'account_id')
            ->map(fn ($balance) => Dec::round($balance))
            ->reject(fn (string $balance) => Dec::isZero($balance));
    }

    /** Income minus expenses in the base currency for a period. */
    public static function profit(Company $company, ?string $from, ?string $until): string
    {
        $classes = self::byClass($company, $from, $until);

        // income is a credit balance (negative here), expenses a debit balance
        return Dec::sub(Dec::neg($classes['income'] ?? '0'), $classes['expense'] ?? '0');
    }

    /** The balance as people read it for an account of this class. */
    public static function natural(string $class, mixed $debitMinusCredit): string
    {
        return config('ledger.classes.'.$class) === 'credit' ? Dec::neg($debitMinusCredit) : Dec::round($debitMinusCredit);
    }

    private static function lines(int $companyId): Builder
    {
        // a plain query with the company named explicitly: it does not depend on who is signed in
        return DB::table((new JournalLine)->getTable())->where('journal_lines.company_id', $companyId);
    }
}
