@extends('layouts.app', ['title' => __('Users & Roles')])

@section('content')
<x-page-header :title="__('Users & Roles')" icon="user-group" :subtitle="$seats ? __(':count users allowed in your version', ['count' => $seats]) : null" />

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-body">
                <table class="table table-hover align-middle w-100">
                    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Role') }}</th><th>{{ __('Access') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($members as $member)
                            @php
                                $locked = $member->pivot->role === 'owner' || $member->id === auth()->id();
                            @endphp
                            <tr>
                                <td><span class="fw-semibold">{{ $member->name }}</span><span class="d-block small text-muted" dir="ltr">{{ $member->email }}</span></td>
                                @if ($locked)
                                    <td>{{ __(config('ledger.roles.'.$member->pivot->role.'.label')) }}</td>
                                    <td><span class="badge bg-success-subtle text-success-emphasis">{{ __('On') }}</span></td>
                                    <td></td>
                                @else
                                    <td>
                                        <select class="form-select form-select-sm" name="role" form="member-{{ $member->id }}" data-plain aria-label="{{ __('Role') }}">
                                            @foreach ($roles as $value => $label)
                                                <option value="{{ $value }}" @selected($member->pivot->role === $value)>{{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="is_active" form="member-{{ $member->id }}" data-plain aria-label="{{ __('Access') }}">
                                            <option value="1" @selected($member->pivot->is_active)>{{ __('On') }}</option>
                                            <option value="0" @selected(! $member->pivot->is_active)>{{ __('Off') }}</option>
                                        </select>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('settings.users.update', ['id' => $member->id]) }}" id="member-{{ $member->id }}">@csrf @method('PUT')
                                            <button type="submit" class="btn btn-sm btn-light-secondary">{{ __('Save') }}</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="small text-muted mb-0">{{ __('Switching access off keeps the person\'s history; he can no longer open this company.') }}</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <form method="POST" action="{{ route('settings.users.store') }}" class="card">
            @csrf
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Add a user') }}</h5></div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <div class="mb-3">
                    <label class="form-label" for="name">{{ __('Full name') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="150" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">{{ __('Email') }} <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" maxlength="190" dir="ltr" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="role">{{ __('Role') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="role" name="role" required data-plain>
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', 'accountant') === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">{{ __('Password') }}</label>
                    <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" minlength="8">
                    <div class="form-text">{{ __('Only for a person who has no account yet. Someone who already uses Fida Ledger keeps his own password.') }}</div>
                </div>
                <button type="submit" class="btn btn-primary"><x-icon name="user-plus" class="me-1" /> {{ __('Add') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
