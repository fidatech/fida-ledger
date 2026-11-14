@extends('layouts.app', ['title' => __('Budget Comparison')])

@section('content')
@php
    $months = [];
    for ($m = 1; $m <= 12; $m++) {
        $months[$m] = date('F', mktime(0, 0, 0, $m, 1, 2000));
    }
    $sum = fn ($rows, string $field) => \App\Support\Dec::sum($rows->pluck($field));
@endphp
<x-page-header :title="__('Budget Comparison')" icon="chart-column" :subtitle="__(':from to :to :year, in :currency', ['from' => __($months[$fromMonth]), 'to' => __($months[$toMonth]), 'year' => $year, 'currency' => base_currency()])" :crumbs="[[__('Budgets'), route('banking.budget', ['year' => $year])], [__('Comparison')]]">
    @if (can_do('reports.export'))
        <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><x-icon name="file-arrow-down" class="me-1" /> {{ __('Excel (CSV)') }}</a>
    @endif
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print / PDF') }}</button>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3 no-print">
            <div class="col-4 col-md-auto"><input type="number" class="form-control" name="year" value="{{ $year }}" min="2000" max="2100" aria-label="{{ __('Year') }}"></div>
            <div class="col-4 col-md-auto"><select class="form-select" name="from_month" data-plain aria-label="{{ __('From') }}">@foreach ($months as $number => $name)<option value="{{ $number }}" @selected($fromMonth === $number)>{{ __($name) }}</option>@endforeach</select></div>
            <div class="col-4 col-md-auto"><select class="form-select" name="to_month" data-plain aria-label="{{ __('Until') }}">@foreach ($months as $number => $name)<option value="{{ $number }}" @selected($toMonth === $number)>{{ __($name) }}</option>@endforeach</select></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        @if ($income->isEmpty() && $expenses->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="bullseye" /></div><h3>{{ __('No budget and nothing posted for these months') }}</h3></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Account') }}</th><th class="text-end">{{ __('Budget') }}</th><th class="text-end">{{ __('Actual') }}</th><th class="text-end">{{ __('Difference') }}</th><th class="text-end">{{ __('Of budget') }}</th></tr></thead>
                <tbody>
                    @foreach ([[__('Income'), $income], [__('Expenses'), $expenses]] as [$heading, $rows])
                        @continue($rows->isEmpty())
                        <tr class="table-light"><th colspan="5">{{ $heading }}</th></tr>
                        @foreach ($rows as $row)
                            <tr>
                                <td style="padding-inline-start:28px">{{ $row['account']->name }}</td>
                                <td class="text-end" dir="ltr">{{ money($row['budget']) }}</td>
                                <td class="text-end" dir="ltr">{{ money($row['actual']) }}</td>
                                <td class="text-end {{ \App\Support\Dec::isNegative($row['variance']) ? 'text-danger' : 'text-success' }}" dir="ltr">{{ money($row['variance']) }}</td>
                                <td class="text-end" dir="ltr">{{ $row['used'] !== null ? $row['used'].'%' : '' }}</td>
                            </tr>
                        @endforeach
                        <tr class="fw-bold"><td>{{ __('Total') }} · {{ $heading }}</td><td class="text-end" dir="ltr">{{ money($sum($rows, 'budget')) }}</td><td class="text-end" dir="ltr">{{ money($sum($rows, 'actual')) }}</td><td class="text-end" dir="ltr">{{ money($sum($rows, 'variance')) }}</td><td></td></tr>
                    @endforeach
                </tbody>
            </table>
            <p class="small text-muted mb-0">{{ __('Green is better than planned: more income, or less expense. Red is worse than planned.') }}</p>
        @endif
    </div>
</div>
@endsection
