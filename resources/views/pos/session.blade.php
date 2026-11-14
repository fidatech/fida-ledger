@extends('layouts.app', ['title' => __('Cash Closing')])

@section('content')
@php
    $paid = $sales->where('status', 'paid');
@endphp
<x-page-header :title="$session->register?->name" icon="cash-register" :subtitle="__('Opened :time by :name', ['time' => $session->opened_at->timezone(company()->timezone)->format('j M Y H:i'), 'name' => $session->user?->name])" :crumbs="[[__('Cashier Sessions'), route('pos.sessions')], [$session->isOpen() ? __('Open') : __('Closed')]]">
    @if ($session->isOpen() && can_do('pos.use'))
        <a class="btn btn-success" href="{{ route('pos.sell', ['id' => $session->id]) }}"><x-icon name="cart-shopping" class="me-1" /> {{ __('Sell') }}</a>
    @endif
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('Sales') }}</div><div class="fs-4 fw-bold" dir="ltr">{{ money(\App\Support\Dec::sum($paid->pluck('total'))) }}</div><div class="small text-muted">{{ $paid->count() }} · {{ __('refunded') }} {{ $sales->where('status', 'refunded')->count() }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('Cash at opening') }}</div><div class="fs-4 fw-bold" dir="ltr">{{ money($session->opening_cash) }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('Cash expected in the drawer') }}</div><div class="fs-4 fw-bold" dir="ltr">{{ money($expected) }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('Counted') }}</div>
        @if ($session->isOpen())
            <div class="fs-4 fw-bold text-muted">—</div>
        @else
            <div class="fs-4 fw-bold" dir="ltr">{{ money($session->counted_cash) }}</div>
            <div class="small {{ \App\Support\Dec::isNegative($session->difference) ? 'text-danger' : 'text-muted' }}" dir="ltr">{{ __('Difference') }}: {{ money($session->difference) }}</div>
        @endif
    </div></div></div>
</div>

@if ($session->isOpen() && can_do('pos.close'))
    <form method="POST" action="{{ route('pos.close', ['id' => $session->id]) }}" class="card no-print" data-confirm="{{ __('Close this register?') }}" data-confirm-text="{{ __('A difference between the count and the expected cash is posted as a shortage or a surplus.') }}" data-confirm-ok="{{ __('Close register') }}" data-confirm-style="primary">
        @csrf
        <div class="card-header"><h5 class="card-title mb-0">{{ __('Cash Closing') }}</h5></div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="counted_cash">{{ __('Cash counted in the drawer') }} <span class="text-danger">*</span></label>
                    <input type="text" inputmode="decimal" class="form-control" id="counted_cash" name="counted_cash" dir="ltr" autocomplete="off" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="notes">{{ __('Notes') }}</label>
                    <input type="text" class="form-control" id="notes" name="notes" maxlength="255">
                </div>
                <div class="col-12 col-md-3"><button type="submit" class="btn btn-primary w-100"><x-icon name="lock" class="me-1" /> {{ __('Close register') }}</button></div>
            </div>
        </div>
    </form>
@endif

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">{{ __('Sales of this session') }}</h5></div>
    <div class="card-body">
        @if ($sales->isEmpty())
            <p class="text-muted mb-0">{{ __('Nothing was sold yet.') }}</p>
        @else
            <table class="table table-hover w-100 mb-0">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Time') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Paid by') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                <tbody>
                    @foreach ($sales as $sale)
                        <tr class="{{ $sale->status === 'refunded' ? 'text-muted' : '' }}">
                            <td dir="ltr"><a class="text-decoration-none" href="{{ route('pos.sale', ['id' => $sale->id]) }}">{{ $sale->code() }}</a>@if ($sale->status === 'refunded') <span class="badge bg-danger-subtle text-danger-emphasis">{{ __('Refunded') }}</span>@endif</td>
                            <td>{{ $sale->created_at->timezone(company()->timezone)->format('H:i') }}</td>
                            <td>{{ $sale->customer?->name }}</td>
                            <td class="small">{{ collect($sale->payments)->map(fn ($p) => $p['name'].' '.money($p['amount']))->implode(', ') }}</td>
                            <td class="text-end" dir="ltr">{{ money($sale->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
