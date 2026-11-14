@extends('layouts.app', ['title' => __('Asset Register')])

@section('content')
<x-page-header :title="__('Asset Register')" icon="building" :subtitle="__('Amounts in :currency', ['currency' => base_currency()])">
    <a class="btn btn-outline-soft" href="{{ route('asset-categories.index') }}">{{ __('Asset Categories') }}</a>
    @if (can_do('assets.manage'))
        <a class="btn btn-primary" href="{{ route('assets.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add asset') }}</a>
    @endif
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('Original cost') }}</div><div class="fs-4 fw-bold" dir="ltr">{{ money($cost) }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('Accumulated depreciation') }}</div><div class="fs-4 fw-bold" dir="ltr">{{ money($depreciated) }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('Net book value') }}</div><div class="fs-4 fw-bold" dir="ltr">{{ money(\App\Support\Dec::sub($cost, $depreciated)) }}</div></div></div></div>
    @if (can_do('assets.manage'))
        <div class="col-6 col-md-3">
            <form method="POST" action="{{ route('assets.depreciate') }}" class="card h-100 mb-0" data-confirm="{{ __('Run depreciation?') }}" data-confirm-text="{{ __('One month is posted for every active asset. A month that was already run is skipped.') }}" data-confirm-ok="{{ __('Run') }}" data-confirm-style="primary">
                @csrf
                <div class="card-body">
                    <label class="small fw-semibold text-muted mb-1" for="period">{{ __('Depreciation for the month') }}</label>
                    <div class="d-flex gap-2"><input type="month" class="form-control form-control-sm" id="period" name="period" value="{{ $period }}" required><button type="submit" class="btn btn-sm btn-primary">{{ __('Run') }}</button></div>
                </div>
            </form>
        </div>
    @endif
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search by name, location or person…') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-8 col-md-auto">
                <select class="form-select" name="status" aria-label="{{ __('Status') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('Every status') }}</option>
                    <option value="active" @selected($status === 'active')>{{ __('In use') }}</option>
                    <option value="disposed" @selected($status === 'disposed')>{{ __('Disposed') }}</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button></div>
        </form>
        @if ($assets->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="building" /></div><h3>{{ __('Nothing found') }}</h3></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Asset') }}</th><th>{{ __('Category') }}</th><th>{{ __('Acquired') }}</th><th class="text-end">{{ __('Cost') }}</th><th class="text-end">{{ __('Depreciated') }}</th><th class="text-end">{{ __('Book value') }}</th><th>{{ __('Where / who') }}</th></tr></thead>
                <tbody>
                    @foreach ($assets as $asset)
                        <tr class="{{ $asset->status === 'disposed' ? 'text-muted' : '' }}">
                            <td dir="ltr">{{ $asset->code() }}</td>
                            <td><a class="text-decoration-none fw-semibold" href="{{ route('assets.show', ['id' => $asset->id]) }}">{{ $asset->name }}</a>@if ($asset->status === 'disposed') <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Disposed') }}</span>@endif</td>
                            <td>{{ $asset->category?->name }}</td>
                            <td class="text-nowrap">{{ fmt_date($asset->acquired_on) }}</td>
                            <td class="text-end" dir="ltr">{{ money($asset->cost) }}</td>
                            <td class="text-end" dir="ltr">{{ money($asset->depreciated) }}</td>
                            <td class="text-end fw-semibold" dir="ltr">{{ $asset->status === 'disposed' ? '' : money($asset->bookValue()) }}</td>
                            <td class="small">{{ trim($asset->location.' · '.$asset->assigned_to, ' ·') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $assets->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
