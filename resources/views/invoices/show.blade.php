@extends('layouts.app', ['title' => $invoice->code()])

@section('content')
@php
    $credit = $invoice->isCreditNote();
    $company = company();
    $badges = ['draft' => ['secondary', __('Draft')], 'void' => ['danger', __('Cancelled')], 'credit' => ['info', __('Credit note')], 'paid' => ['success', __('Paid')], 'partial' => ['warning', __('Part paid')], 'overdue' => ['danger', __('Overdue')], 'unpaid' => ['primary', __('Unpaid')]];
    [$tone, $label] = $badges[$invoice->state()];
    $manage = can_do('invoices.manage');
@endphp
<x-page-header :title="($credit ? __('Credit note') : __('Invoice')).' '.$invoice->code()" icon="file-invoice-dollar" :crumbs="[[$credit ? __('Credit Notes') : __('Invoices'), route('invoices.index', $credit ? ['type' => 'credit_note'] : [])], [$invoice->code()]]">
    @if ($invoice->status === 'draft' && $manage)
        <a class="btn btn-outline-soft" href="{{ route('invoices.edit', ['id' => $invoice->id]) }}"><x-icon name="pen" class="me-1" /> {{ __('Edit') }}</a>
        <form method="POST" action="{{ route('invoices.issue', ['id' => $invoice->id]) }}" class="d-inline" data-confirm="{{ __('Issue :code?', ['code' => $invoice->code()]) }}" data-confirm-text="{{ __('It is posted to the ledger and cannot be changed afterwards.') }}" data-confirm-ok="{{ __('Issue') }}" data-confirm-style="primary">@csrf
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Issue') }}</button>
        </form>
    @endif
    @if (\App\Support\Dec::isPositive($invoice->due()) && can_do('money.create'))
        <a class="btn btn-success" href="{{ route('money.create', ['direction' => 'in', 'invoice' => $invoice->id]) }}"><x-icon name="arrow-down" class="me-1" /> {{ __('Receive payment') }}</a>
    @endif
    @if ($invoice->status === 'issued' && whatsapp_on())
        <a class="btn btn-outline-soft" href="{{ route('whatsapp.compose', ['invoice' => $invoice->id]) }}"><x-icon name="comment-dots" class="me-1" /> {{ __('WhatsApp') }}</a>
    @endif
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
    @if ($invoice->status !== 'void' && $manage)
        <form method="POST" action="{{ route('invoices.void', ['id' => $invoice->id]) }}" class="d-inline" data-confirm="{{ __('Cancel :code?', ['code' => $invoice->code()]) }}" data-confirm-text="{{ __('Its journal entry is reversed. The document stays in the list, marked cancelled.') }}" data-confirm-ok="{{ __('Yes, cancel it') }}">@csrf
            <button type="submit" class="btn btn-danger"><x-icon name="ban" class="me-1" /> {{ __('Cancel it') }}</button>
        </form>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
            <div>
                <div class="fs-4 fw-bold">{{ $company->name }}</div>
                <div class="small text-muted">{{ $company->address }}</div>
                <div class="small text-muted" dir="ltr">{{ $company->phone }} {{ $company->email }}</div>
            </div>
            <div class="text-end">
                <div class="fs-4 fw-bold text-uppercase">{{ $credit ? __('Credit note') : __('Invoice') }}</div>
                <div dir="ltr">{{ $invoice->code() }}</div>
                <span class="badge bg-{{ $tone }}-subtle text-{{ $tone }}-emphasis">{{ $label }}</span>
            </div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="small text-muted">{{ __('To') }}</div>
                <div class="fw-semibold"><a class="text-decoration-none text-reset" href="{{ route('accounts.show', ['id' => $invoice->customer_account_id]) }}">{{ $invoice->customer?->name }}</a></div>
                <div class="small text-muted">{{ $invoice->customer?->address }}</div>
                <div class="small text-muted" dir="ltr">{{ $invoice->customer?->phone }}</div>
            </div>
            <div class="col-6 text-end">
                <div><span class="text-muted">{{ __('Date') }}:</span> {{ fmt_date($invoice->date) }}</div>
                @if ($invoice->due_date)<div><span class="text-muted">{{ __('Due date') }}:</span> {{ fmt_date($invoice->due_date) }}</div>@endif
                @if ($invoice->reference)<div><span class="text-muted">{{ __('Reference') }}:</span> {{ $invoice->reference }}</div>@endif
            </div>
        </div>

        <table class="table w-100">
            <thead><tr><th>#</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('Tax') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
            <tbody>
                @foreach ($invoice->lines as $line)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $line->description }}</td>
                        <td class="text-end" dir="ltr">{{ rtrim(rtrim($line->quantity, '0'), '.') }}</td>
                        <td class="text-end" dir="ltr">{{ money($line->unit_price) }}</td>
                        <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($line->discount) ? '' : money($line->discount) }}</td>
                        <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($line->tax) ? '' : money($line->tax) }}</td>
                        <td class="text-end" dir="ltr">{{ money($line->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="6" class="text-end">{{ __('Subtotal') }}</td><td class="text-end" dir="ltr">{{ money($invoice->subtotal, $invoice->currency) }}</td></tr>
                @if (\App\Support\Dec::isPositive($invoice->tax))
                    <tr><td colspan="6" class="text-end">{{ __('Tax') }}</td><td class="text-end" dir="ltr">{{ money($invoice->tax, $invoice->currency) }}</td></tr>
                @endif
                <tr class="fw-bold fs-5"><td colspan="6" class="text-end">{{ __('Total') }}</td><td class="text-end" dir="ltr">{{ money($invoice->total, $invoice->currency) }}</td></tr>
                @unless ($credit)
                    <tr><td colspan="6" class="text-end">{{ __('Paid') }}</td><td class="text-end" dir="ltr">{{ money($invoice->paid, $invoice->currency) }}</td></tr>
                    <tr class="fw-bold"><td colspan="6" class="text-end">{{ __('Still due') }}</td><td class="text-end" dir="ltr">{{ money($invoice->due(), $invoice->currency) }}</td></tr>
                @endunless
            </tfoot>
        </table>
        @if ($invoice->notes)
            <p class="small text-muted mb-0" style="white-space:pre-line">{{ $invoice->notes }}</p>
        @endif
    </div>
</div>

@if ($invoice->payments->isNotEmpty())
    <div class="card no-print">
        <div class="card-header"><h5 class="card-title mb-0">{{ __('Payments received') }}</h5></div>
        <div class="card-body">
            <table class="table table-hover w-100 mb-0">
                <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Date') }}</th><th>{{ __('Into') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                    @foreach ($invoice->payments as $payment)
                        <tr>
                            <td dir="ltr"><a class="text-decoration-none" href="{{ route('money.show', ['direction' => 'in', 'id' => $payment->money_movement_id]) }}">{{ $payment->movement?->code() }}</a></td>
                            <td>{{ fmt_date($payment->movement?->date) }}</td>
                            <td>{{ $payment->movement?->cashAccount?->name }}</td>
                            <td class="text-end" dir="ltr">{{ money($payment->amount, $invoice->currency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
