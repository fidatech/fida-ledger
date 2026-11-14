<?php

namespace App\Http\Controllers;

use App\Accounting\Balances;
use App\Models\Account;
use App\Models\MoneyMovement;
use App\Models\FixedAsset;
use App\Models\PayrollRun;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockLevel;
use App\Support\Dec;
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

        // Business modules: a figure is worked out only for a module the company has switched on and the user may see
        $business = [];
        if (module_on('pos') && can_do('pos.use')) {
            $start = \Illuminate\Support\Carbon::parse($today, $company->timezone)->utc();
            $business[] = [__('POS sales today'), 'cash-register', 'success', money(PosSale::where('status', 'paid')->where('created_at', '>=', $start)->sum('total'), $company->base_currency), route('pos.sales')];
        }
        if (module_on('sales') && can_do('sales.view')) {
            $business[] = [__('Sales orders open'), 'file-signature', 'primary', (string) SalesOrder::where('type', 'order')->where('status', 'confirmed')->count(), route('sales.index', ['doc' => 'order', 'status' => 'confirmed'])];
        }
        if (module_on('purchasing') && can_do('purchases.view')) {
            $business[] = [__('Purchase orders open'), 'cart-flatbed', 'info', (string) PurchaseOrder::where('status', 'approved')->count(), route('purchases.index', ['status' => 'approved'])];
        }
        if (module_on('inventory') && can_do('inventory.view')) {
            $low = Product::where('products.type', Product::PRODUCT)->where('track_stock', true)->where('reorder_level', '>', 0)
                ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM stock_levels WHERE stock_levels.product_id = products.id) <= products.reorder_level')->count();
            $business[] = [__('Low stock'), 'triangle-exclamation', $low ? 'danger' : 'teal', (string) $low, route('inventory.overview', ['low' => 1])];
            $business[] = [__('Stock value'), 'warehouse', 'purple', money(StockLevel::sum('value'), $company->base_currency), route('inventory.overview')];
        }
        if (module_on('fixed_assets') && can_do('assets.view')) {
            $assets = FixedAsset::where('status', 'active');
            $business[] = [__('Fixed assets, book value'), 'building', 'orange', money(Dec::sub((clone $assets)->sum('cost'), (clone $assets)->sum('depreciated')), $company->base_currency), route('assets.index')];
        }
        if (module_on('payroll') && can_do('payroll.view')) {
            $business[] = [__('Salaries to pay'), 'users-gear', 'warning', money(PayrollRun::where('status', 'approved')->sum('net'), $company->base_currency), route('payroll.index')];
        }

        return view('dashboard', [
            'business' => $business,
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
