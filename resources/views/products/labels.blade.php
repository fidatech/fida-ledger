@extends('layouts.app', ['title' => __('Barcode Labels')])

@section('content')
<x-page-header :title="__('Barcode Labels')" icon="barcode" :subtitle="__('Print labels with the barcode, name and price of your products.')" :crumbs="[[__('All Products'), route('products.index', ['type' => 'product'])], [__('Barcode Labels')]]" />

<form method="GET" action="{{ route('products.labels') }}" class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label" for="q">{{ __('Search') }}</label>
                <input type="search" class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="{{ __('Search by name, code or barcode…') }}">
            </div>
            <div class="col-8 col-md-4">
                <label class="form-label" for="category">{{ __('Category') }}</label>
                <select class="form-select" id="category" name="category" data-plain>
                    <option value="">{{ __('All') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(request()->integer('category') === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-4 col-md-3"><button type="submit" class="btn btn-outline-soft w-100"><x-icon name="magnifying-glass" class="me-1" /> {{ __('Show') }}</button></div>
        </div>
    </div>
</form>

<form method="GET" action="{{ route('products.labels') }}" target="_blank" class="card">
    <input type="hidden" name="print" value="1">
    <input type="hidden" name="q" value="{{ request('q') }}">
    <input type="hidden" name="category" value="{{ request('category') }}">
    <div class="card-body">
        @if ($products->isEmpty())
            <p class="text-muted mb-0">{{ __('Nothing found') }}</p>
        @else
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="columns">{{ __('Labels in a row') }}</label>
                    <select class="form-select" id="columns" name="columns" data-plain>
                        @foreach ([2, 3, 4, 5] as $n)<option value="{{ $n }}" @selected($n === 3)>{{ $n }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="hidden" name="price" value="0">
                        <input class="form-check-input" type="checkbox" id="price" name="price" value="1" checked>
                        <label class="form-check-label" for="price">{{ __('Print the price') }}</label>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100">
                    <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Barcode') }}</th><th class="text-end">{{ __('Selling price') }}</th><th style="width:130px">{{ __('Labels') }}</th></tr></thead>
                    <tbody>
                        @foreach ($products as $product)
                            @php $code = $product->barcode ?: $product->sku; $fits = \App\Support\Barcode::fits($code); @endphp
                            <tr>
                                <td><span class="fw-semibold">{{ $product->name }}</span>@if ($product->category)<span class="d-block small text-muted">{{ $product->category->name }}</span>@endif</td>
                                <td dir="ltr">@if ($fits){{ $code }}@else<span class="text-muted">{{ $code ? __('Cannot be printed as a barcode') : __('No barcode or code') }}</span>@endif</td>
                                <td class="text-end" dir="ltr">{{ money($product->sale_price) }}</td>
                                <td><input type="number" class="form-control form-control-sm" name="copies[{{ $product->id }}]" min="0" max="100" step="1" value="0" @disabled(! $fits) aria-label="{{ __('Labels') }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="small text-muted">{{ __('The barcode is the product\'s barcode or, without one, its code. At most 500 labels are printed at once.') }}</p>
            <button type="submit" class="btn btn-primary"><x-icon name="print" class="me-1" /> {{ __('Show the labels') }}</button>
        @endif
    </div>
</form>
@endsection
