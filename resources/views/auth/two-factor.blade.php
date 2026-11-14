@extends('layouts.auth', ['title' => __('Two-factor sign-in')])

@section('content')
<div class="auth-card">
    <div class="ac-icon"><x-icon name="shield-halved" /></div>
    <h2>{{ __('Two-factor sign-in') }}</h2>
    <p class="ac-sub">{{ __('Type the 6-digit code from your authenticator app, or one of your recovery codes.') }}</p>

    <form method="POST" action="{{ route('two-factor.verify') }}">
        @csrf
        <div class="mb-4">
            <label class="form-label" for="code">{{ __('Code') }}</label>
            <input type="text" id="code" name="code" class="form-control form-control-lg text-center @error('code') is-invalid @enderror" inputmode="numeric" autocomplete="one-time-code" maxlength="20" dir="ltr" required autofocus>
            @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-gradient btn-lg w-100">{{ __('Continue') }} <x-icon name="arrow-right" class="ms-1" /></button>
    </form>
    <p class="text-center small mt-4 mb-0"><a class="text-decoration-none" href="{{ route('login') }}">{{ __('Back to sign in') }}</a></p>
</div>
@endsection
