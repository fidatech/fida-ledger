@extends('layouts.app', ['title' => __('Company Profile')])

@section('content')
<x-page-header :title="__('Company Profile')" icon="building" :subtitle="__('Base currency: :currency', ['currency' => $company->base_currency])" />

<form method="POST" action="{{ route('settings.company.update') }}" class="card">
    @csrf
    @method('PUT')
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="name">{{ __('Company name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $company->name) }}" maxlength="150" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="phone">{{ __('Phone') }}</label>
                <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', $company->phone) }}" maxlength="50" dir="ltr">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="email">{{ __('Email') }}</label>
                <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $company->email) }}" maxlength="190" dir="ltr">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="address">{{ __('Address') }}</label>
                <input type="text" class="form-control" id="address" name="address" value="{{ old('address', $company->address) }}" maxlength="255">
                <div class="form-text">{{ __('Name, address, phone and email are printed on invoices.') }}</div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="country">{{ __('Country') }}</label>
                <input type="text" class="form-control" id="country" name="country" value="{{ old('country', $company->country) }}" maxlength="100">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="timezone">{{ __('Time zone') }} <span class="text-danger">*</span></label>
                <select class="form-select" id="timezone" name="timezone" required>
                    @foreach ($timezones as $zone)
                        <option value="{{ $zone }}" @selected(old('timezone', $company->timezone) === $zone)>{{ $zone }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12"><div class="border-top pt-3 mt-2"><h6 class="mb-0 text-uppercase small fw-semibold text-muted">{{ __('Accounting period lock') }}</h6></div></div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="locked_until">{{ __('Books are closed up to and including') }}</label>
                <x-date name="locked_until" :value="old('locked_until', $company->locked_until)" id="locked_until" />
                <div class="form-text">{{ __('Nothing can be posted or cancelled on or before this day. Leave empty to keep all periods open. Every change is written to the audit log.') }}</div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end">
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>

<form method="POST" action="{{ route('settings.company.close') }}" class="card" data-confirm="{{ __('Close the financial year?') }}" data-confirm-text="{{ __('Income and expenses up to that day are moved to Retained earnings and the books are locked up to that day.') }}" data-confirm-ok="{{ __('Close the year') }}" data-confirm-style="primary">
    @csrf
    <div class="card-header"><h5 class="card-title mb-0">{{ __('Year-end closing') }}</h5></div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label" for="close_date">{{ __('Last day of the year to close') }} <span class="text-danger">*</span></label>
                <x-date name="date" :value="(date('Y') - 1).'-12-31'" :required="true" id="close_date" />
            </div>
            <div class="col-12 col-md-4"><button type="submit" class="btn btn-outline-soft"><x-icon name="lock" class="me-1" /> {{ __('Close the year') }}</button></div>
        </div>
        <p class="small text-muted mt-2 mb-0">{{ __('Profit & Loss for the closed year still shows its result. The Balance Sheet shows it inside Retained earnings.') }}</p>
    </div>
</form>
@endsection
