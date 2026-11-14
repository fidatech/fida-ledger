@extends('layouts.app', ['title' => $sale->code()])

@section('content')
<x-page-header :title="__('Sales receipt').' '.$sale->code()" icon="receipt" :crumbs="[[__('POS Sales'), route('pos.sales')], [$sale->code()]]">
    @if ($sale->session?->isOpen())
        <a class="btn btn-success" href="{{ route('pos.sell', ['id' => $sale->pos_session_id]) }}"><x-icon name="plus" class="me-1" /> {{ __('Next sale') }}</a>
    @endif
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
    @if ($sale->status === 'paid' && $openSession && can_do('pos.close'))
        <form method="POST" action="{{ route('pos.refund', ['id' => $sale->id]) }}" class="d-inline" data-confirm="{{ __('Refund :code?', ['code' => $sale->code()]) }}" data-confirm-text="{{ __('The goods go back into stock and :amount is paid back.', ['amount' => money($sale->total, base_currency())]) }}" data-confirm-ok="{{ __('Refund') }}">
            @csrf
            <input type="hidden" name="session_id" value="{{ $openSession->id }}">
            <button type="submit" class="btn btn-danger"><x-icon name="rotate-left" class="me-1" /> {{ __('Refund') }}</button>
        </form>
    @endif
</x-page-header>

@if ($sale->status === 'refunded')
    <div class="alert alert-danger"><x-icon name="rotate-left" class="me-1" /> {{ __('This sale was refunded.') }}</div>
@endif

<div class="card" style="max-width:520px">
    <div class="card-body">
        <div class="text-center mb-3">
            <div class="fs-5 fw-bold">{{ company()->name }}</div>
            <div class="small text-muted">{{ company()->address }} <span dir="ltr">{{ company()->phone }}</span></div>
            <div class="small" dir="ltr">{{ $sale->code() }} · {{ $sale->created_at->timezone(company()->timezone)->format('j M Y H:i') }}</div>
            <div class="small text-muted">{{ $sale->session?->register?->name }} · {{ $sale->customer?->name }}</div>
        </div>
        <table class="table table-sm w-100">
            <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td class="text-end" dir="ltr">{{ \App\Inventory\Stock::qty($item['quantity']) }}</td>
                        <td class="text-end" dir="ltr">{{ money($item['unit_price']) }}</td>
                        <td class="text-end" dir="ltr">{{ money(\App\Support\Dec::sub(\App\Support\Dec::mul($item['quantity'], $item['unit_price']), $item['discount'])) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @if (\App\Support\Dec::isPositive($sale->invoice?->tax ?? '0'))<tr><td colspan="3" class="text-end">{{ __('Tax') }}</td><td class="text-end" dir="ltr">{{ money($sale->invoice->tax) }}</td></tr>@endif
                <tr class="fw-bold fs-5"><td colspan="3" class="text-end">{{ __('Total') }}</td><td class="text-end" dir="ltr">{{ money($sale->total, base_currency()) }}</td></tr>
                @foreach ($sale->payments as $payment)
                    <tr class="small"><td colspan="3" class="text-end text-muted">{{ $payment['name'] }}</td><td class="text-end" dir="ltr">{{ money($payment['amount']) }}</td></tr>
                @endforeach
            </tfoot>
        </table>
        <p class="text-center small text-muted mb-0">{{ __('Thank you.') }}</p>
        @if (can_do('invoices.view'))
            <p class="text-center small mb-0 no-print"><a class="text-decoration-none" href="{{ route('invoices.show', ['id' => $sale->invoice_id]) }}">{{ __('Invoice') }} {{ $sale->invoice?->code() }}</a></p>
        @endif
    </div>
</div>
@endsection
