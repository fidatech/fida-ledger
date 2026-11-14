@extends('layouts.app', ['title' => __('Exchange Rates')])

@section('content')
<x-page-header :title="__('Exchange Rates')" icon="coins" :subtitle="__('What one unit of each currency is worth in :currency.', ['currency' => base_currency()])" />

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <form method="POST" action="{{ route('settings.rates.store') }}" class="card">
            @csrf
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Add a rate') }}</h5></div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <div class="mb-3">
                    <label class="form-label" for="currency">{{ __('Currency') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="currency" name="currency" required data-plain>
                        @foreach ($foreign as $code)
                            <option value="{{ $code }}" @selected(old('currency') === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="date">{{ __('Date') }} <span class="text-danger">*</span></label>
                    <x-date name="date" :value="old('date', today_iso())" :required="true" id="date" />
                </div>
                <div class="mb-3">
                    <label class="form-label" for="rate">{{ __('1 unit = ? :currency', ['currency' => base_currency()]) }} <span class="text-danger">*</span></label>
                    <input type="text" inputmode="decimal" class="form-control" id="rate" name="rate" value="{{ old('rate') }}" autocomplete="off" dir="ltr" required>
                </div>
                <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
            </div>
        </form>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Rate history') }}</h5></div>
            <div class="card-body">
                @if ($rates->isEmpty())
                    <p class="text-muted mb-0">{{ __('No rate was saved yet. You can also type a rate on each transaction.') }}</p>
                @else
                    <table class="table table-hover w-100">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Currency') }}</th><th class="text-end">{{ __('Rate') }}</th></tr></thead>
                        <tbody>
                            @foreach ($rates as $rate)
                                <tr><td>{{ fmt_date($rate->date) }}</td><td>{{ $rate->currency }}</td><td class="text-end" dir="ltr">{{ rtrim(rtrim($rate->rate, '0'), '.') }} {{ base_currency() }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $rates->links('partials.pagination') }}
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
