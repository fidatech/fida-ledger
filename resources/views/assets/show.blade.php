@extends('layouts.app', ['title' => $asset->name])

@section('content')
@php
    $manage = can_do('assets.manage') && $asset->status === 'active';
@endphp
<x-page-header :title="$asset->name" icon="building" :subtitle="$asset->code().' · '.($asset->category?->name ?? __('No category')).' · '.fmt_date($asset->acquired_on)" :crumbs="[[__('Asset Register'), route('assets.index')], [$asset->code()]]">
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if ($asset->status === 'disposed')
    <div class="alert alert-secondary">{{ __('Disposed of on :date for :amount.', ['date' => fmt_date($asset->disposed_on), 'amount' => money($asset->disposal_proceeds, base_currency())]) }}</div>
@endif

<div class="row g-3 mb-3">
    @foreach ([[__('Cost'), $asset->cost], [__('Accumulated depreciation'), $asset->depreciated], [__('Net book value'), $asset->status === 'disposed' ? '0' : $asset->bookValue()], [__('Per month'), $asset->monthly()]] as [$label, $amount])
        <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ $label }}</div><div class="fs-4 fw-bold" dir="ltr">{{ money($amount) }}</div></div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100 mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Depreciation schedule') }}</h5></div>
            <div class="card-body">
                <p class="small text-muted">{{ __('Straight line over :months months, residual value :residual. Location: :where. Assigned to: :who.', ['months' => $asset->useful_life_months, 'residual' => money($asset->residual_value), 'where' => $asset->location ?: '—', 'who' => $asset->assigned_to ?: '—']) }}</p>
                @if ($asset->depreciations->isEmpty())
                    <p class="text-muted mb-0">{{ __('No depreciation was run for this asset yet.') }}</p>
                @else
                    <table class="table table-sm w-100 mb-0">
                        <thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Entry') }}</th></tr></thead>
                        <tbody>
                            @foreach ($asset->depreciations as $row)
                                <tr><td dir="ltr">{{ $row->period }}</td><td class="text-end" dir="ltr">{{ money($row->amount) }}</td><td class="text-end">@if (can_do('journals.view'))<a class="text-decoration-none" href="{{ route('journals.show', ['id' => $row->journal_entry_id]) }}">{{ __('Open') }}</a>@endif</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card mb-3">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Maintenance and transfers') }}</h5></div>
            <div class="card-body">
                @if ($manage)
                    <form method="POST" action="{{ route('assets.event', ['id' => $asset->id]) }}" class="row g-2 mb-3 no-print">
                        @csrf
                        <div class="col-6 col-md-4"><select class="form-select form-select-sm" name="type" data-plain aria-label="{{ __('Kind') }}"><option value="maintenance">{{ __('Maintenance') }}</option><option value="transfer">{{ __('Transfer') }}</option></select></div>
                        <div class="col-6 col-md-4"><x-date name="date" :value="today_iso()" :required="true" class="form-control-sm" aria-label="{{ __('Date') }}" /></div>
                        <div class="col-12 col-md-4"><input type="text" inputmode="decimal" class="form-control form-control-sm" name="cost" placeholder="{{ __('Cost (optional)') }}" dir="ltr" autocomplete="off" aria-label="{{ __('Cost') }}"></div>
                        <div class="col-12"><input type="text" class="form-control form-control-sm" name="description" placeholder="{{ __('What was done, or why it moved') }}" maxlength="255" required aria-label="{{ __('Description') }}"></div>
                        <div class="col-6"><input type="text" class="form-control form-control-sm" name="location" placeholder="{{ __('New location (transfer)') }}" maxlength="255" aria-label="{{ __('Location') }}"></div>
                        <div class="col-6"><input type="text" class="form-control form-control-sm" name="assigned_to" placeholder="{{ __('New person (transfer)') }}" maxlength="255" aria-label="{{ __('Assigned to') }}"></div>
                        <div class="col-12"><button type="submit" class="btn btn-sm btn-primary">{{ __('Add to the log') }}</button> <span class="small text-muted">{{ __('A log only. Pay a repair with Money Out.') }}</span></div>
                    </form>
                @endif
                @forelse ($asset->events as $event)
                    <div class="d-flex justify-content-between border-top py-2 small"><span><strong>{{ $event->type === 'transfer' ? __('Transfer') : __('Maintenance') }}</strong> · {{ fmt_date($event->date) }} · {{ $event->description }}</span><span dir="ltr">{{ $event->cost !== null ? money($event->cost) : '' }}</span></div>
                @empty
                    <p class="text-muted small mb-0">{{ __('Nothing logged yet.') }}</p>
                @endforelse
            </div>
        </div>
        @if ($manage)
            <form method="POST" action="{{ route('assets.dispose', ['id' => $asset->id]) }}" class="card mb-0 no-print" data-confirm="{{ __('Dispose of this asset?') }}" data-confirm-text="{{ __('Its cost and depreciation leave the books and the gain or loss is posted. This cannot be undone here.') }}" data-confirm-ok="{{ __('Dispose') }}">
                @csrf
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Sell or scrap') }}</h5></div>
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-3"><label class="form-label small" for="date">{{ __('Date') }}</label><x-date name="date" :value="today_iso()" :required="true" id="date" /></div>
                        <div class="col-6 col-md-3"><label class="form-label small" for="proceeds">{{ __('Sold for') }}</label><input type="text" inputmode="decimal" class="form-control" id="proceeds" name="proceeds" value="0" dir="ltr" autocomplete="off"></div>
                        <div class="col-8 col-md-4"><label class="form-label small" for="account_id">{{ __('Money received into') }}</label>
                            <select class="form-select" id="account_id" name="account_id" data-plain><option value="">—</option>@foreach ($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select></div>
                        <div class="col-4 col-md-2"><button type="submit" class="btn btn-danger w-100">{{ __('Dispose') }}</button></div>
                    </div>
                    <p class="small text-muted mt-2 mb-0">{{ __('Book value now: :amount. Selling above it is a gain, below it a loss. Leave 0 for an asset that is scrapped.', ['amount' => money($asset->bookValue())]) }}</p>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
