@extends('layouts.app', ['title' => $order->code()])

@section('content')
@php
    $badges = ['draft' => ['secondary', __('Draft')], 'approved' => ['primary', __('Approved')], 'closed' => ['success', __('Completed')], 'cancelled' => ['danger', __('Cancelled')]];
    $manage = can_do('purchases.manage');
    $q = fn ($n) => \App\Inventory\Stock::qty($n);
    $sub = fn ($a, $b) => \App\Support\Dec::sub($a, $b);
    $open = $order->status === 'approved';
    $eventNames = ['receipt' => __('Goods receipt'), 'bill' => __('Supplier bill'), 'return' => __('Purchase return')];
@endphp
<x-page-header :title="__('Purchase order').' '.$order->code()" icon="cart-flatbed" :subtitle="$order->supplier?->name.' · '.fmt_date($order->date).' · '.$order->warehouse?->name" :crumbs="[[__('Purchase Orders'), route('purchases.index')], [$order->code()]]">
    <span class="badge bg-{{ $badges[$order->status][0] }}-subtle text-{{ $badges[$order->status][0] }}-emphasis align-self-center">{{ $badges[$order->status][1] }}</span>
    @if ($order->status === 'draft' && $manage)
        <a class="btn btn-outline-soft" href="{{ route('purchases.edit', ['id' => $order->id]) }}"><x-icon name="pen" class="me-1" /> {{ __('Edit') }}</a>
    @endif
    @if ($order->status === 'draft' && can_do('purchases.approve'))
        <form method="POST" action="{{ route('purchases.act', ['id' => $order->id, 'step' => 'approve']) }}" class="d-inline">@csrf<button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Approve') }}</button></form>
    @endif
    @if (in_array($order->status, ['approved', 'closed'], true) && can_do('money.create'))
        <a class="btn btn-danger" href="{{ route('money.create', ['direction' => 'out', 'kind' => 'supplier_payment', 'account' => $order->supplier_account_id]) }}"><x-icon name="arrow-up" class="me-1" /> {{ __('Pay supplier') }}</a>
    @endif
    @if (in_array($order->status, ['draft', 'approved'], true) && $manage)
        <form method="POST" action="{{ route('purchases.act', ['id' => $order->id, 'step' => 'cancel']) }}" class="d-inline" data-confirm="{{ __('Cancel this order?') }}" data-confirm-ok="{{ __('Yes, cancel it') }}">@csrf<button type="submit" class="btn btn-outline-soft">{{ __('Cancel order') }}</button></form>
    @endif
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card">
    <div class="card-body">
        <form method="POST" id="stepForm">
            @csrf
            <table class="table align-middle w-100">
                <thead><tr><th>{{ __('Description') }}</th><th class="text-end">{{ __('Ordered') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Received') }}</th><th class="text-end">{{ __('Billed') }}</th><th class="text-end">{{ __('Amount') }}</th>@if ($open && $manage)<th class="text-end no-print" style="width:130px">{{ __('Quantity now') }}</th>@endif</tr></thead>
                <tbody>
                    @foreach ($order->lines as $line)
                        <tr>
                            <td>{{ $line->description }}@unless ($stocked[$line->id]) <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Not stocked') }}</span>@endunless</td>
                            <td class="text-end" dir="ltr">{{ $q($line->quantity) }}</td>
                            <td class="text-end" dir="ltr">{{ money($line->unit_price) }}</td>
                            <td class="text-end" dir="ltr">{{ $stocked[$line->id] ? $q($line->received_qty) : '—' }}</td>
                            <td class="text-end" dir="ltr">{{ $q($line->billed_qty) }}</td>
                            <td class="text-end" dir="ltr">{{ money(\App\Support\Dec::mul($line->quantity, $line->unit_price)) }}</td>
                            @if ($open && $manage)
                                <td class="no-print"><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="qty[{{ $line->id }}]" dir="ltr" autocomplete="off" placeholder="{{ $stocked[$line->id] ? $q($sub($line->quantity, $line->received_qty)) : $q($sub($line->quantity, $line->billed_qty)) }}" aria-label="{{ __('Quantity now') }}"></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="fw-bold"><td colspan="5" class="text-end">{{ __('Total') }}</td><td class="text-end" dir="ltr">{{ money($order->total, $order->currency) }}</td>@if ($open && $manage)<td></td>@endif</tr></tfoot>
            </table>

            @if ($open && $manage)
                <div class="row g-2 align-items-end no-print">
                    <div class="col-6 col-md-2"><label class="form-label" for="date">{{ __('Date') }}</label><x-date name="date" :value="today_iso()" :required="true" id="date" /></div>
                    <div class="col-6 col-md-2"><label class="form-label" for="reference">{{ __('Supplier invoice no.') }}</label><input type="text" class="form-control" id="reference" name="reference" maxlength="100"></div>
                    <div class="col-6 col-md-2"><label class="form-label" for="due_date">{{ __('Bill due on') }}</label><x-date name="due_date" id="due_date" /></div>
                    <div class="col-12 col-md-6 d-flex flex-wrap gap-2 justify-content-md-end">
                        <button type="submit" class="btn btn-primary" formaction="{{ route('purchases.act', ['id' => $order->id, 'step' => 'receive']) }}"><x-icon name="box-open" class="me-1" /> {{ __('Receive goods') }}</button>
                        <button type="submit" class="btn btn-success" formaction="{{ route('purchases.act', ['id' => $order->id, 'step' => 'bill']) }}"><x-icon name="file-invoice-dollar" class="me-1" /> {{ __('Enter supplier bill') }}</button>
                        <button type="submit" class="btn btn-outline-soft" formaction="{{ route('purchases.act', ['id' => $order->id, 'step' => 'return']) }}"><x-icon name="rotate-left" class="me-1" /> {{ __('Return goods') }}</button>
                    </div>
                </div>
                <p class="small text-muted mt-2 mb-0 no-print">{{ __('Type the quantities for this step in "Quantity now". For a bill, empty quantities bill everything received and not yet billed.') }}</p>
            @elseif ($order->status === 'draft')
                <p class="text-muted mb-0">{{ __('Approve the order to receive goods and enter bills.') }}</p>
            @endif
        </form>
        @if ($order->notes)<p class="small text-muted mt-3 mb-0" style="white-space:pre-line">{{ $order->notes }}</p>@endif
    </div>
</div>

@if ($order->events->isNotEmpty())
    <div class="card no-print">
        <div class="card-header"><h5 class="card-title mb-0">{{ __('Receipts, bills and returns') }}</h5></div>
        <div class="card-body">
            <table class="table table-hover w-100 mb-0">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Date') }}</th><th>{{ __('Lines') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Entry') }}</th></tr></thead>
                <tbody>
                    @foreach ($order->events as $event)
                        <tr>
                            <td dir="ltr">{{ $event->code() }}</td>
                            <td>{{ $eventNames[$event->type] }}@if ($event->reference) · {{ $event->reference }}@endif</td>
                            <td class="text-nowrap">{{ fmt_date($event->date) }}</td>
                            <td class="small">@foreach ($event->lines as $line){{ $q($line['quantity']) }} × {{ $line['description'] }}@unless ($loop->last), @endunless @endforeach</td>
                            <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($event->amount) ? '' : money($event->amount, $order->currency) }}</td>
                            <td class="text-end">@if ($event->journal_entry_id && can_do('journals.view'))<a class="text-decoration-none" href="{{ route('journals.show', ['id' => $event->journal_entry_id]) }}">{{ __('Open') }}</a>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
