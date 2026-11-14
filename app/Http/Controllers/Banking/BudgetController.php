<?php

namespace App\Http\Controllers\Banking;

use App\Accounting\Balances;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BudgetLine;
use App\Support\Csv;
use App\Support\Dec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Budgets per income and expense account for a year, and the comparison of budget with what really happened. */
class BudgetController extends Controller
{
    /** The year's budget: one amount per account for the whole year, spread evenly over its months. */
    public function edit(Request $request): View
    {
        $year = $this->year($request);
        $totals = BudgetLine::whereBetween('period', [$year.'-01', $year.'-12'])->groupBy('account_id')->selectRaw('account_id, SUM(amount) AS total')->pluck('total', 'account_id');

        return view('banking.budget', [
            'year' => $year,
            'accounts' => Account::postable()->whereIn('class', ['income', 'expense'])->where(fn ($q) => $q->whereNull('system_key')->orWhere('system_key', '!=', 'exchange_difference'))->orderBy('code')->get(),
            'totals' => $totals,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $year = $this->year($request);
        $data = $request->validate(['budget' => ['nullable', 'array'], 'budget.*' => ['nullable', 'numeric', 'min:0']]);
        $allowed = Account::postable()->whereIn('class', ['income', 'expense'])->pluck('id')->all();

        DB::transaction(function () use ($data, $year, $allowed) {
            foreach ($data['budget'] ?? [] as $accountId => $annual) {
                if (! in_array((int) $accountId, $allowed, true)) {
                    continue;
                }
                BudgetLine::where('account_id', $accountId)->whereBetween('period', [$year.'-01', $year.'-12'])->delete();
                if ($annual === null || $annual === '' || Dec::isZero($annual)) {
                    continue;
                }
                // twelve equal months; December takes the cents left by rounding, so the year adds up exactly
                $monthly = Dec::div($annual, '12', 2);
                $spent = '0';
                for ($month = 1; $month <= 12; $month++) {
                    $amount = $month === 12 ? Dec::sub($annual, $spent) : $monthly;
                    $spent = Dec::add($spent, $amount);
                    BudgetLine::create(['account_id' => (int) $accountId, 'period' => sprintf('%d-%02d', $year, $month), 'amount' => $amount]);
                }
            }
        });

        return redirect()->route('banking.budget', ['year' => $year])->with('success', __('Budget of :year saved.', ['year' => $year]));
    }

    /** Budget against actual, for the months chosen. */
    public function comparison(Request $request): View|StreamedResponse
    {
        $year = $this->year($request);
        $fromMonth = min(12, max(1, $request->integer('from_month') ?: 1));
        $toMonth = min(12, max($fromMonth, $request->integer('to_month') ?: (int) date('n', strtotime(today_iso()))));
        $from = sprintf('%d-%02d', $year, $fromMonth);
        $to = sprintf('%d-%02d', $year, $toMonth);

        $budget = BudgetLine::whereBetween('period', [$from, $to])->groupBy('account_id')->selectRaw('account_id, SUM(amount) AS total')->pluck('total', 'account_id');
        // what really happened, before any year-end closing entry
        $actual = Balances::baseByAccount(company_id(), $from.'-01', date('Y-m-t', strtotime($to.'-01')), true);
        $accounts = Account::whereIn('class', ['income', 'expense'])->whereIn('id', $budget->keys()->merge($actual->keys())->unique())->orderBy('code')->get();

        $rows = $accounts->map(function (Account $account) use ($budget, $actual) {
            $planned = Dec::round($budget[$account->id] ?? '0');
            $real = Balances::natural($account->class, $actual[$account->id] ?? '0');
            // "good" means more income than planned, or less expense than planned
            $variance = $account->class === 'income' ? Dec::sub($real, $planned) : Dec::sub($planned, $real);

            return ['account' => $account, 'budget' => $planned, 'actual' => $real, 'variance' => $variance, 'used' => Dec::isPositive($planned) ? Dec::div(Dec::mul($real, '100'), $planned, 0) : null];
        });

        if ($request->query('export') === 'csv') {
            abort_unless(can_do('reports.export'), 403);

            return Csv::download('budget-comparison-'.$from.'-'.$to, ['Code', 'Account', 'Class', 'Budget', 'Actual', 'Variance', 'Percent of budget'],
                $rows->map(fn (array $row) => [$row['account']->code, $row['account']->name, $row['account']->class, $row['budget'], $row['actual'], $row['variance'], $row['used']]));
        }

        return view('banking.budget-comparison', ['year' => $year, 'fromMonth' => $fromMonth, 'toMonth' => $toMonth, 'income' => $rows->where('account.class', 'income')->values(), 'expenses' => $rows->where('account.class', 'expense')->values()]);
    }

    private function year(Request $request): int
    {
        $year = $request->integer('year') ?: (int) substr(today_iso(), 0, 4);

        return min(2100, max(2000, $year));
    }
}
