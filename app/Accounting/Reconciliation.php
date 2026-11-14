<?php

namespace App\Accounting;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\BankStatementLine;
use App\Models\JournalLine;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;

/**
 * Bank reconciliation: the bank's statement on one side, the ledger lines of the same cash or
 * bank account on the other. Matching only links the two; it never posts or changes an entry.
 * What stays unmatched is the difference to explain: money the bank shows that the books do
 * not (record it with Money In / Out), or entries the bank has not shown yet.
 */
class Reconciliation
{
    /** How far apart the bank's date and the books' date may be for an automatic match. */
    private const DAYS = 4;

    /**
     * Read a CSV statement. The first row names the columns; these names are recognized (any case):
     *   date · description, details, narration, particulars · reference, ref, cheque
     *   amount (in positive, out negative)  or  credit, deposit, in  and  debit, withdrawal, out
     * Dates: 2026-11-15, 15/11/2026 or 15-11-2026 (day first).
     *
     * @return array{added: int, skipped: int}
     */
    public static function import(Account $account, string $path): array
    {
        $handle = fopen($path, 'r');
        $header = $handle ? fgetcsv($handle) : false;
        if (! $header) {
            throw new UserError(__('The file is empty or is not a CSV file.'));
        }
        $header = array_map(fn ($name) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $name))), $header);
        $find = function (array $names) use ($header) {
            foreach ($header as $index => $name) {
                if (in_array($name, $names, true)) {
                    return $index;
                }
            }

            return null;
        };
        $columns = [
            'date' => $find(['date', 'transaction date', 'value date', 'posting date']),
            'description' => $find(['description', 'details', 'narration', 'particulars', 'memo']),
            'reference' => $find(['reference', 'ref', 'cheque', 'cheque no', 'transaction id']),
            'amount' => $find(['amount']),
            'in' => $find(['credit', 'deposit', 'deposits', 'in', 'money in']),
            'out' => $find(['debit', 'withdrawal', 'withdrawals', 'out', 'money out']),
        ];
        if ($columns['date'] === null || ($columns['amount'] === null && $columns['in'] === null && $columns['out'] === null)) {
            throw new UserError(__('The file needs a "date" column and an "amount" column (or "credit" and "debit" columns).'));
        }

        $rows = [];
        $seen = [];
        $number = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $number++;
            if ($row === [null] || trim(implode('', $row)) === '') {
                continue;
            }
            $date = self::date((string) ($row[$columns['date']] ?? ''));
            $clean = fn (?int $index) => $index === null ? '' : preg_replace('/[^\d.\-]/', '', (string) ($row[$index] ?? ''));
            $amount = $columns['amount'] !== null && $clean($columns['amount']) !== ''
                ? $clean($columns['amount'])
                : Dec::sub($clean($columns['in']) ?: '0', ltrim($clean($columns['out']) ?: '0', '-'));
            if ($date === null || ! Dec::valid($amount)) {
                fclose($handle);
                throw new UserError(__('Row :row cannot be read: check its date and amount.', ['row' => $number]));
            }
            if (Dec::isZero($amount)) {
                continue;
            }
            $description = $columns['description'] !== null ? mb_substr(trim((string) ($row[$columns['description']] ?? '')), 0, 255) : null;
            $reference = $columns['reference'] !== null ? mb_substr(trim((string) ($row[$columns['reference']] ?? '')), 0, 100) : null;
            // two identical lines in one file are two real transactions: the count keeps them apart
            $base = $date.'|'.Dec::round($amount).'|'.$description.'|'.$reference;
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $rows[] = ['date' => $date, 'description' => $description ?: null, 'reference' => $reference ?: null, 'amount' => Dec::round($amount), 'fingerprint' => sha1($base.'|'.$seen[$base])];
        }
        fclose($handle);
        if (! $rows) {
            throw new UserError(__('The file has no transactions.'));
        }

        return DB::transaction(function () use ($account, $rows) {
            $known = BankStatementLine::withoutGlobalScope('company')->where('account_id', $account->id)->whereIn('fingerprint', array_column($rows, 'fingerprint'))->pluck('fingerprint')->flip();
            $added = 0;
            foreach ($rows as $row) {
                if ($known->has($row['fingerprint'])) {
                    continue;
                }
                $line = new BankStatementLine($row + ['account_id' => $account->id]);
                $line->company_id = $account->company_id;
                $line->save();
                $added++;
            }

            return ['added' => $added, 'skipped' => count($rows) - $added];
        });
    }

    /**
     * Match every statement line that has exactly one free ledger line of the same amount within a few days.
     * Anything with two or more candidates is left for a person to decide.
     */
    public static function autoMatch(Account $account): int
    {
        return DB::transaction(function () use ($account) {
            $matched = 0;
            foreach (BankStatementLine::withoutGlobalScope('company')->where('account_id', $account->id)->whereNull('journal_line_id')->orderBy('date')->orderBy('id')->lockForUpdate()->get() as $line) {
                $candidates = self::candidates($account, $line)->limit(2)->get();
                if ($candidates->count() === 1) {
                    self::link($line, $candidates->first());
                    $matched++;
                }
            }

            return $matched;
        });
    }

    public static function match(BankStatementLine $line, JournalLine $ledgerLine): void
    {
        DB::transaction(function () use ($line, $ledgerLine) {
            $line = BankStatementLine::withoutGlobalScope('company')->lockForUpdate()->findOrFail($line->id);
            if ($line->journal_line_id !== null) {
                throw new UserError(__('This statement line is already matched.'));
            }
            if ((int) $ledgerLine->account_id !== (int) $line->account_id || Dec::cmp(Dec::sub($ledgerLine->debit, $ledgerLine->credit), $line->amount) !== 0) {
                throw new UserError(__('The entry and the statement line must be on the same account and for the same amount.'));
            }
            if (BankStatementLine::withoutGlobalScope('company')->where('journal_line_id', $ledgerLine->id)->exists()) {
                throw new UserError(__('This entry is already matched to another statement line.'));
            }
            self::link($line, $ledgerLine);
        });
    }

    public static function unmatch(BankStatementLine $line): void
    {
        $line->update(['journal_line_id' => null, 'matched_at' => null, 'matched_by' => null]);
    }

    /** Free ledger lines of the account with the amount of a statement line, oldest first. */
    public static function candidates(Account $account, BankStatementLine $line, ?int $days = self::DAYS)
    {
        $date = $line->date->toDateString();

        return JournalLine::withoutGlobalScope('company')->where('journal_lines.account_id', $account->id)
            ->whereRaw('journal_lines.debit - journal_lines.credit = CAST(? AS DECIMAL(20,4))', [$line->amount])
            ->when($days !== null, fn ($q) => $q->whereBetween('journal_lines.date', [date('Y-m-d', strtotime($date.' -'.$days.' days')), date('Y-m-d', strtotime($date.' +'.$days.' days'))]))
            ->whereNotIn('journal_lines.id', BankStatementLine::withoutGlobalScope('company')->where('account_id', $account->id)->whereNotNull('journal_line_id')->select('journal_line_id'))
            ->orderBy('journal_lines.date')->orderBy('journal_lines.id');
    }

    private static function link(BankStatementLine $line, JournalLine $ledgerLine): void
    {
        $line->update(['journal_line_id' => $ledgerLine->id, 'matched_at' => now(), 'matched_by' => auth('web')->id()]);
    }

    private static function date(string $text): ?string
    {
        $text = trim($text);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $text, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/', $text, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }
}
