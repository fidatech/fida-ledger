@extends('layouts.app', ['title' => ['transfer' => __('Stock Transfer'), 'adjust' => __('Stock Adjustment'), 'count' => __('Physical Count')][$mode]])

@section('content')
@php
    $titles = ['transfer' => __('Stock Transfer'), 'adjust' => __('Stock Adjustment'), 'count' => __('Physical Count')];
@endphp
<x-page-header :title="$titles[$mode]" icon="warehouse" :crumbs="[[__('Stock Overview'), route('inventory.overview')], [$titles[$mode]]]" />

<form method="POST" action="{{ route('inventory.store') }}" class="card" data-confirm="{{ __('Save this?') }}" data-confirm-text="{{ __('Stock and the ledger are updated at once.') }}" data-confirm-ok="{{ __('Save') }}" data-confirm-style="primary">
    @csrf
    <input type="hidden" name="mode" value="{{ $mode }}">
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <div class="alert alert-info">
            @if ($mode === 'transfer') {{ __('Stock moves between two warehouses at its cost. It makes no profit and no loss.') }}
            @elseif ($mode === 'count') {{ __('Enter what you counted. The difference from the books is adjusted: a shortage is an expense, a surplus lowers it.') }}
            @else {{ __('Enter a positive quantity to add stock, or a negative one (for example -3) to remove damaged or lost stock.') }}
            @endif
        </div>
        <div class="row g-3">
            <div class="col-12 col-md-5">
                <label class="form-label" for="product_id">{{ __('Product') }} <span class="text-danger">*</span></label>
                <select class="form-select" id="product_id" name="product_id" required>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((int) old('product_id', request('product')) === $product->id)>{{ $product->name }}{{ $product->sku ? ' · '.$product->sku : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-{{ $mode === 'transfer' ? 2 : 3 }}">
                <label class="form-label" for="warehouse_id">{{ $mode === 'transfer' ? __('From warehouse') : __('Warehouse') }} <span class="text-danger">*</span></label>
                <select class="form-select" id="warehouse_id" name="warehouse_id" required data-plain>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id') === $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($mode === 'transfer')
                <div class="col-6 col-md-2">
                    <label class="form-label" for="to_warehouse_id">{{ __('To warehouse') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="to_warehouse_id" name="to_warehouse_id" required data-plain>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((int) old('to_warehouse_id') === $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-6 col-md-2">
                <label class="form-label" for="quantity">{{ $mode === 'count' ? __('Counted quantity') : __('Quantity') }} <span class="text-danger">*</span></label>
                <input type="text" inputmode="decimal" class="form-control" id="quantity" name="quantity" value="{{ old('quantity') }}" autocomplete="off" dir="ltr" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="date">{{ __('Date') }} <span class="text-danger">*</span></label>
                <x-date name="date" :value="old('date', today_iso())" :required="true" id="date" />
            </div>
            @if ($mode !== 'transfer')
                <div class="col-6 col-md-3">
                    <label class="form-label" for="unit_cost">{{ __('Unit cost of added stock') }} ({{ base_currency() }})</label>
                    <input type="text" inputmode="decimal" class="form-control" id="unit_cost" name="unit_cost" value="{{ old('unit_cost') }}" autocomplete="off" dir="ltr">
                    <div class="form-text">{{ __('Empty uses the purchase price of the product. Stock removed always leaves at the average cost.') }}</div>
                </div>
            @endif
            <div class="col-12 col-md-{{ $mode === 'adjust' ? 6 : 9 }}">
                <label class="form-label" for="reason">{{ __('Reason') }}</label>
                <input type="text" class="form-control" id="reason" name="reason" value="{{ old('reason') }}" maxlength="200">
            </div>
            @if ($mode === 'adjust')
                <div class="col-12 col-md-3">
                    <div class="form-check form-switch mt-md-4 pt-md-2">
                        <input type="hidden" name="opening" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="opening" name="opening" value="1" @checked(old('opening'))>
                        <label class="form-check-label" for="opening">{{ __('This is opening stock') }}</label>
                    </div>
                    <div class="form-text">{{ __('Stock you already had when you started: it is posted against Opening balances, not as income.') }}</div>
                </div>
            @endif
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('inventory.overview') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endsection
