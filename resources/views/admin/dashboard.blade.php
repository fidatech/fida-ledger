@extends('layouts.admin', ['title' => __('Platform Dashboard')])

@section('content')
<x-page-header :title="__('Platform Dashboard')" icon="gauge-high">
    <a class="btn btn-primary" href="{{ route('admin.payments.index') }}"><x-icon name="receipt" class="me-1" /> {{ __('Payment approvals') }}@if ($counts['waiting']) ({{ $counts['waiting'] }})@endif</a>
</x-page-header>

@php
    $cards = [
        [__('Payments waiting'), 'hourglass-half', 'warning', $counts['waiting']],
        [__('Active companies'), 'building', 'success', $counts['active']],
        [__('Waiting for first payment'), 'credit-card', 'info', $counts['pending']],
        [__('Subscription ended'), 'triangle-exclamation', 'danger', $counts['expired']],
        [__('Suspended'), 'ban', 'orange', $counts['suspended']],
        [__('Users'), 'users', 'primary', $counts['users']],
    ];
@endphp
<div class="row g-3 mb-3">
    @foreach ($cards as [$label, $icon, $tone, $count])
        <div class="col-6 col-xl-4">
            <div class="card h-100 mb-0"><div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:38px;height:38px"><x-icon :name="$icon" /></span>
                    <span class="small fw-semibold text-muted">{{ $label }}</span>
                </div>
                <div class="fs-3 fw-bold lh-1">{{ $count }}</div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">{{ __('Newest companies') }}</h5>
        <span class="small text-muted">{{ __('Approved payments') }}: <span dir="ltr">{{ sums($revenue) }}</span></span>
    </div>
    <div class="card-body">
        @if ($latest->isEmpty())
            <p class="text-muted mb-0">{{ __('No company has registered yet.') }}</p>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Company') }}</th><th>{{ __('Version') }}</th><th>{{ __('Status') }}</th><th>{{ __('Subscription ends') }}</th><th>{{ __('Registered') }}</th></tr></thead>
                <tbody>
                    @foreach ($latest as $company)
                        <tr>
                            <td class="fw-semibold">{{ $company->name }}</td>
                            <td>{{ $company->plan?->name }}</td>
                            <td>{{ $company->isUsable() ? __('Active') : ($company->isExpired() ? __('Ended') : __(ucfirst($company->status))) }}</td>
                            <td>{{ fmt_date($company->subscription_ends_at) }}</td>
                            <td>{{ fmt_date($company->created_at) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
