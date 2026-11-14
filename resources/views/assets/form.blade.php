@extends('layouts.app', ['title' => __('Add asset')])

@section('content')
<x-page-header :title="__('Add asset')" icon="building" :crumbs="[[__('Asset Register'), route('assets.index')], [__('Add')]]" />

<form method="POST" action="{{ route('assets.store') }}" class="card">
    @csrf
    <div class="card-body">
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="name">{{ __('Name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="255" required autofocus>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="asset_category_id">{{ __('Category') }}</label>
                <select class="form-select" id="asset_category_id" name="asset_category_id" data-plain onchange="var m=this.options[this.selectedIndex].getAttribute('data-life'); if (m) document.getElementById('useful_life_months').value = m;">
                    <option value="">—</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" data-life="{{ $category->useful_life_months }}" @selected((int) old('asset_category_id') === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="acquired_on">{{ __('Acquired on') }} <span class="text-danger">*</span></label>
                <x-date name="acquired_on" :value="old('acquired_on', today_iso())" :required="true" id="acquired_on" />
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="cost">{{ __('Cost') }} ({{ base_currency() }}) <span class="text-danger">*</span></label>
                <input type="text" inputmode="decimal" class="form-control" id="cost" name="cost" value="{{ old('cost') }}" dir="ltr" autocomplete="off" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="residual_value">{{ __('Residual value') }}</label>
                <input type="text" inputmode="decimal" class="form-control" id="residual_value" name="residual_value" value="{{ old('residual_value', '0') }}" dir="ltr" autocomplete="off">
                <div class="form-text">{{ __('What it will still be worth at the end of its life.') }}</div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="useful_life_months">{{ __('Useful life (months)') }} <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="useful_life_months" name="useful_life_months" value="{{ old('useful_life_months', 60) }}" min="1" max="1200" dir="ltr" required>
                <div class="form-text">{{ __('Straight-line depreciation: the same amount every month.') }}</div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="paid_from_account_id">{{ __('Paid from') }}</label>
                <select class="form-select" id="paid_from_account_id" name="paid_from_account_id">
                    <option value="">{{ __('Already owned (opening balance)') }}</option>
                    @foreach ($payAccounts as $account)
                        <option value="{{ $account->id }}" @selected((int) old('paid_from_account_id') === $account->id)>{{ $account->name }} · {{ $account->kindLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="location">{{ __('Location') }}</label>
                <input type="text" class="form-control" id="location" name="location" value="{{ old('location') }}" maxlength="255">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="assigned_to">{{ __('Assigned to') }}</label>
                <input type="text" class="form-control" id="assigned_to" name="assigned_to" value="{{ old('assigned_to') }}" maxlength="255">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="notes">{{ __('Notes') }}</label>
                <input type="text" class="form-control" id="notes" name="notes" value="{{ old('notes') }}" maxlength="2000">
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('assets.index') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endsection
