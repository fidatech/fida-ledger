@extends('layouts.app', ['title' => __('Currencies')])

@section('content')
<x-page-header :title="__('Currencies')" icon="coins" :subtitle="__('Base currency: :currency', ['currency' => base_currency()])">
    <a class="btn btn-outline-soft" href="{{ route('settings.rates') }}">{{ __('Exchange Rates') }}</a>
</x-page-header>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card"><div class="card-body">
            <table class="table table-hover align-middle w-100 mb-0">
                <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Symbol') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($currencies as $currency)
                        <tr>
                            <td class="fw-semibold">{{ $currency->code }}</td>
                            <td>{{ __($currency->name) }}</td>
                            <td>{{ $currency->symbol }}</td>
                            <td>@if ($currency->code === base_currency())<span class="badge bg-primary-subtle text-primary-emphasis">{{ __('Base currency') }}</span>@elseif ($currency->is_active)<span class="badge bg-success-subtle text-success-emphasis">{{ __('On') }}</span>@else<span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Off') }}</span>@endif</td>
                            <td class="text-end">
                                @if ($currency->code !== base_currency())
                                    <form method="POST" action="{{ route('settings.currencies.toggle', ['id' => $currency->id]) }}">@csrf
                                        <button type="submit" class="btn btn-sm btn-light-secondary">{{ $currency->is_active ? __('Switch off') : __('Switch on') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
    </div>
    <div class="col-12 col-lg-4">
        <form method="POST" action="{{ route('settings.currencies.store') }}" class="card">
            @csrf
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Add a currency') }}</h5></div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <div class="mb-3">
                    <label class="form-label" for="code">{{ __('Code (3 letters)') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control text-uppercase" id="code" name="code" value="{{ old('code') }}" minlength="3" maxlength="3" dir="ltr" required placeholder="AED">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="name">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="60" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="symbol">{{ __('Symbol') }}</label>
                    <input type="text" class="form-control" id="symbol" name="symbol" value="{{ old('symbol') }}" maxlength="8">
                </div>
                <button type="submit" class="btn btn-primary"><x-icon name="plus" class="me-1" /> {{ __('Add') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
