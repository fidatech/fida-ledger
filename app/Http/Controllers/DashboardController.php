<?php

namespace App\Http\Controllers;

use App\Accounting\Balances;
use App\Models\Account;
use App\Models\MoneyMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Financial overview. Every number is read from the ledger or from the posted cash documents. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $company = company();
        $today = $company->today();
        $periods = [
            'today' => [$today, $today],
            'month' => [substr($today, 0, 8).'01', $today],
            'year' => [substr($today, 0, 4).'-01-01', $today],
        ];
        $period = array_key_exists($request->query('period'), $periods) ? $request->query('period') : 'month';
        [$from, $until] = $periods[$period];

        // each currency's cash is shown on its own; only the base-currency total adds them up
        $cashAccounts = Account::cashOrBank()->where('is_archived', false)->orderBy('kind')->orderBy('name')->get();
        $cashBalances = Balances::forAccounts($company->id, $cashAccounts->pluck('id')->all());

        $moved = fn (string $direction) => MoneyMovement::where('direction', $direction)
            ->where('status', MoneyMovement::POSTED)
            ->whereBetween('date', [$from, $until])
            ->groupBy('currency')
            ->selectRaw('currency, SUM(amount) AS total')
            ->pluck('total', 'currency');

        return view('dashboard', [
            'company' => $company,
            'period' => $period,
            'cashAccounts' => $cashAccounts,
            'cashBalances' => $cashBalances,
            'cash' => Balances::ofKinds($company, Account::CASH_KINDS),
            'receivable' => Balances::ofKinds($company, ['customer']),
            'payable' => Balances::ofKinds($company, ['supplier']),
            'moneyIn' => $moved(MoneyMovement::IN),
            'moneyOut' => $moved(MoneyMovement::OUT),
            'profit' => Balances::profit($company, $from, $until),
            'recent' => MoneyMovement::with('cashAccount', 'counterAccount')->orderByDesc('date')->orderByDesc('id')->limit(8)->get(),
        ]);
    }
}
