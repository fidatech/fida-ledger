<?php

namespace App\Http\Controllers;

use App\Accounting\Balances;
use App\Models\Account;
use App\Support\Csv;
use App\Support\Dec;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Financial reports. All of them are sums of journal lines in the base currency, so they
 * agree with each other and with the general ledger by construction.
 */
class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }

    /** Every account with a balance on a day: total debits must equal total credits. */
    public function trialBalance(): View|StreamedResponse
    {
        $until = query_date('until') ?? today_iso();
        $balances = Balances::baseByAccount(company_id(), null, $until);
        $rows = $this->accounts($balances)->map(fn (Account $account) => [
            'account' => $account,
            'debit' => Dec::isPositive($balances[$account->id]) ? $balances[$account->id] : '0',
            'credit' => Dec::isNegative($balances[$account->id]) ? Dec::neg($balances[$account->id]) : '0',
        ]);

        if (request()->query('export') === 'csv') {
            abort_unless(can_do('reports.export'), 403);

            return Csv::download('trial-balance-'.$until, ['Code', 'Account', 'Debit', 'Credit'], $rows->map(fn (array $row) => [$row['account']->code, $row['account']->name, $row['debit'], $row['credit']]));
        }

        return view('reports.trial-balance', [
            'until' => $until,
            'rows' => $rows,
            'debits' => Dec::sum($rows->pluck('debit')),
            'credits' => Dec::sum($rows->pluck('credit')),
        ]);
    }

    /** Income and expenses of a period, and what is left. */
    public function profitLoss(): View
    {
        $today = today_iso();
        $from = query_date('from') ?? substr($today, 0, 4).'-01-01';
        $until = query_date('until') ?? $today;
        $balances = Balances::baseByAccount(company_id(), $from, $until, true); // before any year-end closing entry
        $accounts = $this->accounts($balances);

        $section = fn (string $class) => $accounts->where('class', $class)->map(fn (Account $account) => [
            'account' => $account,
            'amount' => Balances::natural($class, $balances[$account->id]),
        ])->values();
        $income = $section('income');
        $expenses = $section('expense');

        return view('reports.profit-loss', [
            'from' => $from,
            'until' => $until,
            'income' => $income,
            'expenses' => $expenses,
            'totalIncome' => Dec::sum($income->pluck('amount')),
            'totalExpenses' => Dec::sum($expenses->pluck('amount')),
            'profit' => Dec::sub(Dec::sum($income->pluck('amount')), Dec::sum($expenses->pluck('amount'))),
        ]);
    }

    /**
     * What the company owns and owes on a day. Profit that was not yet moved to equity by a
     * year-end closing is shown as its own line under equity, so the sheet always balances.
     */
    public function balanceSheet(): View
    {
        $until = query_date('until') ?? today_iso();
        $balances = Balances::baseByAccount(company_id(), null, $until);
        $accounts = $this->accounts($balances);

        $section = fn (string $class) => $accounts->where('class', $class)->map(fn (Account $account) => [
            'account' => $account,
            'amount' => Balances::natural($class, $balances[$account->id]),
        ])->values();
        $assets = $section('asset');
        $liabilities = $section('liability');
        $equity = $section('equity');
        $earnings = Balances::profit(company(), null, $until);

        $totalAssets = Dec::sum($assets->pluck('amount'));
        $totalLiabilities = Dec::sum($liabilities->pluck('amount'));
        $totalEquity = Dec::add(Dec::sum($equity->pluck('amount')), $earnings);

        return view('reports.balance-sheet', [
            'until' => $until,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'earnings' => $earnings,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalEquity' => $totalEquity,
            'balanced' => Dec::cmp($totalAssets, Dec::add($totalLiabilities, $totalEquity)) === 0,
        ]);
    }

    /** The accounts that have a balance, in the order of the chart. */
    private function accounts(Collection $balances): Collection
    {
        return Account::whereIn('id', $balances->keys())->orderBy('code')->get();
    }
}
