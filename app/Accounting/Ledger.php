<?php

namespace App\Accounting;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Sequence;
use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The one door into the general ledger. Every module (Money In / Out, transfers, invoices,
 * purchasing, POS, payroll ...) posts through here, so there is one set of books.
 *
 * An entry is accepted only when:
 *   - its date is not inside a locked period,
 *   - every line is a debit or a credit (not both, not neither) on a postable account of the company,
 *   - a cash or bank account is posted in its own currency,
 *   - debits equal credits in the base currency, to the last decimal.
 * Posted entries are never changed: reverse() adds a mirror entry.
 */
class Ledger
{
    /**
     * @param  array{
     *     date: string,
     *     description?: ?string,
     *     reference?: ?string,
     *     source?: ?Model,
     *     source_type?: ?string,
     *     idempotency_key?: ?string,
     *     reversal_of_id?: ?int,
     *     lines: list<array{account: Account|int, debit?: mixed, credit?: mixed, currency?: ?string, rate?: mixed, base?: mixed, description?: ?string}>
     * }  $data
     *
     * Each line gives an amount in its own currency. Its value in the base currency is 'base'
     * when given (used where the value is known exactly, as in an exchange), else amount x rate.
     */
    public static function post(Company $company, array $data): JournalEntry
    {
        return DB::transaction(function () use ($company, $data) {
            $key = $data['idempotency_key'] ?? null;
            if ($key !== null) {
                $existing = self::entries($company)->where('idempotency_key', $key)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $date = substr((string) $data['date'], 0, 10);
            if ($company->isLocked($date)) {
                throw new UserError(__('The books are closed up to :date. Choose a later date.', ['date' => fmt_date($company->locked_until)]));
            }

            $lines = self::prepare($company, $data['lines'] ?? []);

            $entry = new JournalEntry([
                'number' => Sequence::next($company->id, 'journal'),
                'date' => $date,
                'description' => $data['description'] ?? null,
                'reference' => $data['reference'] ?? null,
                // a document, or a plain word for entries that have none ('closing': a year-end closing entry)
                'source_type' => isset($data['source']) ? $data['source']->getMorphClass() : ($data['source_type'] ?? null),
                'source_id' => isset($data['source']) ? $data['source']->getKey() : null,
                'idempotency_key' => $key,
                'reversal_of_id' => $data['reversal_of_id'] ?? null,
                'created_by' => auth('web')->id(),
            ]);
            $entry->company_id = $company->id;
            $entry->save();

            foreach ($lines as $line) {
                $row = new JournalLine($line + ['journal_entry_id' => $entry->id, 'date' => $date]);
                $row->company_id = $company->id;
                $row->save();
            }

            return $entry;
        });
    }

    /** Cancel a posted entry by posting its mirror image. The original stays in the books. */
    public static function reverse(JournalEntry $entry, ?string $date = null, ?string $reason = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $date, $reason) {
            $company = Company::findOrFail($entry->company_id);
            $entry = self::entries($company)->lockForUpdate()->findOrFail($entry->id);

            if ($entry->isReversed()) {
                throw new UserError(__('This entry was already reversed.'));
            }
            if ($entry->reversal_of_id !== null) {
                throw new UserError(__('A reversing entry cannot be reversed.'));
            }

            $lines = [];
            foreach ($entry->lines()->withoutGlobalScope('company')->get() as $line) {
                $debit = Dec::isPositive($line->debit);
                $lines[] = [
                    'account' => (int) $line->account_id,
                    // what was a debit becomes a credit of the same amount and the same base value
                    $debit ? 'credit' : 'debit' => $debit ? $line->debit : $line->credit,
                    'currency' => $line->currency,
                    'rate' => $line->rate,
                    'base' => $debit ? $line->base_debit : $line->base_credit,
                    'description' => $line->description,
                ];
            }

            $reversal = self::post($company, [
                'date' => $date ?? $entry->date->toDateString(),
                'description' => trim(__('Reversal of entry #:number', ['number' => $entry->number]).($reason ? ': '.$reason : '')),
                'reference' => $entry->reference,
                'reversal_of_id' => $entry->id,
                'lines' => $lines,
            ]);

            $entry->update(['reversed_by_id' => $reversal->id]);

            return $reversal;
        });
    }

    /**
     * Check the lines and work out their base values.
     *
     * @return list<array<string, mixed>> rows ready for journal_lines
     */
    private static function prepare(Company $company, array $lines): array
    {
        if (count($lines) < 2) {
            throw new LogicException('A journal entry needs at least two lines.');
        }

        $scale = (int) config('ledger.scale');
        $ids = array_map(fn (array $line) => $line['account'] instanceof Account ? $line['account']->id : (int) $line['account'], $lines);
        $accounts = Account::withoutGlobalScope('company')->where('company_id', $company->id)->whereIn('id', $ids)->get()->keyBy('id');

        $rows = [];
        $debits = '0';
        $credits = '0';
        foreach ($lines as $index => $line) {
            $account = $accounts->get($ids[$index]);
            if (! $account) {
                throw new LogicException('Line '.($index + 1).': the account does not belong to this company.');
            }
            if ($account->is_group) {
                throw new UserError(__(':account is a group of accounts. Choose an account inside it.', ['account' => $account->name]));
            }
            if ($account->is_archived) {
                throw new UserError(__(':account is archived and cannot be used.', ['account' => $account->name]));
            }

            $debit = Dec::round($line['debit'] ?? '0', $scale);
            $credit = Dec::round($line['credit'] ?? '0', $scale);
            if (Dec::isNegative($debit) || Dec::isNegative($credit) || Dec::isZero($debit) === Dec::isZero($credit)) {
                throw new LogicException('Line '.($index + 1).': give a positive debit or a positive credit, not both.');
            }

            $currency = strtoupper((string) ($line['currency'] ?? $account->currency ?? $company->base_currency));
            if ($account->currency !== null && $account->currency !== $currency) {
                throw new UserError(__(':account holds :currency only.', ['account' => $account->name, 'currency' => $account->currency]));
            }

            $amount = Dec::isZero($debit) ? $credit : $debit;
            if ($currency === $company->base_currency) {
                $rate = '1';
                $base = $amount;
            } else {
                $rate = Dec::round($line['rate'] ?? '0', (int) config('ledger.rate_scale'));
                if (isset($line['base'])) {
                    $base = Dec::round($line['base'], $scale);
                } else {
                    if (! Dec::isPositive($rate)) {
                        throw new UserError(__('Enter the exchange rate of :currency.', ['currency' => $currency]));
                    }
                    $base = Dec::mul($amount, $rate, $scale);
                }
                if (Dec::isNegative($base)) {
                    throw new LogicException('Line '.($index + 1).': the base value cannot be negative.');
                }
            }

            $isDebit = ! Dec::isZero($debit);
            $rows[] = [
                'account_id' => $account->id,
                'description' => $line['description'] ?? null,
                'currency' => $currency,
                'debit' => $debit,
                'credit' => $credit,
                'rate' => $rate,
                'base_debit' => $isDebit ? $base : '0',
                'base_credit' => $isDebit ? '0' : $base,
            ];
            $debits = Dec::add($debits, $isDebit ? $base : '0', $scale);
            $credits = Dec::add($credits, $isDebit ? '0' : $base, $scale);
        }

        if (Dec::cmp($debits, $credits) !== 0) {
            throw new LogicException('The entry does not balance: debits '.$debits.', credits '.$credits.' ('.$company->base_currency.').');
        }

        return $rows;
    }

    private static function entries(Company $company)
    {
        return JournalEntry::withoutGlobalScope('company')->where('company_id', $company->id);
    }
}
