@extends('layouts.app', ['title' => __('My Profile & Security')])

@section('content')
<x-page-header :title="__('My Profile & Security')" icon="user-shield" :subtitle="$user->email" />

@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@if ($recoveryCodes)
    <div class="alert alert-warning">
        <strong class="d-block mb-1">{{ __('Save these recovery codes now. They are shown only this once.') }}</strong>
        <p class="mb-2">{{ __('Each code signs you in one time if you lose your phone.') }}</p>
        <div class="row g-2" dir="ltr">@foreach ($recoveryCodes as $code)<div class="col-6 col-md-3"><code class="fs-6">{{ $code }}</code></div>@endforeach</div>
    </div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <form method="POST" action="{{ route('profile.update') }}" class="card">
            @csrf
            @method('PUT')
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Profile') }}</h5></div>
            <div class="card-body">
                <label class="form-label" for="name">{{ __('Full name') }}</label>
                <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="150" required>
            </div>
            <div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary">{{ __('Save') }}</button></div>
        </form>

        <form method="POST" action="{{ route('profile.password') }}" class="card mb-0">
            @csrf
            @method('PUT')
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Change Password') }}</h5></div>
            <div class="card-body">
                <div class="mb-3"><label class="form-label" for="current_password">{{ __('Current password') }}</label><input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required></div>
                <div class="mb-3"><label class="form-label" for="password">{{ __('New password') }}</label><input type="password" class="form-control" id="password" name="password" autocomplete="new-password" minlength="8" required></div>
                <div><label class="form-label" for="password_confirmation">{{ __('New password again') }}</label><input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="8" required></div>
            </div>
            <div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary">{{ __('Change password') }}</button></div>
        </form>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ __('Two-factor sign-in') }}</h5>
                @if ($user->hasTwoFactor())<span class="badge bg-success-subtle text-success-emphasis">{{ __('On') }}</span>@else<span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Off') }}</span>@endif
            </div>
            <div class="card-body">
                <p class="small text-muted">{{ __('After your password, sign-in also asks for a code from an authenticator app on your phone (Google Authenticator, Microsoft Authenticator, Authy). A stolen password alone then opens nothing.') }}</p>

                @if ($user->hasTwoFactor())
                    <form method="POST" action="{{ route('profile.two-factor.disable') }}" data-confirm="{{ __('Switch two-factor off?') }}" data-confirm-ok="{{ __('Switch off') }}">
                        @csrf
                        @method('DELETE')
                        <label class="form-label" for="tf_password">{{ __('Your password, to switch it off') }}</label>
                        <div class="d-flex gap-2"><input type="password" class="form-control" id="tf_password" name="current_password" autocomplete="current-password" required><button type="submit" class="btn btn-danger text-nowrap">{{ __('Switch off') }}</button></div>
                    </form>
                @elseif ($pendingSecret)
                    <ol class="small ps-3">
                        <li class="mb-2">{{ __('In the authenticator app choose "Enter a setup key" and type this key (account: :email, type: time based):', ['email' => $user->email]) }}
                            <div class="my-2"><code class="fs-5 user-select-all" dir="ltr">{{ implode(' ', str_split($pendingSecret, 4)) }}</code></div>
                            <a class="text-decoration-none" href="{{ $otpUri }}">{{ __('On this phone? Open the app with this link.') }}</a>
                        </li>
                        <li>{{ __('Type the 6-digit code the app shows now:') }}</li>
                    </ol>
                    <form method="POST" action="{{ route('profile.two-factor.confirm') }}" class="d-flex gap-2">
                        @csrf
                        <input type="text" class="form-control" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" dir="ltr" required aria-label="{{ __('Code') }}">
                        <button type="submit" class="btn btn-primary text-nowrap">{{ __('Switch on') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('profile.two-factor.start') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary"><x-icon name="shield-halved" class="me-1" /> {{ __('Set up two-factor sign-in') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
