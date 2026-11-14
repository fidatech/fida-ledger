@extends('layouts.app', ['title' => $account ? __('Edit account') : __('Add :name', ['name' => __($kinds[$kind]['label'])])])

@section('content')
@php
    $definition = $kinds[$kind] ?? null;
    $isParty = in_array($kind, ['customer', 'supplier', 'saraf', 'personal', 'loan_receivable', 'loan_payable'], true);
    $field = fn (string $name, $default = null) => old($name, $account?->{$name} ?? $default);
@endphp
<x-page-header :title="$account ? __('Edit :name', ['name' => $account->name]) : __('Add :name', ['name' => __($definition['label'])])" :icon="$definition['icon'] ?? 'address-book'" :crumbs="[[__('Accounts'), route('accounts.index', ['kind' => $kind])], [$account ? __('Edit') : __('Add')]]" />

<form method="POST" action="{{ $account ? route('accounts.update', ['id' => $account->id]) : route('accounts.store') }}" class="card">
    @csrf
    @if ($account)
        @method('PUT')
    @endif
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <div class="row g-3">
            @unless ($account)
                <div class="col-12 col-md-4">
                    <label class="form-label" for="kind">{{ __('Kind of account') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="kind" name="kind" required data-plain onchange="location.href='{{ route('accounts.create') }}?kind='+this.value">
                        @foreach ($kinds as $value => $option)
                            <option value="{{ $value }}" @selected($kind === $value)>{{ __($option['label']) }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div class="col-12 col-md-{{ $account ? 6 : 5 }}">
                <label class="form-label" for="name">{{ __('Name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ $field('name') }}" maxlength="150" required autofocus>
            </div>
            @if (! $account && ! empty($definition['currency']))
                <div class="col-12 col-md-3">
                    <label class="form-label" for="currency">{{ __('Currency') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="currency" name="currency" required data-plain>
                        @foreach (currencies() as $code)
                            <option value="{{ $code }}" @selected(old('currency', base_currency()) === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('This account holds one currency. It cannot be changed later.') }}</div>
                </div>
            @endif
            @if ($isParty)
                <div class="col-12 col-md-4">
                    <label class="form-label" for="phone">{{ __('Phone') }}</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="{{ $field('phone') }}" maxlength="50" dir="ltr">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="email">{{ __('Email') }}</label>
                    <input type="email" class="form-control" id="email" name="email" value="{{ $field('email') }}" maxlength="190" dir="ltr">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="address">{{ __('Address') }}</label>
                    <input type="text" class="form-control" id="address" name="address" value="{{ $field('address') }}" maxlength="255">
                </div>
            @endif
            <div class="col-12">
                <label class="form-label" for="notes">{{ __('Notes') }}</label>
                <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000">{{ $field('notes') }}</textarea>
            </div>

            @unless ($account)
                <div class="col-12"><div class="border-top pt-3 mt-2"><h6 class="mb-0 text-uppercase small fw-semibold text-muted">{{ __('Opening balance (optional)') }}</h6><div class="form-text">{{ __('Leave the amount empty when the account starts at zero.') }}</div></div></div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="opening_amount">{{ __('Amount') }}</label>
                    <input type="text" inputmode="decimal" class="form-control" id="opening_amount" name="opening_amount" value="{{ old('opening_amount') }}" autocomplete="off" dir="ltr">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="opening_side">{{ __('Side') }}</label>
                    <select class="form-select" id="opening_side" name="opening_side" data-plain>
                        @php
                            $normal = config('ledger.classes.'.$definition['class']);
                        @endphp
                        <option value="debit" @selected(old('opening_side', $normal) === 'debit')>{{ $isParty ? __('Debit (they owe us)') : __('Debit') }}</option>
                        <option value="credit" @selected(old('opening_side', $normal) === 'credit')>{{ $isParty ? __('Credit (we owe them)') : __('Credit') }}</option>
                    </select>
                </div>
                @if (empty($definition['currency']))
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="opening_currency">{{ __('Currency') }}</label>
                        <select class="form-select" id="opening_currency" name="opening_currency" data-plain>
                            @foreach (currencies() as $code)
                                <option value="{{ $code }}" @selected(old('opening_currency', base_currency()) === $code)>{{ $code }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-6 col-md-2">
                    <label class="form-label" for="opening_rate">{{ __('Rate') }}</label>
                    <input type="text" inputmode="decimal" class="form-control" id="opening_rate" name="opening_rate" value="{{ old('opening_rate') }}" autocomplete="off" dir="ltr">
                    <div class="form-text">{{ __('Only for a currency other than :currency: 1 unit = ? :currency', ['currency' => base_currency()]) }}</div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="opening_date">{{ __('As of') }}</label>
                    <x-date name="opening_date" :value="old('opening_date', today_iso())" id="opening_date" />
                </div>
            @endunless
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ $account ? route('accounts.show', ['id' => $account->id]) : route('accounts.index', ['kind' => $kind]) }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endsection
