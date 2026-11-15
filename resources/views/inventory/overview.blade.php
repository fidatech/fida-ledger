@extends('layouts.app', ['title' => __('Stock Overview')])

@section('content')
<x-page-header :title="__('Stock Overview')" icon="warehouse" :subtitle="__('Value at average cost, in :currency', ['currency' => base_currency()])">
    @if (can_do('reports.export'))
        <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><x-icon name="file-arrow-down" class="me-1" /> {{ __('Excel (CSV)') }}</a>
    @endif
    @if (can_do('inventory.manage'))
        <a class="btn btn-outline-soft" href="{{ route('inventory.change', ['mode' => 'transfer']) }}"><x-icon name="right-left" class="me-1" /> {{ __('Stock Transfer') }}</a>
        <a class="btn btn-outline-soft" href="{{ route('inventory.count-sheet') }}"><x-icon name="clipboard-check" class="me-1" /> {{ __('Physical Count') }}</a>
        <a class="btn btn-primary" href="{{ route('inventory.change', ['mode' => 'adjust']) }}"><x-icon name="plus" class="me-1" /> {{ __('Stock Adjustment') }}</a>
    @endif
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('Stock value') }}</div><div class="fs-4 fw-bold" dir="ltr">{{ money($stockValue, base_currency()) }}</div></div></div></div>
    <div class="col-6 col-md-4"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('Inventory in the ledger') }}</div><div class="fs-4 fw-bold" dir="ltr">{{ money($ledgerValue, base_currency()) }}</div></div></div></div>
    <div class="col-12 col-md-4"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('Reconciliation') }}</div>
        @if ($reconciled)
            <div class="fw-bold text-success"><x-icon name="circle-check" class="me-1" /> {{ __('Stock and ledger agree') }}</div>
        @else
            <div class="fw-bold text-danger"><x-icon name="triangle-exclamation" class="me-1" /> {{ __('Difference') }}: <span dir="ltr">{{ money(\App\Support\Dec::sub($stockValue, $ledgerValue)) }}</span></div>
            <div class="small text-muted">{{ __('A manual entry on the Inventory account causes this.') }}</div>
        @endif
    </div></div></div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search by name, code or barcode…') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-6 col-md-auto">
                <select class="form-select" name="warehouse" aria-label="{{ __('Warehouse') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('All warehouses') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="low" value="1" id="low" @checked(request()->boolean('low')) onchange="this.form.submit()"><label class="form-check-label" for="low">{{ __('Low stock only') }}</label></div></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button></div>
        </form>
        @if ($rows->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="boxes-stacked" /></div><h3>{{ __('Nothing found') }}</h3><p>{{ __('Products marked "Keep stock" appear here.') }}</p></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Code (SKU)') }}</th><th class="text-end">{{ __('On hand') }}</th><th class="text-end">{{ __('Average cost') }}</th><th class="text-end">{{ __('Value') }}</th><th class="text-end">{{ __('Reorder level') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $low = \App\Support\Dec::cmp($row->quantity, $row->reorder_level) <= 0 && \App\Support\Dec::isPositive($row->reorder_level);
                        @endphp
                        <tr>
                            <td><a class="text-decoration-none fw-semibold" href="{{ route('inventory.movements', ['product' => $row->id]) }}">{{ $row->name }}</a>@if ($low) <span class="badge bg-danger-subtle text-danger-emphasis">{{ __('Low') }}</span>@endif</td>
                            <td dir="ltr">{{ $row->sku }}</td>
                            <td class="text-end fw-semibold" dir="ltr">{{ \App\Inventory\Stock::qty($row->quantity) }}</td>
                            <td class="text-end" dir="ltr">{{ \App\Support\Dec::isPositive($row->quantity) ? money(\App\Support\Dec::div($row->value, $row->quantity)) : '' }}</td>
                            <td class="text-end" dir="ltr">{{ money($row->value) }}</td>
                            <td class="text-end" dir="ltr">{{ \App\Inventory\Stock::qty($row->reorder_level) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $rows->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
