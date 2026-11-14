@extends('layouts.app', ['title' => $direction === 'in' ? __('Record Money In') : __('Record Money Out')])

@section('content')
@php
    $in = $direction === 'in';
    $foreign = $cashAccounts->contains(fn ($account) => $account->currency !== base_currency());
@endphp
<x-page-header :title="$in ? __('Record Money In') : __('Record Money Out')" :icon="$in ? 'arrow-down' : 'arrow-up'" :crumbs="[[$in ? __('Money In') : __('Money Out'), route('money.index', ['direction' => $direction])], [__('Record')]]" />

<form method="POST" action="{{ route('money.store', ['direction' => $direction]) }}" class="card" enctype="multipart/form-data" data-confirm="{{ $in ? __('Record this Money In?') : __('Record this Money Out?') }}" data-confirm-text="{{ __('It is posted to the ledger at once. A mistake is corrected by cancelling it, not by editing.') }}" data-confirm-ok="{{ __('Record') }}" data-confirm-style="primary">
    @csrf
    <input type="hidden" name="once" value="{{ old('once', $once) }}">
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="kind">{{ $in ? __('What is this money?') : __('What is this payment for?') }} <span class="text-danger">*</span></label>
                {{-- choosing a kind reloads the form with the accounts that fit it --}}
                <select class="form-select" id="kind" name="kind" required data-plain onchange="location.href='{{ route('money.create', ['direction' => $direction]) }}?kind='+this.value">
                    @foreach ($kinds as $value => $option)
                        <option value="{{ $value }}" @selected($kind === $value)>{{ __($option['label']) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="counter_account_id">{{ $in ? __('Received from / for') : __('Paid to / for') }} <span class="text-danger">*</span>
                    @if ($newKind && can_do('accounts.manage'))
                        <a class="small text-decoration-none ms-2" href="{{ route('accounts.create', ['kind' => $newKind]) }}">+ {{ __('New') }}</a>
                    @endif
                </label>
                <select class="form-select" id="counter_account_id" name="counter_account_id" required>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($counterAccounts as $account)
                        <option value="{{ $account->id }}" @selected((int) old('counter_account_id', $picked) === $account->id)>{{ $account->name }} · {{ $account->kindLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="cash_account_id">{{ $in ? __('Into cash or bank account') : __('From cash or bank account') }} <span class="text-danger">*</span></label>
                <select class="form-select" id="cash_account_id" name="cash_account_id" required>
                    @foreach ($cashAccounts as $account)
                        <option value="{{ $account->id }}" @selected((int) old('cash_account_id') === $account->id)>{{ $account->label() }}</option>
                    @endforeach
                </select>
                <div class="form-text">{{ __('The amount is in the currency of this account.') }}</div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="amount">{{ __('Amount') }} <span class="text-danger">*</span></label>
                <input type="text" inputmode="decimal" class="form-control" id="amount" name="amount" value="{{ old('amount') }}" autocomplete="off" dir="ltr" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="date">{{ __('Date') }} <span class="text-danger">*</span></label>
                <x-date name="date" :value="old('date', today_iso())" :required="true" id="date" />
            </div>
            @if ($foreign)
                <div class="col-12 col-md-4">
                    <label class="form-label" for="rate">{{ __('Exchange rate') }}</label>
                    <input type="text" inputmode="decimal" class="form-control" id="rate" name="rate" value="{{ old('rate') }}" autocomplete="off" dir="ltr">
                    <div class="form-text">{{ __('Only when the account is not in :currency: 1 unit = ? :currency. Empty uses the saved rate of that day.', ['currency' => base_currency()]) }}</div>
                </div>
            @endif
            <div class="col-12 col-md-{{ $foreign ? 8 : 12 }}">
                <label class="form-label" for="description">{{ __('Description') }}</label>
                <input type="text" class="form-control" id="description" name="description" value="{{ old('description') }}" maxlength="255">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="reference">{{ __('Reference') }}</label>
                <input type="text" class="form-control" id="reference" name="reference" value="{{ old('reference') }}" maxlength="100">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="attachment">{{ __('Attachment (photo or PDF)') }}</label>
                <input type="file" class="form-control" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf">
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('money.index', ['direction' => $direction]) }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn {{ $in ? 'btn-success' : 'btn-danger' }}"><x-icon name="check" class="me-1" /> {{ $in ? __('Record Money In') : __('Record Money Out') }}</button>
    </div>
</form>
@endsection
