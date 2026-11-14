@extends('layouts.app', ['title' => __('Stock Movements')])

@section('content')
@php
    $types = ['purchase' => __('Purchase'), 'purchase_return' => __('Purchase return'), 'sale' => __('Sale'), 'sale_return' => __('Sales return'), 'transfer_in' => __('Transfer in'), 'transfer_out' => __('Transfer out'), 'adjustment' => __('Adjustment'), 'opening' => __('Opening stock')];
@endphp
<x-page-header :title="__('Stock Movements')" icon="arrows-rotate" :crumbs="[[__('Stock Overview'), route('inventory.overview')], [__('Movements')]]" />

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md">
                <select class="form-select" name="product" aria-label="{{ __('Product') }}" onchange="this.form.submit()">
                    <option value="">{{ __('All products') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(request()->integer('product') === $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <select class="form-select" name="warehouse" aria-label="{{ __('Warehouse') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('All warehouses') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(request()->integer('warehouse') === $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <select class="form-select" name="type" aria-label="{{ __('Kind') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('All kinds') }}</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto"><x-date name="from" :value="query_date('from')" title="{{ __('From') }}" aria-label="{{ __('From') }}" /></div>
            <div class="col-6 col-md-auto"><x-date name="until" :value="query_date('until')" title="{{ __('Until') }}" aria-label="{{ __('Until') }}" /></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>
        @if ($movements->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="arrows-rotate" /></div><h3>{{ __('Nothing found') }}</h3></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Product') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Reference') }}</th><th class="text-end">{{ __('In') }}</th><th class="text-end">{{ __('Out') }}</th><th class="text-end">{{ __('Value') }}</th></tr></thead>
                <tbody>
                    @foreach ($movements as $movement)
                        @php
                            $in = \App\Support\Dec::isPositive($movement->quantity);
                        @endphp
                        <tr>
                            <td class="text-nowrap">{{ fmt_date($movement->date) }}</td>
                            <td>{{ $movement->product?->name }}</td>
                            <td>{{ $movement->warehouse?->name }}</td>
                            <td>{{ $types[$movement->type] ?? $movement->type }}</td>
                            <td>{{ $movement->reference }}@if ($movement->description) <span class="small text-muted">{{ $movement->description }}</span>@endif</td>
                            <td class="text-end text-success" dir="ltr">{{ $in ? \App\Inventory\Stock::qty($movement->quantity) : '' }}</td>
                            <td class="text-end text-danger" dir="ltr">{{ $in ? '' : \App\Inventory\Stock::qty(\App\Support\Dec::abs($movement->quantity)) }}</td>
                            <td class="text-end" dir="ltr">{{ money($movement->value) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $movements->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
