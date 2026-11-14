@extends('layouts.app', ['title' => __('app.dashboard.title')])

@php
    $base = $company->base_currency;
    $cards = [
        [__('Cash & bank'), 'wallet', 'primary', money($cash['base'], $base), sums($cash['currencies']), route('accounts.index', ['kind' => 'cash'])],
        [__('Customers owe you'), 'user-tag', 'teal', money($receivable['base'], $base), sums($receivable['currencies']), route('accounts.index', ['kind' => 'customer'])],
        [__('You owe suppliers'), 'truck-field', 'orange', money(\App\Support\Dec::neg($payable['base']), $base), sums(array_map(fn ($amount) => \App\Support\Dec::neg($amount), $payable['currencies'])), route('accounts.index', ['kind' => 'supplier'])],
        [__('Money In'), 'arrow-down', 'success', null, sums($moneyIn), route('money.index', ['direction' => 'in'])],
        [__('Money Out'), 'arrow-up', 'danger', null, sums($moneyOut), route('money.index', ['direction' => 'out'])],
        [\App\Support\Dec::isNegative($profit) ? __('Net loss') : __('Net profit'), 'chart-line', \App\Support\Dec::isNegative($profit) ? 'danger' : 'purple', money($profit, $base), null, route('reports.profit-loss')],
    ];
    $periods = ['today' => __('Today'), 'month' => __('This month'), 'year' => __('This year')];
@endphp

@section('content')
<x-page-header :title="__('Financial Overview')" icon="gauge-high" :subtitle="$company->name.' · '.fmt_date(today_iso()).' · '.__('Reporting currency: :currency', ['currency' => $base])">
    <div class="btn-group" role="group" aria-label="{{ __('Period') }}">
        @foreach ($periods as $key => $label)
            <a class="btn btn-sm {{ $period === $key ? 'btn-primary' : 'btn-outline-soft' }}" href="{{ route('dashboard', ['period' => $key]) }}">{{ $label }}</a>
        @endforeach
    </div>
</x-page-header>

@foreach ($announcements as $announcement)
    <div class="alert alert-{{ $announcement->tone }}"><strong class="d-block">{{ $announcement->title }}</strong>@if ($announcement->body)<span style="white-space:pre-line">{{ $announcement->body }}</span>@endif</div>
@endforeach

<div class="row g-3 mb-3">
    @foreach ($cards as [$label, $icon, $tone, $main, $detail, $url])
        <div class="col-6 col-xl-4">
            <a href="{{ $url }}" class="card card-hover h-100 mb-0 text-decoration-none text-reset">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:38px;height:38px"><x-icon :name="$icon" /></span>
                        <span class="small fw-semibold text-muted">{{ $label }}</span>
                    </div>
                    @if ($main !== null)
                        <div class="fs-5 fw-bold lh-1 mb-1" dir="ltr">{{ $main }}</div>
                    @endif
                    @if ($detail !== null)
                        <div class="{{ $main === null ? 'fs-5 fw-bold' : 'small text-muted' }}" dir="ltr">{{ $detail }}</div>
                    @endif
                </div>
            </a>
        </div>
    @endforeach
</div>

@if ($business)
    <div class="row g-3 mb-3">
        @foreach ($business as [$label, $icon, $tone, $figure, $url])
            <div class="col-6 col-md-4 col-xl-3">
                <a href="{{ $url }}" class="card card-hover h-100 mb-0 text-decoration-none text-reset">
                    <div class="card-body d-flex align-items-center gap-2">
                        <span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:38px;height:38px"><x-icon :name="$icon" /></span>
                        <span class="min-w-0"><span class="d-block small fw-semibold text-muted text-truncate">{{ $label }}</span><span class="d-block fw-bold" dir="ltr">{{ $figure }}</span></span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endif

<div class="row g-3">
    <div class="col-12 col-xl-5">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ __('Cash and bank balances') }}</h5>
                <a class="small text-decoration-none" href="{{ route('accounts.index', ['kind' => 'cash']) }}">{{ __('See all') }}</a>
            </div>
            <div class="card-body">
                {{-- every currency stands on its own line: currencies are never added together without conversion --}}
                <ul class="list-clean">
                    @foreach ($cashAccounts as $account)
                        <li>
                            <span class="sc-icon icon-soft {{ $account->kind === 'bank' ? 'info' : 'primary' }} flex-shrink-0" style="width:36px;height:36px"><x-icon :name="$account->kind === 'bank' ? 'building-columns' : 'money-bill-wave'" /></span>
                            <a href="{{ route('accounts.show', ['id' => $account->id]) }}" class="min-w-0 flex-grow-1 text-decoration-none text-reset">
                                <span class="d-block fw-semibold text-truncate">{{ $account->name }}</span>
                                <span class="d-block small text-muted">{{ $account->kindLabel() }}</span>
                            </a>
                            <span class="fw-semibold flex-shrink-0" dir="ltr">{{ money($cashBalances[$account->id]['currencies'][$account->currency] ?? '0', $account->currency) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-7">
        <div class="card h-100 mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Recent transactions') }}</h5></div>
            <div class="card-body">
                @if ($recent->isEmpty())
                    <div class="empty-state">
                        <div class="es-icon"><x-icon name="arrow-down" /></div>
                        <h3>{{ __('Nothing recorded yet') }}</h3>
                        <p>{{ __('Record the first money you received or paid.') }}</p>
                        @if (can_do('money.create'))
                            <a class="btn btn-primary" href="{{ route('money.create', ['direction' => 'in']) }}"><x-icon name="plus" class="me-1" /> {{ __('Record Money In') }}</a>
                        @endif
                    </div>
                @else
                    <ul class="list-clean">
                        @foreach ($recent as $movement)
                            <li>
                                <span class="sc-icon icon-soft {{ $movement->direction === 'in' ? 'success' : 'danger' }} flex-shrink-0" style="width:36px;height:36px"><x-icon :name="$movement->direction === 'in' ? 'arrow-down' : 'arrow-up'" /></span>
                                <a href="{{ route('money.show', ['direction' => $movement->direction, 'id' => $movement->id]) }}" class="min-w-0 flex-grow-1 text-decoration-none text-reset">
                                    <span class="d-block fw-semibold text-truncate">{{ $movement->counterAccount?->name }}</span>
                                    <span class="d-block small text-muted text-truncate">{{ fmt_date($movement->date) }} · {{ $movement->kindLabel() }}</span>
                                </a>
                                <span class="fw-semibold flex-shrink-0 {{ $movement->isVoid() ? 'text-decoration-line-through text-muted' : '' }}" dir="ltr">{{ money($movement->amount, $movement->currency) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
