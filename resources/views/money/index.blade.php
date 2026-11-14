@extends('layouts.app', ['title' => $direction === 'in' ? __('Money In History') : __('Money Out History')])

@section('content')
@php
    $in = $direction === 'in';
@endphp
<x-page-header :title="$in ? __('Money In History') : __('Money Out History')" :icon="$in ? 'arrow-down' : 'arrow-up'">
    @if (can_do('money.create'))
        <a class="btn {{ $in ? 'btn-success' : 'btn-danger' }}" href="{{ route('money.create', ['direction' => $direction]) }}"><x-icon name="plus" class="me-1" /> {{ $in ? __('Record Money In') : __('Record Money Out') }}</a>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md">
                <input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search by account, description or reference…') }}" aria-label="{{ __('Search') }}">
            </div>
            <div class="col-6 col-md-auto">
                <select class="form-select" name="kind" aria-label="{{ __('Kind') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('All kinds') }}</option>
                    @foreach ($kinds as $value => $option)
                        <option value="{{ $value }}" @selected(request('kind') === $value)>{{ __($option['label']) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <select class="form-select" name="currency" aria-label="{{ __('Currency') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('All currencies') }}</option>
                    @foreach (currencies() as $code)
                        <option value="{{ $code }}" @selected(request('currency') === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto"><x-date name="from" :value="query_date('from')" title="{{ __('From') }}" aria-label="{{ __('From') }}" /></div>
            <div class="col-6 col-md-auto"><x-date name="until" :value="query_date('until')" title="{{ __('Until') }}" aria-label="{{ __('Until') }}" /></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button></div>
        </form>

        @if ($totals->isNotEmpty())
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge bg-primary-subtle text-primary-emphasis fw-semibold p-2">{{ __('Total') }}: <span dir="ltr">{{ sums($totals) }}</span></span>
            </div>
        @endif

        @if ($movements->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon :name="$in ? 'arrow-down' : 'arrow-up'" /></div>
                <h3>{{ __('Nothing found') }}</h3>
            </div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Account') }}</th><th>{{ __('Kind') }}</th><th>{{ $in ? __('Into') : __('From') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($movements as $movement)
                        <tr class="{{ $movement->isVoid() ? 'text-muted' : '' }}">
                            <td dir="ltr" class="small">{{ $movement->code() }}</td>
                            <td class="text-nowrap">{{ fmt_date($movement->date) }}</td>
                            <td><span class="fw-semibold">{{ $movement->counterAccount?->name }}</span>@if ($movement->description)<span class="d-block small text-muted">{{ $movement->description }}</span>@endif</td>
                            <td>{{ $movement->kindLabel() }}@if ($movement->isVoid()) <span class="badge bg-danger-subtle text-danger-emphasis">{{ __('Cancelled') }}</span>@endif</td>
                            <td>{{ $movement->cashAccount?->name }}</td>
                            <td class="text-end fw-semibold {{ $movement->isVoid() ? 'text-decoration-line-through' : '' }}" dir="ltr">{{ money($movement->amount, $movement->currency) }}</td>
                            <td class="text-end no-print"><div class="act-group"><x-act kind="receipt" :href="route('money.show', ['direction' => $direction, 'id' => $movement->id])" /></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $movements->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
