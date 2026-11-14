@extends('layouts.admin', ['title' => __('Payment approvals')])

@section('content')
<x-page-header :title="__('Payment approvals')" icon="receipt" :subtitle="__('A company becomes active only when its payment is approved here.')" />

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach (['pending' => __('Waiting'), 'approved' => __('Approved'), 'rejected' => __('Rejected')] as $value => $label)
                <a class="btn btn-sm {{ $status === $value ? 'btn-primary' : 'btn-outline-soft' }}" href="{{ route('admin.payments.index', ['status' => $value]) }}">{{ $label }}</a>
            @endforeach
        </div>

        @if ($payments->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="receipt" /></div><h3>{{ __('Nothing here') }}</h3></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Sent') }}</th><th>{{ __('Company') }}</th><th>{{ __('For') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Paid by') }}</th><th>{{ __('Receipt') }}</th><th class="text-end">{{ __('Decision') }}</th></tr></thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td class="text-nowrap">{{ fmt_date($payment->created_at) }}</td>
                            <td><span class="fw-semibold">{{ $payment->company?->name }}</span><span class="d-block small text-muted">{{ $payment->submitter?->name }} · {{ $payment->submitter?->email }}</span></td>
                            <td>{{ $payment->plan?->name }} · {{ $payment->kind === 'first' ? __('First year') : __('Renewal') }}<span class="d-block small text-muted" dir="ltr">{{ __('Setup') }} {{ money($payment->setup_fee) }} + {{ __('Annual') }} {{ money($payment->annual_fee) }}</span></td>
                            <td class="text-end fw-semibold" dir="ltr">{{ money($payment->amount, $payment->currency) }}</td>
                            <td>{{ __(config('billing.methods.'.$payment->method, $payment->method)) }}<span class="d-block small text-muted" dir="ltr">{{ $payment->reference }}</span></td>
                            <td>@if ($payment->receipt_path)<a class="btn btn-sm btn-light-secondary" href="{{ route('admin.payments.receipt', ['id' => $payment->id]) }}" target="_blank" rel="noopener"><x-icon name="eye" class="me-1" /> {{ __('Open') }}</a>@elseif ($payment->method === 'cash')<span class="small text-muted">{{ __('Cash: no receipt') }}</span>@endif</td>
                            <td class="text-end">
                                @if ($payment->status === 'pending')
                                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                                        <form method="POST" action="{{ route('admin.payments.approve', ['id' => $payment->id]) }}" data-confirm="{{ __('Approve this payment?') }}" data-confirm-text="{{ __('The company becomes active for one year.') }}" data-confirm-ok="{{ __('Approve') }}" data-confirm-style="primary">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success"><x-icon name="check" class="me-1" /> {{ __('Approve') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.payments.reject', ['id' => $payment->id]) }}" class="d-flex gap-1">
                                            @csrf
                                            <select class="form-select form-select-sm" name="reason" required data-plain aria-label="{{ __('Reason') }}" style="max-width:220px">
                                                <option value="">{{ __('Reason to reject…') }}</option>
                                                @foreach (config('billing.reject_reasons') as $value => $label)
                                                    <option value="{{ $value }}">{{ __($label) }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-danger">{{ __('Reject') }}</button>
                                        </form>
                                    </div>
                                @elseif ($payment->status === 'approved')
                                    <span class="badge bg-success-subtle text-success-emphasis">{{ __('Approved') }}</span>
                                    <span class="d-block small text-muted">{{ fmt_date($payment->period_start) }} – {{ fmt_date($payment->period_end) }}</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger-emphasis">{{ __('Rejected') }}</span>
                                    <span class="d-block small text-muted">{{ $payment->rejectionText() }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $payments->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
