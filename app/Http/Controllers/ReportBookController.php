<?php

namespace App\Http\Controllers;

use App\Accounting\Balances;
use App\Inventory\Stock;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Support\Csv;
use App\Support\Dec;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The reports that are a table: one method builds the rows, one view shows them, and the same
 * rows go out as CSV. A report of a Business module exists only while that module is on.
 *
 * Each method returns:
 *   title, icon, subtitle, dates ('range', 'until' or null), headings [[label, 'end'|null]],
 *   rows [[cell, ...]] where a cell is text or [text, url], footer (a row) and note.
 */
class ReportBookController extends Controller
{
    /** report key => [method, module it needs or null] */
    public const REPORTS = [
        'cash-flow' => ['cashFlow', null],
        'receivables-aging' => ['receivablesAging', null],
        'currency-balances' => ['currencyBalances', null],
        'tax' => ['tax', null],
        'audit-log' => ['auditLog', null],
        'product-sales' => ['productSales', 'products'],
        'purchases-by-supplier' => ['purchasesBySupplier', 'purchasing'],
        'warehouse-stock' => ['warehouseStock', 'inventory'],
        'pos-daily' => ['posDaily', 'pos'],
        'depreciation' => ['depreciation', 'fixed_assets'],
        'payroll-summary' => ['payrollSummary', 'payroll'],
    ];

    public function __invoke(Request $request): View|StreamedResponse
    {
        [$method, $module] = self::REPORTS[$request->route('report')];
        abort_unless($module === null || module_on($module), 404);
        // the audit trail and salaries are not for everyone who may read reports
        abort_if($method === 'auditLog' && ! can_do('settings.manage'), 403);
        abort_if($method === 'payrollSummary' && ! can_do('payroll.view'), 403);

        $today = today_iso();
        $from = query_date('from') ?? substr($today, 0, 4).'-01-01';
        $until = query_date('until') ?? $today;
        $report = $this->{$method}($from, $until) + ['footer' => null, 'note' => null, 'from' => $from, 'until' => $until];

        if ($request->query('export') === 'csv') {
            abort_unless(can_do('reports.export'), 403);
            $plain = fn (array $row) => array_map(fn ($cell) => is_array($cell) ? $cell[0] : $cell, $row);

            return Csv::download($request->route('report'), array_column($report['headings'], 0), array_map($plain, array_merge($report['rows'], $report['footer'] ? [$report['footer']] : [])));
        }

        return view('reports.table', $report);
    }

    /** Where cash came from and went to: every entry that touched cash or bank, by what stood on its other side. */
    private function cashFlow(string $from, string $until): array
    {
        $cashIds = Account::cashOrBank()->pluck('id');
        $entries = DB::table('journal_lines')->where('company_id', company_id())->whereIn('account_id', $cashIds)->whereBetween('date', [$from, $until])->select('journal_entry_id');
        $groups = DB::table('journal_lines')->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.company_id', company_id())->whereIn('journal_lines.journal_entry_id', $entries)->whereNotIn('journal_lines.account_id', $cashIds)
            ->groupBy('accounts.class', 'accounts.kind')->orderBy('accounts.class')->orderBy('accounts.kind')
            ->selectRaw('accounts.class AS class, accounts.kind AS kind, SUM(journal_lines.base_credit) AS money_in, SUM(journal_lines.base_debit) AS money_out')->get();

        $opening = Dec::sum(Balances::baseByAccount(company_id(), null, date('Y-m-d', strtotime($from.' -1 day')))->only($cashIds->all()));
        $rows = [];
        $net = '0';
        foreach ($groups as $group) {
            $change = Dec::sub($group->money_in, $group->money_out);
            $net = Dec::add($net, $change);
            $rows[] = [__(config('ledger.kinds.'.$group->kind.'.plural', ucfirst(str_replace('_', ' ', $group->kind ?: $group->class)))), __(ucfirst($group->class)), money($group->money_in), money($group->money_out), money($change)];
        }
        array_unshift($rows, [__('Cash and bank at the start'), '', '', '', money($opening)]);

        return [
            'title' => __('Cash Flow Statement'), 'icon' => 'money-bill-transfer', 'subtitle' => __('In :currency. Transfers between your own cash and bank accounts are not counted.', ['currency' => base_currency()]), 'dates' => 'range',
            'headings' => [[__('What it was for'), null], [__('Class'), null], [__('Cash in'), 'end'], [__('Cash out'), 'end'], [__('Net'), 'end']],
            'rows' => $rows,
            'footer' => [__('Cash and bank at the end'), '', '', '', money(Dec::add($opening, $net))],
        ];
    }

    /** What each customer still owes on issued invoices, by how late it is. */
    private function receivablesAging(string $from, string $until): array
    {
        $today = today_iso();
        $buckets = [];
        foreach (Invoice::with('customer')->where('type', Invoice::INVOICE)->where('status', Invoice::ISSUED)->whereColumn('paid', '<', 'total')->orderBy('due_date')->cursor() as $invoice) {
            $days = $invoice->due_date ? (int) floor((strtotime($today) - strtotime($invoice->due_date->toDateString())) / 86400) : 0;
            $bucket = $days <= 0 ? 0 : ($days <= 30 ? 1 : ($days <= 60 ? 2 : ($days <= 90 ? 3 : 4)));
            $key = $invoice->customer_account_id.'|'.$invoice->currency;
            $buckets[$key] ??= ['name' => $invoice->customer?->name, 'id' => $invoice->customer_account_id, 'currency' => $invoice->currency, 'amounts' => ['0', '0', '0', '0', '0']];
            $buckets[$key]['amounts'][$bucket] = Dec::add($buckets[$key]['amounts'][$bucket], $invoice->due());
        }
        $rows = [];
        foreach ($buckets as $row) {
            $rows[] = array_merge([[$row['name'], route('accounts.show', ['id' => $row['id']])], $row['currency']], array_map(fn ($amount) => Dec::isZero($amount) ? '' : money($amount), $row['amounts']), [money(Dec::sum($row['amounts']))]);
        }

        return [
            'title' => __('Receivables Aging'), 'icon' => 'hourglass-half', 'subtitle' => __('Unpaid invoices on :date, by days past the due date', ['date' => fmt_date($today)]), 'dates' => null,
            'headings' => [[__('Customer'), null], [__('Currency'), null], [__('Not due'), 'end'], ['1–30', 'end'], ['31–60', 'end'], ['61–90', 'end'], ['90+', 'end'], [__('Total'), 'end']],
            'rows' => $rows,
            'note' => __('Suppliers are shown by balance under Accounts → Suppliers: supplier payments are not matched to single bills.'),
        ];
    }

    /** Every currency on its own: what is held, owed to us and owed by us, and its value in the base currency as carried in the books. */
    private function currencyBalances(string $from, string $until): array
    {
        $groups = [__('Cash & bank') => ['cash', 'bank'], __('Customers') => ['customer'], __('Suppliers') => ['supplier'], __('Sarafs') => ['saraf'], __('Personal accounts') => ['personal'], __('Loans given') => ['loan_receivable'], __('Loans taken') => ['loan_payable']];
        $rows = [];
        foreach ($groups as $label => $kinds) {
            $rowsOfGroup = DB::table('journal_lines')->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
                ->where('journal_lines.company_id', company_id())->whereIn('accounts.kind', $kinds)->where('journal_lines.date', '<=', $until)
                ->groupBy('journal_lines.currency')->orderBy('journal_lines.currency')
                ->selectRaw('journal_lines.currency AS currency, SUM(journal_lines.debit) - SUM(journal_lines.credit) AS balance, SUM(journal_lines.base_debit) - SUM(journal_lines.base_credit) AS base')->get();
            foreach ($rowsOfGroup as $row) {
                if (Dec::isZero($row->balance) && Dec::isZero($row->base)) {
                    continue;
                }
                $rows[] = [$label, $row->currency, money($row->balance), money($row->base), Dec::isZero($row->balance) ? '' : rtrim(rtrim(Dec::div($row->base, $row->balance, 6), '0'), '.')];
            }
        }

        return [
            'title' => __('Currency Balances'), 'icon' => 'coins', 'subtitle' => __('As of :date. A negative balance is an amount we owe.', ['date' => fmt_date($until)]), 'dates' => 'until',
            'headings' => [[__('Group'), null], [__('Currency'), null], [__('Balance'), 'end'], [__('Value in :currency', ['currency' => base_currency()]), 'end'], [__('Carried at rate'), 'end']],
            'rows' => $rows,
            'note' => __('Values are what was posted, not today\'s rates. Currencies are never added together.'),
        ];
    }

    /** Tax charged on invoices, less tax given back on credit notes. */
    private function tax(string $from, string $until): array
    {
        $rows = [];
        $totals = [];
        foreach (Invoice::with('customer')->where('status', Invoice::ISSUED)->where('tax', '>', 0)->whereBetween('date', [$from, $until])->orderBy('date')->orderBy('id')->cursor() as $invoice) {
            $sign = $invoice->isCreditNote() ? '-1' : '1';
            $tax = Dec::mul($invoice->tax, $sign);
            $totals[$invoice->currency] = Dec::add($totals[$invoice->currency] ?? '0', $tax);
            $rows[] = [fmt_date($invoice->date), [$invoice->code(), route('invoices.show', ['id' => $invoice->id])], $invoice->customer?->name, $invoice->currency, money(Dec::mul($invoice->subtotal, $sign)), money($tax)];
        }

        return [
            'title' => __('Tax Report'), 'icon' => 'percent', 'subtitle' => __('Tax on issued invoices and credit notes'), 'dates' => 'range',
            'headings' => [[__('Date'), null], [__('Document'), null], [__('Customer'), null], [__('Currency'), null], [__('Net amount'), 'end'], [__('Tax'), 'end']],
            'rows' => $rows,
            'footer' => [__('Total tax'), '', '', '', '', sums($totals)],
        ];
    }

    private function auditLog(string $from, string $until): array
    {
        $rows = AuditLog::where('company_id', company_id())->whereBetween('created_at', [$from.' 00:00:00', $until.' 23:59:59'])->orderByDesc('id')->limit(1000)->get()
            ->map(fn (AuditLog $log) => [$log->created_at?->timezone(company()->timezone)->format('j M Y H:i'), \App\Models\User::find($log->user_id)?->name ?? ($log->admin_id ? __('System administrator') : ''), $log->action, (string) $log->subject, $log->details ? json_encode($log->details, JSON_UNESCAPED_UNICODE) : '', (string) $log->ip])->all();

        return [
            'title' => __('Audit Report'), 'icon' => 'clock-rotate-left', 'subtitle' => __('Sensitive actions in this company, newest first (up to 1,000)'), 'dates' => 'range',
            'headings' => [[__('When'), null], [__('Who'), null], [__('Action'), null], [__('Subject'), null], [__('Details'), null], ['IP', null]],
            'rows' => $rows,
        ];
    }

    /** Quantity and net amount invoiced per product (credit notes subtracted), per currency. */
    private function productSales(string $from, string $until): array
    {
        $rows = DB::table('invoice_lines')->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoices.company_id', company_id())->where('invoices.status', Invoice::ISSUED)->whereBetween('invoices.date', [$from, $until])
            ->groupBy('invoice_lines.product_id', 'invoice_lines.description', 'invoices.currency')
            ->selectRaw("invoice_lines.description AS name, invoices.currency AS currency,
                SUM(CASE WHEN invoices.type = 'credit_note' THEN -invoice_lines.quantity ELSE invoice_lines.quantity END) AS quantity,
                SUM(CASE WHEN invoices.type = 'credit_note' THEN -invoice_lines.net ELSE invoice_lines.net END) AS net")
            ->orderByDesc('net')->get();
        $totals = [];
        foreach ($rows as $row) {
            $totals[$row->currency] = Dec::add($totals[$row->currency] ?? '0', $row->net);
        }

        return [
            'title' => __('Product Sales'), 'icon' => 'chart-column', 'subtitle' => __('What was invoiced, less credit notes'), 'dates' => 'range',
            'headings' => [[__('Product or service'), null], [__('Currency'), null], [__('Quantity'), 'end'], [__('Net amount'), 'end']],
            'rows' => $rows->map(fn ($row) => [$row->name, $row->currency, Stock::qty($row->quantity), money($row->net)])->all(),
            'footer' => [__('Total'), '', '', sums($totals)],
        ];
    }

    private function purchasesBySupplier(string $from, string $until): array
    {
        $rows = DB::table('purchase_events')->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_events.purchase_order_id')->join('accounts', 'accounts.id', '=', 'purchase_orders.supplier_account_id')
            ->where('purchase_events.company_id', company_id())->whereIn('purchase_events.type', ['bill', 'return'])->whereBetween('purchase_events.date', [$from, $until])
            ->groupBy('accounts.id', 'accounts.name', 'purchase_orders.currency')->orderBy('accounts.name')
            ->selectRaw("accounts.id AS id, accounts.name AS name, purchase_orders.currency AS currency,
                SUM(CASE WHEN purchase_events.type = 'bill' THEN purchase_events.amount ELSE 0 END) AS billed,
                SUM(CASE WHEN purchase_events.type = 'return' THEN purchase_events.amount ELSE 0 END) AS returned")->get();

        return [
            'title' => __('Purchase Reports'), 'icon' => 'cart-flatbed', 'subtitle' => __('Supplier bills and returns of billed goods'), 'dates' => 'range',
            'headings' => [[__('Supplier'), null], [__('Currency'), null], [__('Billed'), 'end'], [__('Returned'), 'end'], [__('Net purchases'), 'end']],
            'rows' => $rows->map(fn ($row) => [[$row->name, route('accounts.show', ['id' => $row->id])], $row->currency, money($row->billed), money($row->returned), money(Dec::sub($row->billed, $row->returned))])->all(),
        ];
    }

    private function warehouseStock(string $from, string $until): array
    {
        $rows = DB::table('stock_levels')->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')->where('stock_levels.company_id', company_id())->where('stock_levels.quantity', '>', 0)
            ->groupBy('warehouses.id', 'warehouses.name')->orderBy('warehouses.name')
            ->selectRaw('warehouses.id AS id, warehouses.name AS name, COUNT(*) AS products, SUM(stock_levels.quantity) AS quantity, SUM(stock_levels.value) AS value')->get();

        return [
            'title' => __('Warehouse Summary'), 'icon' => 'warehouse', 'subtitle' => __('Stock on hand now, valued at average cost in :currency', ['currency' => base_currency()]), 'dates' => null,
            'headings' => [[__('Warehouse'), null], [__('Products in stock'), 'end'], [__('Units'), 'end'], [__('Value'), 'end']],
            'rows' => $rows->map(fn ($row) => [[$row->name, route('inventory.overview', ['warehouse' => $row->id])], (string) $row->products, Stock::qty($row->quantity), money($row->value)])->all(),
            'footer' => [__('Total'), '', '', money(Dec::sum($rows->pluck('value')))],
        ];
    }

    /** POS takings by day and register, with the cost of what was sold. */
    private function posDaily(string $from, string $until): array
    {
        $sales = \App\Models\PosSale::with('session.register')->where('status', 'paid')->whereBetween('created_at', [$from.' 00:00:00', $until.' 23:59:59'])->orderBy('id')->cursor();
        $days = [];
        foreach ($sales as $sale) {
            $key = $sale->created_at->timezone(company()->timezone)->toDateString().'|'.$sale->session?->register?->name;
            $days[$key] ??= ['count' => 0, 'total' => '0', 'cash' => '0', 'cost' => '0'];
            $days[$key]['count']++;
            foreach (['total' => $sale->total, 'cash' => $sale->cash_paid, 'cost' => $sale->cost] as $field => $amount) {
                $days[$key][$field] = Dec::add($days[$key][$field], $amount);
            }
        }
        $rows = [];
        foreach ($days as $key => $day) {
            [$date, $register] = explode('|', $key, 2);
            $rows[] = [fmt_date($date), $register, (string) $day['count'], money($day['total']), money($day['cash']), money(Dec::sub($day['total'], $day['cash'])), money($day['cost'])];
        }

        return [
            'title' => __('POS Sales'), 'icon' => 'cash-register', 'subtitle' => __('Paid sales by day and register, in :currency. Refunded sales are left out.', ['currency' => base_currency()]), 'dates' => 'range',
            'headings' => [[__('Day'), null], [__('Register'), null], [__('Sales'), 'end'], [__('Total'), 'end'], [__('Cash'), 'end'], [__('Other payments'), 'end'], [__('Cost of goods'), 'end']],
            'rows' => $rows,
            'footer' => [__('Total'), '', (string) array_sum(array_column($days, 'count')), money(Dec::sum(array_column($days, 'total'))), money(Dec::sum(array_column($days, 'cash'))), '', money(Dec::sum(array_column($days, 'cost')))],
        ];
    }

    private function depreciation(string $from, string $until): array
    {
        $rows = DB::table('asset_depreciations')->join('fixed_assets', 'fixed_assets.id', '=', 'asset_depreciations.fixed_asset_id')->where('asset_depreciations.company_id', company_id())
            ->whereBetween('asset_depreciations.period', [substr($from, 0, 7), substr($until, 0, 7)])
            ->groupBy('fixed_assets.id', 'fixed_assets.name', 'fixed_assets.cost')->orderBy('fixed_assets.name')
            ->selectRaw('fixed_assets.id AS id, fixed_assets.name AS name, fixed_assets.cost AS cost, COUNT(*) AS months, SUM(asset_depreciations.amount) AS amount')->get();

        return [
            'title' => __('Depreciation'), 'icon' => 'building', 'subtitle' => __('Depreciation posted in the period, in :currency', ['currency' => base_currency()]), 'dates' => 'range',
            'headings' => [[__('Asset'), null], [__('Cost'), 'end'], [__('Months'), 'end'], [__('Depreciation'), 'end']],
            'rows' => $rows->map(fn ($row) => [[$row->name, route('assets.show', ['id' => $row->id])], money($row->cost), (string) $row->months, money($row->amount)])->all(),
            'footer' => [__('Total'), '', '', money(Dec::sum($rows->pluck('amount')))],
        ];
    }

    private function payrollSummary(string $from, string $until): array
    {
        $rows = DB::table('payroll_lines')->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_lines.payroll_run_id')->join('employees', 'employees.id', '=', 'payroll_lines.employee_id')
            ->where('payroll_lines.company_id', company_id())->whereIn('payroll_runs.status', ['approved', 'paid'])->whereBetween('payroll_runs.period', [substr($from, 0, 7), substr($until, 0, 7)])
            ->groupBy('employees.id', 'employees.name')->orderBy('employees.name')
            ->selectRaw('employees.name AS name, COUNT(*) AS months, SUM(payroll_lines.basic) + SUM(payroll_lines.allowances) AS gross, SUM(payroll_lines.deductions) AS deductions, SUM(payroll_lines.advance) AS advances, SUM(payroll_lines.net) AS net')->get();
        $unpaid = DB::table('payroll_runs')->where('company_id', company_id())->where('status', 'approved')->sum('net');

        return [
            'title' => __('Employee Payroll'), 'icon' => 'users-gear', 'subtitle' => __('Approved payrolls in the period, in :currency', ['currency' => base_currency()]), 'dates' => 'range',
            'headings' => [[__('Employee'), null], [__('Months'), 'end'], [__('Gross'), 'end'], [__('Deductions'), 'end'], [__('Advances recovered'), 'end'], [__('Net'), 'end']],
            'rows' => $rows->map(fn ($row) => [$row->name, (string) $row->months, money($row->gross), money($row->deductions), money($row->advances), money($row->net)])->all(),
            'footer' => [__('Total'), '', money(Dec::sum($rows->pluck('gross'))), money(Dec::sum($rows->pluck('deductions'))), money(Dec::sum($rows->pluck('advances'))), money(Dec::sum($rows->pluck('net')))],
            'note' => __('Salaries approved and not yet paid: :amount', ['amount' => money($unpaid, base_currency())]),
        ];
    }
}
