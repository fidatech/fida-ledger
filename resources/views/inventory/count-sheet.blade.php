@extends('layouts.app', ['title' => __('Physical Stock Count')])

@section('content')
<x-page-header :title="__('Physical Stock Count')" icon="clipboard-check" :subtitle="$warehouse->name" :crumbs="[[__('Stock Overview'), route('inventory.overview')], [__('Physical Stock Count')]]">
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print the sheet') }}</button>
    <a class="btn btn-outline-soft" href="{{ route('inventory.change', ['mode' => 'count']) }}">{{ __('Count one product') }}</a>
</x-page-header>

<form method="GET" action="{{ route('inventory.count-sheet') }}" class="card mb-3 d-print-none">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label" for="warehouse">{{ __('Warehouse') }}</label>
                <select class="form-select" id="warehouse" name="warehouse" data-plain>
                    @foreach ($warehouses as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $warehouse->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
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
            <div class="col-4 col-md-3"><button type="submit" class="btn btn-outline-soft w-100">{{ __('Show') }}</button></div>
        </div>
    </div>
</form>

<form method="POST" action="{{ route('inventory.count-sheet.store') }}" class="card" data-confirm="{{ __('Save this count?') }}" data-confirm-text="{{ __('Every product with a typed count is adjusted to it. Stock and the ledger are updated at once.') }}" data-confirm-ok="{{ __('Save') }}" data-confirm-style="primary">
    @csrf
    <input type="hidden" name="warehouse_id" value="{{ $warehouse->id }}">
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        @if ($products->isEmpty())
            <p class="text-muted mb-0">{{ __('Products with "Keep stock" switched on appear here.') }}</p>
        @else
            <div class="alert alert-info d-print-none">{{ __('Type what you counted. Leave a box empty for a product you did not count: it is not changed. A shortage is posted as an expense; a surplus lowers it.') }}</div>
            <div class="row g-3 mb-3 d-print-none">
                <div class="col-12 col-sm-4">
                    <label class="form-label" for="date">{{ __('Date') }} <span class="text-danger">*</span></label>
                    <x-date name="date" id="date" :value="old('date', today_iso())" required />
                </div>
                <div class="col-12 col-sm-8">
                    <label class="form-label" for="reason">{{ __('Notes') }}</label>
                    <input type="text" class="form-control" id="reason" name="reason" value="{{ old('reason') }}" maxlength="200" placeholder="{{ __('Physical count') }}">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle w-100">
                    <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Code (SKU)') }}</th><th>{{ __('Unit') }}</th><th class="text-end">{{ __('In the books') }}</th><th style="width:160px">{{ __('Counted') }}</th></tr></thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr>
                                <td>{{ $product->name }}</td>
                                <td dir="ltr">{{ $product->sku }}</td>
                                <td>{{ $product->unit?->symbol ?: $product->unit?->name }}</td>
                                <td class="text-end" dir="ltr">{{ \App\Support\Dec::format($levels[$product->id] ?? '0') }}</td>
                                <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="counted[{{ $product->id }}]" value="{{ old('counted.'.$product->id) }}" autocomplete="off" dir="ltr" aria-label="{{ __('Counted') }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-print-none">
                <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save the count') }}</button>
                <div class="mt-3">{{ $products->links('partials.pagination') }}</div>
                @if ($products->hasPages())
                    <p class="small text-muted">{{ __('Save each page before opening the next one.') }}</p>
                @endif
            </div>
        @endif
    </div>
</form>
@endsection
