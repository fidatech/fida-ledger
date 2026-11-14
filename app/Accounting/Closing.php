<?php

namespace App\Accounting;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use App\Support\Dec;

/**
 * Year-end closing. The result of the year (income minus expenses) is moved to "Retained
 * earnings" by one entry dated the last day of the year, and the books are locked up to that day.
 *
 * The entry is marked 'closing'. Profit & loss for a period leaves such entries out, so the
 * closed year still shows its result; the balance sheet keeps them in, so equity is right.
 */
class Closing
{
    public static function close(Company $company, string $date): JournalEntry
    {
        return DB::transaction(function () use ($company, $date) {
            $company = Company::lockForUpdate()->findOrFail($company->id);
            if ($date > $company->today()) {
                throw new UserError(__('A year can be closed only after it has ended.'));
            }
            if ($company->isLocked($date)) {
                throw new UserError(__('The books are already closed up to :date.', ['date' => fmt_date($company->locked_until)]));
            }

            // what stands on every income and expense account on that day, earlier closings included
            $balances = Balances::baseByAccount($company->id, null, $date);
            $accounts = Account::withoutGlobalScope('company')->where('company_id', $company->id)->whereIn('class', ['income', 'expense'])->whereIn('id', $balances->keys())->get();

            $lines = [];
            $result = '0'; // debit minus credit of the accounts being emptied: negative is a profit
            foreach ($accounts as $account) {
                $balance = $balances[$account->id];
                $lines[] = ['account' => $account, Dec::isPositive($balance) ? 'credit' : 'debit' => Dec::abs($balance)];
                $result = Dec::add($result, $balance);
            }
            if (! $lines || Dec::isZero($result) && count($lines) < 2) {
                throw new UserError(__('There is no income or expense to close up to this day.'));
            }
            if (! Dec::isZero($result)) {
                // a profit (credit balance overall) is credited to retained earnings; a loss is debited
                $lines[] = ['account' => Account::system($company->id, 'retained_earnings'), Dec::isNegative($result) ? 'credit' : 'debit' => Dec::abs($result)];
            }

            $entry = Ledger::post($company, [
                'date' => $date,
                'description' => __('Year-end closing: result moved to retained earnings'),
                'reference' => 'CLOSE-'.$date,
                'source_type' => 'closing',
                'idempotency_key' => 'closing-'.$date,
                'lines' => $lines,
            ]);
            $company->update(['locked_until' => $date]);

            return $entry;
        });
    }
}
