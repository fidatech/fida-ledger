@extends('layouts.admin', ['title' => __('Users')])

@section('content')
<x-page-header :title="__('Users')" icon="users" :subtitle="__('Everyone who can sign in to a company.')" />

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search by name or email…') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button></div>
        </form>
        @if ($users->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="users" /></div><h3>{{ __('Nothing found') }}</h3></div>
        @else
            <table class="table table-hover align-middle w-100">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Companies') }}</th><th>{{ __('Two-factor') }}</th><th>{{ __('Email confirmed') }}</th><th>{{ __('Registered') }}</th><th class="text-end">{{ __('Access') }}</th></tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td><span class="fw-semibold">{{ $user->name }}</span><span class="d-block small text-muted" dir="ltr">{{ $user->email }}</span></td>
                            <td>{{ $user->companies_count }}</td>
                            <td>{{ $user->hasTwoFactor() ? __('On') : '' }}</td>
                            <td>{{ $user->email_verified_at ? fmt_date($user->email_verified_at) : '' }}</td>
                            <td>{{ fmt_date($user->created_at) }}</td>
                            <td class="text-end">
                                @if (auth('admin')->user()->canDecide())
                                    <form method="POST" action="{{ route('admin.users.toggle', ['id' => $user->id]) }}" data-confirm="{{ $user->is_active ? __('Block :name from signing in?', ['name' => $user->name]) : __('Let :name sign in again?', ['name' => $user->name]) }}" data-confirm-ok="{{ $user->is_active ? __('Block') : __('Restore') }}">@csrf
                                        <button type="submit" class="btn btn-sm {{ $user->is_active ? 'btn-light-secondary' : 'btn-success' }}">{{ $user->is_active ? __('Block') : __('Restore') }}</button>
                                    </form>
                                @else
                                    {{ $user->is_active ? __('On') : __('Blocked') }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $users->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
