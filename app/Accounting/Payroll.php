<?php

namespace App\Accounting;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use App\Support\Dec;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Payroll, in the base currency. Earning a salary and paying it are two events:
 *
 *   Advance    debit Employee advances, credit cash or bank.
 *   Approve    debit Salaries (basic + allowances); credit Payroll liabilities (net to pay),
 *              Payroll deductions payable (withheld) and Employee advances (recovered).
 *   Pay        debit Payroll liabilities, credit cash or bank. No expense here: it was
 *              recognized on approval, so a salary is never counted twice.
 *
 * Deductions are amounts the company sets per employee. No country's tax rules are built in.
 * There is one payroll per month (a unique key), and each step can only happen once.
 */
class Payroll
{
    public static function advance(Company $company, Employee $employee, mixed $amount, int $paidFromAccountId, string $date, ?string $notes = null): SalaryAdvance
    {
        return DB::transaction(function () use ($company, $employee, $amount, $paidFromAccountId, $date, $notes) {
            $employee = Employee::withoutGlobalScope('company')->where('company_id', $company->id)->lockForUpdate()->findOrFail($employee->id);
            $amount = Dec::round($amount);
            if (! Dec::isPositive($amount)) {
                throw new UserError(__('The amount must be more than zero.'));
            }
            $cash = self::cash($company, $paidFromAccountId);

            $entry = Ledger::post($company, ['date' => $date, 'description' => __('Salary advance').' · '.$employee->name, 'source' => $employee, 'lines' => [
                ['account' => Account::system($company->id, 'employee_advances'), 'debit' => $amount],
                ['account' => $cash, 'credit' => $amount],
            ]]);
            $advance = new SalaryAdvance(['employee_id' => $employee->id, 'date' => substr($date, 0, 10), 'amount' => $amount, 'paid_from_account_id' => $cash->id, 'journal_entry_id' => $entry->id, 'notes' => $notes]);
            $advance->company_id = $company->id;
            $advance->save();
            $employee->update(['advance_balance' => Dec::add($employee->advance_balance, $amount)]);

            return $advance;
        });
    }

    /** Prepare the payroll of a month ("2026-11") as a draft: one line per active employee, from his salary structure. */
    public static function prepare(Company $company, string $period): PayrollRun
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new UserError(__('Choose a month.'));
        }

        try {
            return DB::transaction(function () use ($company, $period) {
                $run = new PayrollRun(['period' => $period, 'status' => 'draft']);
                $run->company_id = $company->id;
                $run->save();

                $monthEnd = date('Y-m-t', strtotime($period.'-01'));
                $employees = Employee::withoutGlobalScope('company')->where('company_id', $company->id)->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('joined_on')->orWhere('joined_on', '<=', $monthEnd))->orderBy('name')->get();
                if ($employees->isEmpty()) {
                    throw new UserError(__('There are no active employees to pay.'));
                }
                foreach ($employees as $employee) {
                    $line = new PayrollLine(['payroll_run_id' => $run->id, 'employee_id' => $employee->id] + self::amounts($employee->basic_salary, $employee->allowances, $employee->deductions, $employee->advance_balance));
                    $line->company_id = $company->id;
                    $line->save();
                }
                self::total($run);

                return $run;
            });
        } catch (UniqueConstraintViolationException) {
            throw new UserError(__('The payroll of this month already exists.'));
        }
    }

    /**
     * Change the lines of a draft: [line id => ['allowances' => .., 'deductions' => .., 'advance' => ..]].
     * The advance recovered can never be more than the employee still owes or than his pay.
     */
    public static function adjust(PayrollRun $run, array $changes): PayrollRun
    {
        return DB::transaction(function () use ($run, $changes) {
            $run = self::locked($run, 'draft');
            foreach ($run->lines()->withoutGlobalScope('company')->get() as $line) {
                if (! isset($changes[$line->id])) {
                    continue;
                }
                $owed = Employee::withoutGlobalScope('company')->whereKey($line->employee_id)->value('advance_balance');
                $change = $changes[$line->id];
                $wanted = $change['advance'] ?? $line->advance;
                $line->update(self::amounts($line->basic, $change['allowances'] ?? $line->allowances, $change['deductions'] ?? $line->deductions, Dec::cmp($wanted, $owed) < 0 ? $wanted : $owed));
            }
            self::total($run);

            return $run;
        });
    }

    /** Approve: the salary expense and what is owed are posted. From here on the lines are fixed. */
    public static function approve(PayrollRun $run): PayrollRun
    {
        return DB::transaction(function () use ($run) {
            $run = PayrollRun::withoutGlobalScope('company')->lockForUpdate()->findOrFail($run->id);
            if ($run->status !== 'draft') {
                return $run; // approved once
            }
            $company = Company::findOrFail($run->company_id);
            self::total($run);
            if (! Dec::isPositive($run->gross)) {
                throw new UserError(__('This payroll has nothing to pay.'));
            }

            $lines = [['account' => Account::system($company->id, 'salaries'), 'debit' => $run->gross]];
            foreach ([['payroll_liabilities', $run->net], ['payroll_deductions', $run->deductions], ['employee_advances', $run->advances]] as [$key, $amount]) {
                if (Dec::isPositive($amount)) {
                    $lines[] = ['account' => Account::system($company->id, $key), 'credit' => $amount];
                }
            }
            $entry = Ledger::post($company, ['date' => date('Y-m-t', strtotime($run->period.'-01')), 'description' => __('Payroll').' · '.$run->label(), 'reference' => 'PAY-'.$run->period, 'source' => $run, 'idempotency_key' => 'payroll-accrual-'.$run->id, 'lines' => $lines]);

            // the advances recovered come off what each employee owes
            foreach ($run->lines()->withoutGlobalScope('company')->get() as $line) {
                if (Dec::isPositive($line->advance)) {
                    $employee = Employee::withoutGlobalScope('company')->lockForUpdate()->findOrFail($line->employee_id);
                    $employee->update(['advance_balance' => Dec::sub($employee->advance_balance, $line->advance)]);
                }
            }
            $run->update(['status' => 'approved', 'accrual_entry_id' => $entry->id, 'approved_by' => auth('web')->id()]);

            return $run;
        });
    }

    /** Pay the net salaries of an approved payroll from a cash or bank account. Happens once. */
    public static function pay(PayrollRun $run, int $paidFromAccountId, string $date): PayrollRun
    {
        return DB::transaction(function () use ($run, $paidFromAccountId, $date) {
            $run = PayrollRun::withoutGlobalScope('company')->lockForUpdate()->findOrFail($run->id);
            if ($run->status === 'paid') {
                return $run; // paid once
            }
            if ($run->status !== 'approved') {
                throw new UserError(__('Approve the payroll before paying it.'));
            }
            $company = Company::findOrFail($run->company_id);
            $entry = Ledger::post($company, ['date' => $date, 'description' => __('Salaries paid').' · '.$run->label(), 'reference' => 'PAY-'.$run->period, 'source' => $run, 'idempotency_key' => 'payroll-payment-'.$run->id, 'lines' => [
                ['account' => Account::system($company->id, 'payroll_liabilities'), 'debit' => $run->net],
                ['account' => self::cash($company, $paidFromAccountId), 'credit' => $run->net],
            ]]);
            $run->update(['status' => 'paid', 'payment_entry_id' => $entry->id]);

            return $run;
        });
    }

    /** A draft that will not be used can be removed; an approved payroll is in the ledger and stays. */
    public static function discard(PayrollRun $run): void
    {
        DB::transaction(function () use ($run) {
            $run = self::locked($run, 'draft');
            PayrollLine::withoutGlobalScope('company')->where('payroll_run_id', $run->id)->delete();
            $run->delete();
        });
    }

    /** @return array{basic: string, allowances: string, deductions: string, advance: string, net: string} */
    private static function amounts(mixed $basic, mixed $allowances, mixed $deductions, mixed $advance): array
    {
        $gross = Dec::add($basic, $allowances);
        $deductions = Dec::round($deductions);
        if (Dec::isNegative($allowances) || Dec::isNegative($deductions) || Dec::cmp($deductions, $gross) > 0) {
            throw new UserError(__('Deductions cannot be more than the salary.'));
        }
        $afterDeductions = Dec::sub($gross, $deductions);
        // an advance is recovered only as far as the pay allows
        $advance = Dec::isNegative($advance) ? '0' : (Dec::cmp($advance, $afterDeductions) < 0 ? Dec::round($advance) : $afterDeductions);

        return ['basic' => Dec::round($basic), 'allowances' => Dec::round($allowances), 'deductions' => $deductions, 'advance' => $advance, 'net' => Dec::sub($afterDeductions, $advance)];
    }

    private static function total(PayrollRun $run): void
    {
        $lines = $run->lines()->withoutGlobalScope('company')->get();
        $run->update([
            'gross' => Dec::add(Dec::sum($lines->pluck('basic')), Dec::sum($lines->pluck('allowances'))),
            'deductions' => Dec::sum($lines->pluck('deductions')),
            'advances' => Dec::sum($lines->pluck('advance')),
            'net' => Dec::sum($lines->pluck('net')),
        ]);
    }

    private static function locked(PayrollRun $run, string $status): PayrollRun
    {
        $run = PayrollRun::withoutGlobalScope('company')->lockForUpdate()->findOrFail($run->id);
        if ($run->status !== $status) {
            throw new UserError(__('This payroll is already approved and cannot be changed.'));
        }

        return $run;
    }

    private static function cash(Company $company, int $accountId): Account
    {
        $account = Account::withoutGlobalScope('company')->where('company_id', $company->id)->postable()->cashOrBank()->where('currency', $company->base_currency)->find($accountId);
        if (! $account) {
            throw new UserError(__('Choose a cash or bank account in :currency.', ['currency' => $company->base_currency]));
        }

        return $account;
    }
}
