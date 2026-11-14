@extends('layouts.app', ['title' => $order->code()])

@section('content')
@php
    $quote = $order->isQuotation();
    $type = $order->type;
    $badges = ['draft' => ['secondary', __('Draft')], 'confirmed' => ['primary', __('Confirmed')], 'closed' => ['success', $quote ? __('Accepted') : __('Completed')], 'cancelled' => ['danger', __('Cancelled')]];
    $manage = can_do('sales.manage');
    $q = fn ($n) => \App\Inventory\Stock::qty($n);
    $open = ! $quote && $order->status === 'confirmed';
    $act = fn (string $step) => route('sales.act', ['doc' => $type, 'id' => $order->id, 'step' => $step]);
@endphp
<x-page-header :title="($quote ? __('Quotation') : __('Sales order')).' '.$order->code()" icon="file-signature" :subtitle="$order->customer?->name.' · '.fmt_date($order->date).' · '.$order->warehouse?->name" :crumbs="[[$quote ? __('Quotations') : __('Sales Orders'), route('sales.index', ['doc' => $type])], [$order->code()]]">
    <span class="badge bg-{{ $badges[$order->status][0] }}-subtle text-{{ $badges[$order->status][0] }}-emphasis align-self-center">{{ $badges[$order->status][1] }}</span>
    @if ($order->status === 'draft' && $manage)
        <a class="btn btn-outline-soft" href="{{ route('sales.edit', ['doc' => $type, 'id' => $order->id]) }}"><x-icon name="pen" class="me-1" /> {{ __('Edit') }}</a>
        <form method="POST" action="{{ $act('confirm') }}" class="d-inline">@csrf<button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ $quote ? __('Accepted: make it an order') : __('Confirm order') }}</button></form>
    @endif
    @if (in_array($order->status, ['draft', 'confirmed'], true) && $manage)
        <form method="POST" action="{{ $act('cancel') }}" class="d-inline" data-confirm="{{ __('Cancel this?') }}" data-confirm-ok="{{ __('Yes, cancel it') }}">@csrf<button type="submit" class="btn btn-outline-soft">{{ __('Cancel it') }}</button></form>
    @endif
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card">
    <div class="card-body">
        <form method="POST" id="stepForm">
            @csrf
            <table class="table align-middle w-100">
                <thead><tr><th>{{ __('Description') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('Tax %') }}</th>@unless ($quote)<th class="text-end">{{ __('Delivered') }}</th><th class="text-end">{{ __('Invoiced') }}</th>@endunless @if ($open && $manage)<th class="text-end no-print" style="width:130px">{{ __('Quantity now') }}</th>@endif</tr></thead>
                <tbody>
                    @foreach ($order->lines as $line)
                        <tr>
                            <td>{{ $line->description }}</td>
                            <td class="text-end" dir="ltr">{{ $q($line->quantity) }}</td>
                            <td class="text-end" dir="ltr">{{ money($line->unit_price) }}</td>
                            <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($line->discount) ? '' : money($line->discount) }}</td>
                            <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($line->tax_rate) ? '' : $q($line->tax_rate) }}</td>
                            @unless ($quote)<td class="text-end" dir="ltr">{{ $q($line->delivered_qty) }}</td><td class="text-end" dir="ltr">{{ $q($line->invoiced_qty) }}</td>@endunless
                            @if ($open && $manage)
                                <td class="no-print"><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="qty[{{ $line->id }}]" dir="ltr" autocomplete="off" placeholder="{{ $q(\App\Support\Dec::sub($line->quantity, $line->delivered_qty)) }}" aria-label="{{ __('Quantity now') }}"></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="fw-bold"><td colspan="{{ $quote ? 4 : 6 }}" class="text-end">{{ __('Total with tax') }}</td><td class="text-end" dir="ltr">{{ money($order->total, $order->currency) }}</td>@if ($open && $manage)<td></td>@endif</tr></tfoot>
            </table>

            @if ($open && $manage)
                <div class="row g-2 align-items-end no-print">
                    <div class="col-6 col-md-2"><label class="form-label" for="date">{{ __('Date') }}</label><x-date name="date" :value="today_iso()" :required="true" id="date" /></div>
                    <div class="col-6 col-md-2"><label class="form-label" for="due_date">{{ __('Invoice due on') }}</label><x-date name="due_date" id="due_date" /></div>
                    <div class="col-12 col-md-8 d-flex flex-wrap gap-2 justify-content-md-end">
                        <button type="submit" class="btn btn-primary" formaction="{{ $act('deliver') }}"><x-icon name="truck" class="me-1" /> {{ __('Deliver') }}</button>
                        <button type="submit" class="btn btn-success" formaction="{{ $act('invoice') }}"><x-icon name="file-invoice-dollar" class="me-1" /> {{ __('Invoice what is delivered') }}</button>
                        <button type="submit" class="btn btn-outline-soft" formaction="{{ $act('return') }}"><x-icon name="rotate-left" class="me-1" /> {{ __('Customer return') }}</button>
                    </div>
                </div>
                <p class="small text-muted mt-2 mb-0 no-print">{{ __('"Quantity now" is used by Deliver and Customer return. Empty quantities deliver everything still open.') }}</p>
            @elseif (! $quote && $order->status === 'closed' && $manage)
                <div class="row g-2 align-items-end no-print">
                    <div class="col-12"><p class="small text-muted mb-1">{{ __('This order is complete. To take goods back, type the quantities and press Customer return.') }}</p></div>
                    @foreach ($order->lines as $line)
                        <div class="col-6 col-md-3"><label class="form-label small" for="ret-{{ $line->id }}">{{ $line->description }}</label><input type="text" inputmode="decimal" class="form-control form-control-sm" id="ret-{{ $line->id }}" name="qty[{{ $line->id }}]" dir="ltr" autocomplete="off"></div>
                    @endforeach
                    <div class="col-6 col-md-2"><label class="form-label small" for="date">{{ __('Date') }}</label><x-date name="date" :value="today_iso()" :required="true" id="date" /></div>
                    <div class="col-auto"><button type="submit" class="btn btn-outline-soft" formaction="{{ $act('return') }}"><x-icon name="rotate-left" class="me-1" /> {{ __('Customer return') }}</button></div>
                </div>
            @endif
        </form>
        @if ($order->notes)<p class="small text-muted mt-3 mb-0" style="white-space:pre-line">{{ $order->notes }}</p>@endif
    </div>
</div>

@if ($order->events->isNotEmpty() || $order->invoices->isNotEmpty())
    <div class="card no-print">
        <div class="card-header"><h5 class="card-title mb-0">{{ __('Deliveries, invoices and returns') }}</h5></div>
        <div class="card-body">
            <table class="table table-hover w-100 mb-0">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Date') }}</th><th>{{ __('Details') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                    @foreach ($order->events as $event)
                        <tr>
                            <td dir="ltr">{{ $event->code() }}</td>
                            <td>{{ $event->type === 'return' ? __('Customer return') : __('Delivery note') }}</td>
                            <td class="text-nowrap">{{ fmt_date($event->date) }}</td>
                            <td class="small">@foreach ($event->lines as $line){{ $q($line['quantity']) }} × {{ $line['description'] }}@unless ($loop->last), @endunless @endforeach</td>
                            <td class="text-end small text-muted" dir="ltr">@if (can_do('journals.view') && $event->journal_entry_id)<a class="text-decoration-none" href="{{ route('journals.show', ['id' => $event->journal_entry_id]) }}">{{ __('Cost') }} {{ money($event->cost) }}</a>@endif</td>
                        </tr>
                    @endforeach
                    @foreach ($order->invoices as $invoice)
                        <tr>
                            <td dir="ltr"><a class="text-decoration-none" href="{{ route('invoices.show', ['id' => $invoice->id]) }}">{{ $invoice->code() }}</a></td>
                            <td>{{ $invoice->isCreditNote() ? __('Credit note') : __('Invoice') }}</td>
                            <td class="text-nowrap">{{ fmt_date($invoice->date) }}</td>
                            <td class="small">{{ $invoice->isCreditNote() ? '' : __('Still due: :amount', ['amount' => money($invoice->due(), $invoice->currency)]) }}</td>
                            <td class="text-end" dir="ltr">{{ money($invoice->total, $invoice->currency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
