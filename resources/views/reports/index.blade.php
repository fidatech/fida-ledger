@extends('layouts.app', ['title' => __('Reports')])

@section('content')
<x-page-header :title="__('Financial Reports')" icon="chart-column" :subtitle="__('All reports are prepared from the general ledger in :currency.', ['currency' => base_currency()])" />

@php
    $book = fn (string $key) => route('reports.show', ['report' => $key]);
    $groups = [
        __('Financial') => [
            [__('Trial Balance'), __('Every account with its debit or credit balance on a day.'), 'scale-balanced', 'primary', route('reports.trial-balance')],
            [__('Profit & Loss'), __('Income and expenses of a period, and the profit that is left.'), 'chart-line', 'success', route('reports.profit-loss')],
            [__('Balance Sheet'), __('What the company owns and owes on a day.'), 'landmark', 'info', route('reports.balance-sheet')],
            [__('Cash Flow Statement'), __('Where cash came from and where it went.'), 'money-bill-transfer', 'teal', $book('cash-flow')],
            [__('General Journal'), __('Every entry posted to the ledger.'), 'book', 'purple', can_do('journals.view') ? route('journals.index') : null],
            [__('Tax Report'), __('Tax charged on invoices, less credit notes.'), 'percent', 'orange', $book('tax')],
        ],
        __('Accounts and money') => [
            [__('Account Statements'), __('Customers, suppliers, sarafs and every other account.'), 'address-book', 'teal', can_do('accounts.view') ? route('accounts.index') : null],
            [__('Cashbook and Bankbook'), __('Open a cash or bank account to see its book.'), 'wallet', 'primary', can_do('accounts.view') ? route('accounts.index', ['kind' => 'cash']) : null],
            [__('Receivables Aging'), __('Unpaid invoices by how late they are.'), 'hourglass-half', 'warning', can_do('invoices.view') ? $book('receivables-aging') : null],
            [__('Currency Balances'), __('Each currency on its own, with its value in the books.'), 'coins', 'info', $book('currency-balances')],
            [__('Money In Report'), __('Money received, by kind, currency and date.'), 'arrow-down', 'success', can_do('money.view') ? route('money.index', ['direction' => 'in']) : null],
            [__('Money Out Report'), __('Money paid, by kind, currency and date.'), 'arrow-up', 'danger', can_do('money.view') ? route('money.index', ['direction' => 'out']) : null],
            [__('Exchange Gain / Loss'), __('Every currency exchange with its realized difference.'), 'right-left', 'purple', can_do('money.view') ? route('transfers.index', ['type' => 'exchange']) : null],
            [__('Invoice Reports'), __('Invoices by status, customer and date.'), 'file-invoice-dollar', 'primary', can_do('invoices.view') ? route('invoices.index') : null],
            [__('Audit Report'), __('Who did what in this company.'), 'clock-rotate-left', 'orange', can_do('settings.manage') ? $book('audit-log') : null],
        ],
        __('Business operations') => [
            [__('Product Sales'), __('Quantity and amount invoiced per product.'), 'chart-column', 'success', module_on('products') ? $book('product-sales') : null],
            [__('Purchase Reports'), __('Bills and returns per supplier.'), 'cart-flatbed', 'info', module_on('purchasing') ? $book('purchases-by-supplier') : null],
            [__('Stock Valuation'), __('Stock on hand, its cost and the ledger check.'), 'boxes-stacked', 'purple', module_on('inventory') && can_do('inventory.view') ? route('inventory.overview') : null],
            [__('Stock Movement'), __('Every movement of stock, with its source.'), 'arrows-rotate', 'teal', module_on('inventory') && can_do('inventory.view') ? route('inventory.movements') : null],
            [__('Low Stock'), __('Products at or below their reorder level.'), 'triangle-exclamation', 'danger', module_on('inventory') && can_do('inventory.view') ? route('inventory.overview', ['low' => 1]) : null],
            [__('Warehouse Summary'), __('Units and value per warehouse.'), 'warehouse', 'primary', module_on('inventory') ? $book('warehouse-stock') : null],
            [__('POS Sales'), __('Takings by day and register.'), 'cash-register', 'success', module_on('pos') ? $book('pos-daily') : null],
            [__('Cash Register Reconciliation'), __('Expected and counted cash of every session.'), 'lock', 'warning', module_on('pos') && can_do('pos.use') ? route('pos.sessions') : null],
            [__('Depreciation'), __('Depreciation posted per asset.'), 'building', 'orange', module_on('fixed_assets') ? $book('depreciation') : null],
            [__('Employee Payroll'), __('Gross, deductions and net per employee.'), 'users-gear', 'info', module_on('payroll') && can_do('payroll.view') ? $book('payroll-summary') : null],
        ],
    ];
@endphp
@foreach ($groups as $heading => $reports)
    @php
        $reports = array_values(array_filter($reports, fn ($report) => $report[4] !== null));
    @endphp
    @continue(! $reports)
    <h6 class="text-uppercase small fw-semibold text-muted mt-4 mb-2">{{ $heading }}</h6>
    <div class="row g-3">
        @foreach ($reports as [$name, $text, $icon, $tone, $url])
            <div class="col-12 col-md-6 col-xl-4">
                <a href="{{ $url }}" class="card card-hover h-100 mb-0 text-decoration-none text-reset">
                    <div class="card-body d-flex gap-3 align-items-start">
                        <span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:42px;height:42px"><x-icon :name="$icon" /></span>
                        <span><span class="d-block fw-semibold">{{ $name }}</span><span class="d-block small text-muted">{{ $text }}</span></span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endforeach
@endsection
