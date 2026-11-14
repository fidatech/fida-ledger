@extends('layouts.admin', ['title' => $company->name])

@section('content')
@php
    $super = auth('admin')->user()->isSuper();
@endphp
<x-page-header :title="$company->name" icon="building" :subtitle="trim($company->email.' · '.$company->phone.' · '.$company->country, ' ·')" :crumbs="[[__('Companies'), route('admin.companies.index')], [$company->name]]" />

@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <form method="POST" action="{{ route('admin.companies.update', ['id' => $company->id]) }}" class="card" data-confirm="{{ __('Save this subscription?') }}" data-confirm-text="{{ __('A change of version switches the company\'s modules. Nothing is charged here.') }}" data-confirm-ok="{{ __('Save') }}" data-confirm-style="primary">
            @csrf
            @method('PUT')
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Version and subscription') }}</h5></div>
            <div class="card-body">
                <p class="small">{{ __('Status') }}: <strong>{{ $company->status === 'suspended' ? __('Suspended') : ($company->status === 'pending' ? __('Waiting for first payment') : ($company->isUsable() ? __('Active') : __('Subscription ended'))) }}</strong> · {{ __('Setup fee') }}: {{ $company->setup_fee_paid ? __('Paid') : __('Not paid') }}</p>
                <div class="mb-3">
                    <label class="form-label" for="plan_id">{{ __('Version') }}</label>
                    <select class="form-select" id="plan_id" name="plan_id" data-plain @disabled(! $super)>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected($company->plan_id === $plan->id)>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="subscription_ends_at">{{ __('Subscription ends on') }}</label>
                    <x-date name="subscription_ends_at" :value="$company->subscription_ends_at" id="subscription_ends_at" :disabled="! $super" />
                    <div class="form-text">{{ __('A payment approval sets this by itself. Change it here only to correct a mistake or to give extra days.') }}</div>
                </div>
                <div>
                    <label class="form-label" for="note">{{ __('Reason (kept in the audit log)') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="note" name="note" maxlength="255" required @disabled(! $super)>
                </div>
            </div>
            @if ($super)<div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary">{{ __('Save') }}</button></div>@endif
        </form>
        <div class="card mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Active modules') }}</h5></div>
            <div class="card-body">
                @if ($modules)
                    <ul class="mb-0">@foreach ($modules as $module)<li>{{ __($module['name']) }}</li>@endforeach</ul>
                @else
                    <p class="text-muted mb-0">{{ __('Core accounting only.') }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Users') }} ({{ $members->count() }})</h5></div>
            <div class="card-body">
                <table class="table table-sm w-100 mb-0">
                    <tbody>@foreach ($members as $member)<tr><td>{{ $member->name }}</td><td dir="ltr" class="small text-muted">{{ $member->email }}</td><td>{{ __(config('ledger.roles.'.$member->pivot->role.'.label', $member->pivot->role)) }}</td><td>{{ $member->pivot->is_active ? '' : __('Off') }}</td></tr>@endforeach</tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Payments') }}</h5></div>
            <div class="card-body">
                @if ($payments->isEmpty())
                    <p class="text-muted mb-0">{{ __('No payment was sent yet.') }}</p>
                @else
                    <table class="table table-sm w-100 mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('For') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Period') }}</th></tr></thead>
                        <tbody>@foreach ($payments as $payment)<tr><td>{{ fmt_date($payment->created_at) }}</td><td>{{ $payment->plan?->name }} · {{ $payment->kind === 'first' ? __('First year') : __('Renewal') }}</td><td class="text-end" dir="ltr">{{ money($payment->amount, $payment->currency) }}</td><td>{{ __(ucfirst($payment->status)) }}</td><td class="small">{{ $payment->period_start ? fmt_date($payment->period_start).' – '.fmt_date($payment->period_end) : '' }}</td></tr>@endforeach</tbody>
                    </table>
                @endif
            </div>
        </div>
        <div class="card mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('What administrators did here') }}</h5></div>
            <div class="card-body">
                @forelse ($logs as $log)
                    <div class="small border-bottom py-1">{{ $log->created_at?->format('j M Y H:i') }} · <strong>{{ $log->action }}</strong> @if ($log->details)<span class="text-muted" dir="ltr">{{ json_encode($log->details, JSON_UNESCAPED_UNICODE) }}</span>@endif</div>
                @empty
                    <p class="text-muted mb-0">{{ __('Nothing yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
