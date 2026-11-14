<?php

namespace App\Accounting;

use App\Exceptions\UserError;
use App\Models\Company;
use App\Models\RecurringTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Recurring Money In / Out. Each due date is posted once: the Money In/Out carries the key
 * "recurring-<id>-<date>", so running the job twice, or two servers running it together,
 * cannot post the same date again.
 *
 * Missed dates (the server was down, the subscription had lapsed) follow the rule chosen on
 * the transaction: 'all' posts every missed date on its own date, 'latest' posts only the most
 * recent one and skips the rest. A date inside a locked period is not posted: the error is
 * kept on the transaction and it waits for a person.
 */
class Recurring
{
    /** Never more than this many postings of one transaction in one run (a daily item forgotten for years). */
    private const MAX_PER_RUN = 400;

    /** @return array{posted: int, failed: int} */
    public static function run(Company $company): array
    {
        $posted = 0;
        $failed = 0;
        if (! $company->isUsable()) {
            return compact('posted', 'failed');
        }
        $today = $company->today();

        $due = RecurringTransaction::withoutGlobalScope('company')->where('company_id', $company->id)->where('is_active', true)->where('next_run', '<=', $today)->orderBy('id')->get();
        foreach ($due as $item) {
            try {
                $posted += self::runOne($company, $item, $today);
            } catch (Throwable $e) {
                $failed++;
                // a problem a person can fix (archived account, locked period, missing rate) is shown on the transaction
                $item->update(['last_error' => mb_substr($e instanceof UserError ? $e->getMessage() : __('Could not be posted. Check its accounts.'), 0, 255)]);
                if (! $e instanceof UserError) {
                    report($e);
                }
            }
        }

        return compact('posted', 'failed');
    }

    private static function runOne(Company $company, RecurringTransaction $item, string $today): int
    {
        // every date that is due, oldest first
        $dates = [];
        $next = $item->next_run->copy();
        while ($next->toDateString() <= $today && ($item->end_date === null || $next->lte($item->end_date)) && count($dates) < self::MAX_PER_RUN) {
            $dates[] = $next->toDateString();
            $next = self::after($next, $item->frequency, $item->next_run->day);
        }
        $toPost = $item->missed_policy === 'latest' ? array_slice($dates, -1) : $dates;

        return DB::transaction(function () use ($company, $item, $dates, $toPost, $next) {
            foreach ($toPost as $date) {
                Money::record($company, [
                    'direction' => $item->direction, 'kind' => $item->kind, 'date' => $date,
                    'cash_account_id' => $item->cash_account_id, 'counter_account_id' => $item->counter_account_id, 'amount' => $item->amount,
                    'description' => $item->name, 'reference' => 'REC-'.$item->id, 'idempotency_key' => 'recurring-'.$item->id.'-'.$date,
                ]);
            }
            $ended = $item->end_date !== null && $next->gt($item->end_date);
            $item->update(['next_run' => $next->toDateString(), 'last_run' => $dates ? end($dates) : $item->last_run, 'last_error' => null, 'is_active' => ! $ended]);

            return count($toPost);
        });
    }

    /** The date after this one. A monthly item due on the 31st runs on the last day of a shorter month. */
    public static function after(Carbon $date, string $frequency, int $anchorDay): Carbon
    {
        return match ($frequency) {
            'daily' => $date->copy()->addDay(),
            'weekly' => $date->copy()->addWeek(),
            'yearly' => $date->copy()->addYearNoOverflow(),
            default => (function () use ($date, $anchorDay) {
                $month = $date->copy()->startOfMonth()->addMonth();

                return $month->day(min($anchorDay, $month->daysInMonth));
            })(),
        };
    }
}
