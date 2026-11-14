@extends('layouts.app', ['title' => __('Subscription & Billing')])

@section('content')
@php
    $state = match (true) {
        $company->status === \App\Models\Company::SUSPENDED => ['danger', 'ban', __('This company is suspended'), __('Please contact support.')],
        $waiting !== null && ! $company->isUsable() => ['warning', 'hourglass-half', __('Payment pending'), __('We received your payment details. The company opens as soon as an administrator confirms the money arrived.')],
        $company->isUsable() => ['success', 'circle-check', __('Active'), __('Your subscription runs until :date (:days days left).', ['date' => fmt_date($company->subscription_ends_at), 'days' => $company->daysLeft()])],
        $company->isExpired() => ['danger', 'triangle-exclamation', __('Subscription ended'), __('It ended on :date. Renew to open your books again. Nothing was deleted.', ['date' => fmt_date($company->subscription_ends_at)])],
        default => ['warning', 'credit-card', __('Payment needed'), __('Pay the first year to start using your company.')],
    };
@endphp
<x-page-header :title="__('Subscription & Billing')" icon="credit-card" :subtitle="$company->name" />

<div class="alert alert-{{ $state[0] }} d-flex align-items-start gap-3">
    <span class="fs-4"><x-icon :name="$state[1]" /></span>
    <div><strong class="d-block">{{ $state[2] }}</strong>{{ $state[3] }}</div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card h-100 mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Your version') }}: {{ $company->plan->name }}</h5></div>
            <div class="card-body">
                <p class="small text-muted">{{ __($company->plan->description) }}</p>
                @if ($modules)
                    <div class="small fw-semibold mb-1">{{ __('Active modules') }}</div>
                    <ul class="small mb-3">
                        @foreach ($modules as $module)
                            <li>{{ __($module['name']) }}</li>
                        @endforeach
                    </ul>
                @endif
                <div class="d-flex justify-content-between small"><span>{{ __('One-time setup fee') }}</span><span dir="ltr">{{ $company->setup_fee_paid ? __('Paid') : money($quote['setup_fee'], $quote['currency']) }}</span></div>
                <div class="d-flex justify-content-between small"><span>{{ __('Annual subscription') }}</span><span dir="ltr">{{ money($quote['annual_fee'], $quote['currency']) }}</span></div>
                <hr class="my-2">
                <div class="d-flex justify-content-between fw-bold"><span>{{ $quote['kind'] === 'first' ? __('To pay for the first year') : __('To pay for a renewal') }}</span><span dir="ltr">{{ money($quote['amount'], $quote['currency']) }}</span></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card h-100 mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Send a payment') }}</h5></div>
            <div class="card-body">
                @if ($waiting)
                    <p class="mb-1">{{ __('A payment of :amount sent on :date is waiting for approval.', ['amount' => money($waiting->amount, $waiting->currency), 'date' => fmt_date($waiting->created_at)]) }}</p>
                    <p class="small text-muted mb-0">{{ __('You will be able to use the company as soon as it is approved. If it is rejected, the reason appears below and you can send it again.') }}</p>
                @elseif ($canPay)
                    <p class="small">{{ __(config('billing.instructions')) }}</p>
                    <form method="POST" action="{{ route('billing.pay') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="method">{{ __('Paid by') }} <span class="text-danger">*</span></label>
                                <select class="form-select" id="method" name="method" required data-plain>
                                    @foreach (config('billing.methods') as $value => $label)
                                        <option value="{{ $value }}" @selected(old('method') === $value)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="reference">{{ __('Transaction number') }}</label>
                                <input type="text" class="form-control" id="reference" name="reference" value="{{ old('reference') }}" maxlength="100" dir="ltr">
                            </div>
                            <div class="col-12" id="receiptBox">
                                <label class="form-label" for="receipt">{{ __('Receipt (photo or PDF)') }} <span class="text-danger">*</span></label>
                                <input type="file" class="form-control @error('receipt') is-invalid @enderror" id="receipt" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                                @error('receipt')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12" id="cashNote" hidden>
                                <div class="form-text">{{ __('Cash needs no receipt. Hand the money to our office; your company opens when we confirm it.') }}</div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3"><x-icon name="paper-plane" class="me-1" /> {{ __('Send :amount for approval', ['amount' => money($quote['amount'], $quote['currency'])]) }}</button>
                    </form>
                    @push('scripts')
                    <script>
                        // a cash payment has no receipt: the box is hidden and not required
                        (function () {
                            var method = document.getElementById('method'), box = document.getElementById('receiptBox'), file = document.getElementById('receipt'), note = document.getElementById('cashNote');
                            function sync() {
                                var cash = method.value === 'cash';
                                box.hidden = cash;
                                note.hidden = !cash;
                                file.required = !cash;
                                if (cash) file.value = '';
                            }
                            method.addEventListener('change', sync);
                            sync();
                        })();
                    </script>
                    @endpush
                @elseif ($company->isUsable())
                    <p class="text-muted mb-0">{{ __('Nothing is due. You can renew from :days days before the end date.', ['days' => config('billing.renew_days')]) }}</p>
                @else
                    <p class="text-muted mb-0">{{ __('Ask the owner of the company to pay the subscription.') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h5 class="card-title mb-0">{{ __('Payments') }}</h5></div>
    <div class="card-body">
        @if ($payments->isEmpty())
            <p class="text-muted mb-0">{{ __('No payment was sent yet.') }}</p>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('For') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Period') }}</th><th class="text-end">{{ __('Receipt') }}</th></tr></thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td>{{ fmt_date($payment->created_at) }}</td>
                            <td>{{ $payment->plan?->name }} · {{ $payment->kind === 'first' ? __('First year') : __('Renewal') }}</td>
                            <td class="text-end" dir="ltr">{{ money($payment->amount, $payment->currency) }}</td>
                            <td>
                                @if ($payment->status === 'approved')
                                    <span class="badge bg-success-subtle text-success-emphasis">{{ __('Approved') }}</span>
                                @elseif ($payment->status === 'rejected')
                                    <span class="badge bg-danger-subtle text-danger-emphasis">{{ __('Rejected') }}</span>
                                    <span class="d-block small text-muted">{{ $payment->rejectionText() }}</span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis">{{ __('Waiting') }}</span>
                                @endif
                            </td>
                            <td>{{ $payment->period_start ? fmt_date($payment->period_start).' – '.fmt_date($payment->period_end) : '' }}</td>
                            <td class="text-end">@if ($payment->receipt_path)<x-act kind="view" :href="route('billing.receipt', ['id' => $payment->id])" :blank="true" />@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
