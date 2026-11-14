<?php

namespace App\Http\Controllers\Payroll;

use App\Accounting\Payroll;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Payroll runs, payslips and salary advances. Every screen here needs a payroll permission: salaries are private. */
class PayrollController extends Controller
{
    public function index(): View
    {
        return view('payroll.index', [
            'runs' => PayrollRun::withCount('lines')->orderByDesc('period')->paginate(per_page(24)),
            'period' => substr(today_iso(), 0, 7),
            'employees' => Employee::where('is_active', true)->count(),
        ]);
    }

    public function prepare(Request $request): RedirectResponse
    {
        $data = $request->validate(['period' => ['required', 'date_format:Y-m']]);
        $run = Payroll::prepare(company(), $data['period']);

        return redirect()->route('payroll.show', ['id' => $run->id])->with('success', __('Payroll of :period prepared. Check it, then approve.', ['period' => $run->label()]));
    }

    public function show(Request $request): View
    {
        return view('payroll.show', [
            'run' => PayrollRun::with('lines.employee.department')->findOrFail($request->route('id')),
            'cashAccounts' => Account::postable()->cashOrBank()->where('currency', base_currency())->orderBy('name')->get(),
        ]);
    }

    /** save (the lines of a draft), approve, pay or discard. */
    public function act(Request $request): RedirectResponse
    {
        $run = PayrollRun::findOrFail($request->route('id'));
        $step = $request->route('step');
        $data = $request->validate([
            'lines' => ['nullable', 'array'],
            'lines.*.allowances' => ['nullable', 'numeric', 'min:0'],
            'lines.*.deductions' => ['nullable', 'numeric', 'min:0'],
            'lines.*.advance' => ['nullable', 'numeric', 'min:0'],
            'account_id' => ['required_if:step,pay', 'nullable', 'integer', own('accounts')],
            'date' => ['nullable', 'date'],
        ]);
        // empty boxes mean "leave as it is"
        $changes = array_map(fn (array $line) => array_filter($line, fn ($value) => $value !== null && $value !== ''), $data['lines'] ?? []);

        if ($step === 'discard') {
            Payroll::discard($run);

            return redirect()->route('payroll.index')->with('success', __('Draft removed.'));
        }
        if (in_array($step, ['save', 'approve'], true) && $run->status === 'draft' && $changes) {
            Payroll::adjust($run, $changes);
        }
        if ($step === 'approve') {
            Payroll::approve($run);
        }
        if ($step === 'pay') {
            if (empty($data['account_id'])) {
                return back()->with('danger', __('Choose the account the salaries are paid from.'));
            }
            Payroll::pay($run, (int) $data['account_id'], $data['date'] ?? today_iso());
        }
        if ($step !== 'save') {
            AuditLog::record('payroll '.$step, $run->period);
        }

        return back()->with('success', __('Payroll saved.'));
    }

    /** One employee's payslip for a month. */
    public function payslip(Request $request): View
    {
        return view('payroll.payslip', ['line' => PayrollLine::with('employee.department', 'run')->findOrFail($request->route('id'))]);
    }

    public function advances(): View
    {
        return view('payroll.advances', [
            'advances' => SalaryAdvance::with('employee')->orderByDesc('date')->orderByDesc('id')->paginate(per_page(25)),
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(),
            'cashAccounts' => Account::postable()->cashOrBank()->where('currency', base_currency())->orderBy('name')->get(),
        ]);
    }

    public function advance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', own('employees')],
            'amount' => ['required', 'numeric', 'gt:0'],
            'account_id' => ['required', 'integer', own('accounts')],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        Payroll::advance(company(), Employee::findOrFail($data['employee_id']), $data['amount'], (int) $data['account_id'], $data['date'], $data['notes'] ?? null);

        return back()->with('success', __('Advance recorded.'));
    }
}
