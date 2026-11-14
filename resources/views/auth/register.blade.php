@extends('layouts.auth', ['title' => __('Create an account')])

@section('content')
<div class="auth-card">
    <div class="ac-icon"><x-icon name="user-plus" /></div>
    <h2>{{ __('Create an account') }}</h2>
    <p class="ac-sub">{{ __('Step 1 of 3. Next you choose your version and set up your company.') }}</p>

    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="name">{{ __('Full name') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" maxlength="150" autocomplete="name" required autofocus>
            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="email">{{ __('app.auth.email') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="username" required>
            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">{{ __('app.auth.password') }}</label>
            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" minlength="8" required>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label class="form-label" for="password_confirmation">{{ __('Password again') }}</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" minlength="8" required>
        </div>
        <button type="submit" class="btn btn-gradient btn-lg w-100">{{ __('Continue') }} <x-icon name="arrow-right" class="ms-1" /></button>
    </form>
    <p class="text-center small text-muted mt-4 mb-0">{{ __('Already registered?') }} <a class="text-decoration-none fw-semibold" href="{{ route('login') }}">{{ __('app.auth.submit') }}</a></p>
</div>
@endsection
