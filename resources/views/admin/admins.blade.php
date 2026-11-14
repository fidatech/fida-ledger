@extends('layouts.admin', ['title' => __('Administrators')])

@section('content')
@php
    $super = auth('admin')->user()->isSuper();
@endphp
<x-page-header :title="__('Administrators')" icon="user-shield" :subtitle="__('Super administrators change prices, versions and administrators. Managers approve payments and suspend companies. Viewers only look.')" />

@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card"><div class="card-body">
            <table class="table table-hover align-middle w-100 mb-0">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Role') }}</th><th>{{ __('Access') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($admins as $admin)
                        <tr>
                            <td><span class="fw-semibold">{{ $admin->name }}</span><span class="d-block small text-muted" dir="ltr">{{ $admin->email }}</span></td>
                            @if ($super && $admin->id !== auth('admin')->id())
                                <td><select class="form-select form-select-sm" name="role" form="admin-{{ $admin->id }}" data-plain aria-label="{{ __('Role') }}">@foreach (\App\Models\Admin::ROLES as $value => $label)<option value="{{ $value }}" @selected(($admin->role ?? 'super') === $value)>{{ __($label) }}</option>@endforeach</select></td>
                                <td><select class="form-select form-select-sm" name="is_active" form="admin-{{ $admin->id }}" data-plain aria-label="{{ __('Access') }}"><option value="1" @selected($admin->is_active)>{{ __('On') }}</option><option value="0" @selected(! $admin->is_active)>{{ __('Off') }}</option></select></td>
                                <td class="text-end"><form method="POST" action="{{ route('admin.admins.update', ['id' => $admin->id]) }}" id="admin-{{ $admin->id }}">@csrf @method('PUT')<button type="submit" class="btn btn-sm btn-light-secondary">{{ __('Save') }}</button></form></td>
                            @else
                                <td>{{ $admin->roleLabel() }}</td><td>{{ $admin->is_active ? __('On') : __('Off') }}</td><td></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
    </div>
    @if ($super)
        <div class="col-12 col-lg-4">
            <form method="POST" action="{{ route('admin.admins.store') }}" class="card">
                @csrf
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Add an administrator') }}</h5></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label" for="name">{{ __('Full name') }}</label><input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="150" required></div>
                    <div class="mb-3"><label class="form-label" for="email">{{ __('Email') }}</label><input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" dir="ltr" required></div>
                    <div class="mb-3"><label class="form-label" for="role">{{ __('Role') }}</label><select class="form-select" id="role" name="role" data-plain>@foreach (\App\Models\Admin::ROLES as $value => $label)<option value="{{ $value }}" @selected(old('role', 'manager') === $value)>{{ __($label) }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label" for="password">{{ __('Password (at least 10 characters)') }}</label><input type="password" class="form-control" id="password" name="password" autocomplete="new-password" minlength="10" required></div>
                    <button type="submit" class="btn btn-primary">{{ __('Add') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
