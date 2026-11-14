@extends('layouts.app', ['title' => __('Point of Sale')])

@section('content')
<x-page-header :title="__('Point of Sale')" icon="cash-register">
    @if (can_do('settings.manage'))
        <a class="btn btn-outline-soft" href="{{ route('pos.registers.index') }}"><x-icon name="gear" class="me-1" /> {{ __('Registers') }}</a>
    @endif
    <a class="btn btn-outline-soft" href="{{ route('pos.sessions') }}">{{ __('Cashier Sessions') }}</a>
</x-page-header>

@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@if ($registers->isEmpty())
    <div class="card"><div class="card-body"><div class="empty-state">
        <div class="es-icon"><x-icon name="cash-register" /></div>
        <h3>{{ __('Add a register first') }}</h3>
        <p>{{ __('A register is one till: it has its own cash drawer account and sells from one warehouse.') }}</p>
        @if (can_do('settings.manage'))<a class="btn btn-primary" href="{{ route('pos.registers.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => __('Register')]) }}</a>@endif
    </div></div></div>
@else
    <div class="row g-3">
        @foreach ($registers as $register)
            @php
                $session = $sessions[$register->id] ?? null;
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="sc-icon icon-soft {{ $session ? 'success' : 'primary' }} flex-shrink-0" style="width:42px;height:42px"><x-icon name="cash-register" /></span>
                            <div class="min-w-0"><div class="fw-bold text-truncate">{{ $register->name }}</div><div class="small text-muted text-truncate">{{ $register->warehouse?->name }} · {{ $register->cashAccount?->name }}</div></div>
                        </div>
                        @if ($session)
                            <p class="small text-muted">{{ __('Open since :time by :name', ['time' => $session->opened_at->timezone(company()->timezone)->format('j M H:i'), 'name' => $session->user?->name]) }}</p>
                            <div class="d-flex gap-2">
                                <a class="btn btn-success flex-grow-1" href="{{ route('pos.sell', ['id' => $session->id]) }}"><x-icon name="cart-shopping" class="me-1" /> {{ __('Sell') }}</a>
                                @if (can_do('pos.close'))<a class="btn btn-outline-soft" href="{{ route('pos.session', ['id' => $session->id]) }}">{{ __('Cash Closing') }}</a>@endif
                            </div>
                        @else
                            <form method="POST" action="{{ route('pos.open', ['id' => $register->id]) }}">
                                @csrf
                                <label class="form-label small" for="open-{{ $register->id }}">{{ __('Cash in the drawer now') }} ({{ base_currency() }})</label>
                                <div class="d-flex gap-2">
                                    <input type="text" inputmode="decimal" class="form-control" id="open-{{ $register->id }}" name="opening_cash" value="0" dir="ltr" autocomplete="off">
                                    <button type="submit" class="btn btn-primary text-nowrap"><x-icon name="lock-open" class="me-1" /> {{ __('Open') }}</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
