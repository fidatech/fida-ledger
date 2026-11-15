@extends('layouts.app', ['title' => __('API Access')])

@section('content')
<x-page-header :title="__('API Access')" icon="code" :subtitle="__('Keys for other programs, such as a mobile or desktop app, to work with this company.')" :crumbs="[[__('Settings')], [__('API Access')]]" />

@if (session('api_key'))
    <div class="alert alert-success">
        <strong>{{ __('Your new key') }}</strong>
        <p class="mb-2">{{ __('Copy it now and keep it somewhere safe. It is shown only once.') }}</p>
        <input type="text" class="form-control font-monospace" value="{{ session('api_key') }}" readonly dir="ltr" onclick="this.select()" aria-label="{{ __('Your new key') }}">
    </div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Keys') }}</h5></div>
            <div class="card-body">
                @if ($tokens->isEmpty())
                    <p class="text-muted mb-0">{{ __('No key was made yet.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle w-100">
                            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Works as') }}</th><th>{{ __('Access') }}</th><th>{{ __('Last used') }}</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($tokens as $token)
                                    <tr class="{{ $token->revoked_at ? 'text-muted' : '' }}">
                                        <td><span class="fw-semibold">{{ $token->name }}</span><span class="d-block small text-muted">{{ fmt_date($token->created_at) }}</span></td>
                                        <td>{{ $token->user?->name }}</td>
                                        <td>{{ $token->can_write ? __('Read and write') : __('Read only') }}</td>
                                        <td>{{ $token->last_used_at ? fmt_date($token->last_used_at) : __('Never') }}</td>
                                        <td class="text-end">
                                            @if ($token->revoked_at)
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Revoked') }}</span>
                                            @else
                                                <form method="POST" action="{{ route('settings.api.destroy', ['id' => $token->id]) }}" data-confirm="{{ __('Revoke this key?') }}" data-confirm-text="{{ __('Programs using it can no longer sign in. This cannot be undone.') }}" data-confirm-ok="{{ __('Revoke') }}">@csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Revoke') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="small text-muted mb-0 mt-3">{{ __('A key works in this company only, with the role of the person who made it. It stops working when that person loses access or the subscription ends.') }}</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <form method="POST" action="{{ route('settings.api.store') }}" class="card">
            @csrf
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Make a key') }}</h5></div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <div class="mb-3">
                    <label class="form-label" for="name">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="100" required placeholder="{{ __('For example: mobile app') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="access">{{ __('Access') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="access" name="access" required data-plain>
                        <option value="read" @selected(old('access', 'read') === 'read')>{{ __('Read only') }}</option>
                        <option value="write" @selected(old('access') === 'write')>{{ __('Read and write') }}</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><x-icon name="key" class="me-1" /> {{ __('Make the key') }}</button>
                <p class="small text-muted mt-3 mb-0">{{ __('How to use the keys is described in docs/API.md.') }}</p>
            </div>
        </form>
    </div>
</div>
@endsection
