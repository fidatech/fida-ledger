@extends('layouts.plain', ['title' => __('Set up your company')])

@section('content')
@php
    $chosen = old('plan', request('plan', 'business'));
@endphp
<div class="mb-3">
    <h1 class="h3 mb-1">{{ $first ? __('Set up your company') : __('Add a company') }}</h1>
    <p class="text-muted mb-0">{{ __('Choose your version, then tell us about the company. Each version comes with its modules. There is no free plan and no trial: the company opens when its payment is approved.') }}</p>
</div>

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('onboarding.store') }}">
    @csrf

    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">{{ __('1. Version') }}</h5></div>
        <div class="card-body">
            <div class="row g-3">
                @foreach ($plans as $plan)
                    <div class="col-12 col-md-6">
                        <label class="card h-100 mb-0" style="cursor:pointer">
                            <div class="card-body">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="plan" id="plan-{{ $plan->code }}" value="{{ $plan->code }}" @checked($chosen === $plan->code) required>
                                    <span class="form-check-label fw-bold fs-5">{{ $plan->name }}</span>
                                </div>
                                <p class="small text-muted mt-2 mb-3">{{ __($plan->description) }}</p>
                                <div class="d-flex justify-content-between small"><span>{{ __('One-time setup fee') }}</span><strong dir="ltr">{{ money($plan->setup_fee, $plan->currency) }}</strong></div>
                                <div class="d-flex justify-content-between small"><span>{{ __('Annual subscription') }}</span><strong dir="ltr">{{ money($plan->annual_fee, $plan->currency) }}</strong></div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between"><span>{{ __('First year') }}</span><strong dir="ltr">{{ money(\App\Support\Dec::add($plan->setup_fee, $plan->annual_fee, 2), $plan->currency) }}</strong></div>
                                <div class="d-flex justify-content-between small text-muted"><span>{{ __('Every year after') }}</span><span dir="ltr">{{ money($plan->annual_fee, $plan->currency) }}</span></div>
                                <hr class="my-2">
                                {{-- the modules come with the version: they are shown, not chosen --}}
                                <div class="small fw-semibold mb-1">{{ __('Modules') }}</div>
                                <ul class="list-unstyled small mb-0">
                                    @foreach (config('billing.core_modules') as $name)
                                        <li class="d-flex gap-2"><span class="text-success"><x-icon name="circle-check" /></span><span>{{ __($name) }}</span></li>
                                    @endforeach
                                    @foreach ($modules as $key => $module)
                                        @if (in_array($plan->code, $includes[$key] ?? [], true))
                                            <li class="d-flex gap-2"><span class="text-success"><x-icon name="circle-check" /></span><span class="fw-semibold">{{ __($module['name']) }}</span></li>
                                        @else
                                            <li class="d-flex gap-2 text-muted"><span><x-icon name="circle-xmark" /></span><span class="text-decoration-line-through">{{ __($module['name']) }}</span></li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">{{ __('2. Company details') }}</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="name">{{ __('Company name') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="150" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="type">{{ __('Company type') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="type" name="type" required data-plain>
                        @foreach (config('ledger.company_types') as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', 'trading') === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="base_currency">{{ __('Base currency') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="base_currency" name="base_currency" required data-plain>
                        @foreach (config('ledger.currencies') as $code => [$currencyName])
                            <option value="{{ $code }}" @selected(old('base_currency', 'AFN') === $code)>{{ $code }} · {{ __($currencyName) }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('Reports are prepared in this currency. It cannot be changed later.') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="country">{{ __('Country') }}</label>
                    <input type="text" class="form-control" id="country" name="country" value="{{ old('country', 'Afghanistan') }}" maxlength="100">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="timezone">{{ __('Time zone') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="timezone" name="timezone" required>
                        @foreach ($timezones as $zone)
                            <option value="{{ $zone }}" @selected(old('timezone', 'Asia/Kabul') === $zone)>{{ $zone }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="phone">{{ __('Phone') }}</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone') }}" maxlength="50" dir="ltr">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            @unless ($first)
                <a class="btn btn-light-secondary" href="{{ route('dashboard') }}">{{ __('Cancel') }}</a>
            @endunless
            <button type="submit" class="btn btn-primary">{{ __('Continue to payment') }} <x-icon name="arrow-right" class="ms-1" /></button>
        </div>
    </div>
</form>

@endsection
