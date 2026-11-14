@extends('layouts.app', ['title' => $order ? __('Edit :code', ['code' => $order->code()]) : ($type === 'quotation' ? __('New quotation') : __('New sales order'))])

@section('content')
@php
    $quote = $type === 'quotation';
    $defaultIncome = $incomeAccounts->firstWhere('system_key', 'sales')?->id ?? $incomeAccounts->first()?->id;
@endphp
<x-page-header :title="$order ? __('Edit :code', ['code' => $order->code()]) : ($quote ? __('New quotation') : __('New sales order'))" icon="file-signature" :crumbs="[[$quote ? __('Quotations') : __('Sales Orders'), route('sales.index', ['doc' => $type])], [$order ? __('Edit') : __('New')]]" />

@if ($customers->isEmpty())
    <div class="card"><div class="card-body"><div class="empty-state">
        <div class="es-icon"><x-icon name="user-tag" /></div><h3>{{ __('Add a customer first') }}</h3>
        @if (can_do('accounts.manage'))<a class="btn btn-primary" href="{{ route('accounts.create', ['kind' => 'customer']) }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => __('Customer')]) }}</a>@endif
    </div></div></div>
@else
<form method="POST" action="{{ $order ? route('sales.update', ['doc' => $type, 'id' => $order->id]) : route('sales.store', ['doc' => $type]) }}" class="card">
    @csrf
    @if ($order) @method('PUT') @endif
    <div class="card-body">
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="customer_account_id">{{ __('Customer') }} <span class="text-danger">*</span></label>
                <select class="form-select" id="customer_account_id" name="customer_account_id" required>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) old('customer_account_id', $order?->customer_account_id) === $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            @include('partials.doc-head', ['doc' => $order, 'secondDate' => 'valid_until', 'secondLabel' => $quote ? __('Valid until') : __('Deliver by')])
        </div>
        @include('partials.doc-lines', ['lines' => old('lines') ?? ($order ? $order->lines->map->only(['product_id', 'description', 'quantity', 'unit_price', 'discount', 'tax_rate', 'income_account_id'])->all() : []), 'priceField' => 'sale_price', 'selling' => true, 'defaultIncome' => $defaultIncome])
        <div class="mt-3">
            <label class="form-label" for="notes">{{ __('Notes') }}</label>
            <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000">{{ old('notes', $order?->notes) }}</textarea>
        </div>
        <p class="small text-muted mt-3 mb-0">{{ $quote ? __('A quotation posts nothing. When the customer accepts, confirm it: it becomes a sales order.') : __('An order posts nothing. Stock and cost of goods sold move on delivery; income is recognized by the invoice.') }}</p>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('sales.index', ['doc' => $type]) }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endif
@endsection
