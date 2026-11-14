@extends('layouts.app', ['title' => $order ? __('Edit :code', ['code' => $order->code()]) : __('New purchase order')])

@section('content')
<x-page-header :title="$order ? __('Edit :code', ['code' => $order->code()]) : __('New purchase order')" icon="cart-flatbed" :crumbs="[[__('Purchase Orders'), route('purchases.index')], [$order ? __('Edit') : __('New')]]" />

@if ($suppliers->isEmpty())
    <div class="card"><div class="card-body"><div class="empty-state">
        <div class="es-icon"><x-icon name="truck-field" /></div><h3>{{ __('Add a supplier first') }}</h3>
        @if (can_do('accounts.manage'))<a class="btn btn-primary" href="{{ route('accounts.create', ['kind' => 'supplier']) }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => __('Supplier')]) }}</a>@endif
    </div></div></div>
@else
<form method="POST" action="{{ $order ? route('purchases.update', ['id' => $order->id]) : route('purchases.store') }}" class="card">
    @csrf
    @if ($order) @method('PUT') @endif
    <div class="card-body">
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="supplier_account_id">{{ __('Supplier') }} <span class="text-danger">*</span></label>
                <select class="form-select" id="supplier_account_id" name="supplier_account_id" required>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((int) old('supplier_account_id', $order?->supplier_account_id) === $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            @include('partials.doc-head', ['doc' => $order, 'secondDate' => 'expected_date', 'secondLabel' => __('Expected on')])
        </div>
        @include('partials.doc-lines', ['lines' => old('lines') ?? ($order ? $order->lines->map->only(['product_id', 'description', 'quantity', 'unit_price'])->all() : []), 'priceField' => 'purchase_price'])
        <div class="mt-3">
            <label class="form-label" for="notes">{{ __('Notes') }}</label>
            <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000">{{ old('notes', $order?->notes) }}</textarea>
        </div>
        <p class="small text-muted mt-3 mb-0">{{ __('An order posts nothing. Stock rises when goods are received; the supplier is credited when the bill is entered.') }}</p>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('purchases.index') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endif
@endsection
