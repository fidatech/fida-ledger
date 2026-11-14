@extends('layouts.admin', ['title' => __('Companies')])

@section('content')
<x-page-header :title="__('Companies')" icon="building" :subtitle="__('Status and subscription only. The books of a company are not shown here.')" />

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search by name or email…') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-8 col-md-auto">
                <select class="form-select" name="status" aria-label="{{ __('Status') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('Every status') }}</option>
                    @foreach (['active' => __('Active'), 'pending' => __('Waiting for first payment'), 'ended' => __('Subscription ended'), 'suspended' => __('Suspended')] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button></div>
        </form>

        @if ($companies->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="building" /></div><h3>{{ __('Nothing found') }}</h3></div>
        @else
            <table class="table table-hover align-middle w-100">
                <thead><tr><th>{{ __('Company') }}</th><th>{{ __('Version') }}</th><th>{{ __('Users') }}</th><th>{{ __('Status') }}</th><th>{{ __('Subscription ends') }}</th><th>{{ __('Registered') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($companies as $company)
                        <tr>
                            <td><span class="fw-semibold">{{ $company->name }}</span><span class="d-block small text-muted" dir="ltr">{{ $company->email }} {{ $company->phone }}</span></td>
                            <td>{{ $company->plan?->name }}</td>
                            <td>{{ $company->users_count }}</td>
                            <td>
                                @if ($company->status === 'suspended')<span class="badge bg-danger-subtle text-danger-emphasis">{{ __('Suspended') }}</span>
                                @elseif ($company->status === 'pending')<span class="badge bg-warning-subtle text-warning-emphasis">{{ __('Waiting for first payment') }}</span>
                                @elseif ($company->isUsable())<span class="badge bg-success-subtle text-success-emphasis">{{ __('Active') }}</span>
                                @else<span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Subscription ended') }}</span>@endif
                            </td>
                            <td>{{ fmt_date($company->subscription_ends_at) }}</td>
                            <td>{{ fmt_date($company->created_at) }}</td>
                            <td class="text-end">
                                @if ($company->status !== 'pending' && auth('admin')->user()->canDecide())
                                    <form method="POST" action="{{ route('admin.companies.suspend', ['id' => $company->id]) }}" data-confirm="{{ $company->status === 'suspended' ? __('Restore :name?', ['name' => $company->name]) : __('Suspend :name?', ['name' => $company->name]) }}" data-confirm-text="{{ __('Its users cannot open the books while it is suspended. Nothing is deleted.') }}" data-confirm-ok="{{ $company->status === 'suspended' ? __('Restore') : __('Suspend') }}">@csrf
                                        <button type="submit" class="btn btn-sm {{ $company->status === 'suspended' ? 'btn-success' : 'btn-danger' }}">{{ $company->status === 'suspended' ? __('Restore') : __('Suspend') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $companies->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
