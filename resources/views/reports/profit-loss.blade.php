@extends('layouts.app', ['title' => __('Profit & Loss')])

@section('content')
<x-page-header :title="__('Profit & Loss')" icon="chart-line" :subtitle="__(':from to :until, in :currency', ['from' => fmt_date($from), 'until' => fmt_date($until), 'currency' => base_currency()])" :crumbs="[[__('Reports'), route('reports.index')], [__('Profit & Loss')]]">
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3 no-print">
            <div class="col-6 col-md-auto"><x-date name="from" :value="$from" aria-label="{{ __('From') }}" /></div>
            <div class="col-6 col-md-auto"><x-date name="until" :value="$until" aria-label="{{ __('Until') }}" /></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>
        <table class="table w-100">
            <tbody>
                @include('reports._section', ['heading' => __('Income'), 'rows' => $income, 'total' => $totalIncome, 'totalLabel' => __('Total income')])
                @include('reports._section', ['heading' => __('Expenses'), 'rows' => $expenses, 'total' => $totalExpenses, 'totalLabel' => __('Total expenses')])
                <tr class="fw-bold fs-5 {{ \App\Support\Dec::isNegative($profit) ? 'text-danger' : 'text-success' }}"><td>{{ \App\Support\Dec::isNegative($profit) ? __('Net loss') : __('Net profit') }}</td><td class="text-end" dir="ltr">{{ money($profit) }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
