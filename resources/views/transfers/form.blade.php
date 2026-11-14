@extends('layouts.app', ['title' => $exchange ? __('Currency Exchange') : __('Account Transfer')])

@section('content')
<x-page-header :title="$exchange ? __('Currency Exchange') : __('Account Transfer')" :icon="$exchange ? 'coins' : 'right-left'" :crumbs="[[__('Transfers & Exchange'), route('transfers.index')], [__('New')]]" />

@if ($accounts->count() < 2)
    <div class="card"><div class="card-body"><div class="empty-state">
        <div class="es-icon"><x-icon name="building-columns" /></div>
        <h3>{{ __('You need two cash or bank accounts') }}</h3>
        <p>{{ $exchange ? __('Open a cash or bank account in the other currency first.') : __('Open a second cash or bank account first.') }}</p>
        @if (can_do('accounts.manage'))
            <a class="btn btn-primary" href="{{ route('accounts.create', ['kind' => 'cash']) }}"><x-icon name="plus" class="me-1" /> {{ __('Add account') }}</a>
        @endif
    </div></div></div>
@else
<form method="POST" action="{{ route('transfers.store') }}" class="card" data-confirm="{{ __('Record this?') }}" data-confirm-text="{{ __('It is posted to the ledger at once.') }}" data-confirm-ok="{{ __('Record') }}" data-confirm-style="primary">
    @csrf
    <input type="hidden" name="once" value="{{ old('once', $once) }}">
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="from_account_id">{{ $exchange ? __('We give from') : __('From account') }} <span class="text-danger">*</span></label>
                <select class="form-select" id="from_account_id" name="from_account_id" required>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((int) old('from_account_id') === $account->id)>{{ $account->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="to_account_id">{{ $exchange ? __('We receive into') : __('To account') }} <span class="text-danger">*</span></label>
                <select class="form-select" id="to_account_id" name="to_account_id" required>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((int) old('to_account_id') === $account->id)>{{ $account->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="from_amount">{{ $exchange ? __('Amount given') : __('Amount') }} <span class="text-danger">*</span></label>
                <input type="text" inputmode="decimal" class="form-control" id="from_amount" name="from_amount" value="{{ old('from_amount') }}" autocomplete="off" dir="ltr" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="to_amount">{{ __('Amount received') }} @if ($exchange)<span class="text-danger">*</span>@endif</label>
                <input type="text" inputmode="decimal" class="form-control" id="to_amount" name="to_amount" value="{{ old('to_amount') }}" autocomplete="off" dir="ltr" @required($exchange)>
                <div class="form-text">{{ __('Needed when the two accounts hold different currencies.') }}</div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="date">{{ __('Date') }} <span class="text-danger">*</span></label>
                <x-date name="date" :value="old('date', today_iso())" :required="true" id="date" />
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="to_rate">{{ __('Rate of the currency received') }}</label>
                <input type="text" inputmode="decimal" class="form-control" id="to_rate" name="to_rate" value="{{ old('to_rate') }}" autocomplete="off" dir="ltr">
                <div class="form-text">{{ __('Only when neither currency is :currency: 1 unit received = ? :currency.', ['currency' => base_currency()]) }}</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">{{ __('Description') }}</label>
                <input type="text" class="form-control" id="description" name="description" value="{{ old('description') }}" maxlength="255">
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('transfers.index') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Record') }}</button>
    </div>
</form>
@endif
@endsection
