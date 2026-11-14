@extends('layouts.app', ['title' => $type === 'quotation' ? __('Quotations') : __('Sales Orders')])

@section('content')
@php
    $quote = $type === 'quotation';
    $badges = ['draft' => ['secondary', __('Draft')], 'confirmed' => ['primary', __('Confirmed')], 'closed' => ['success', $quote ? __('Accepted') : __('Completed')], 'cancelled' => ['danger', __('Cancelled')]];
@endphp
<x-page-header :title="$quote ? __('Quotations') : __('Sales Orders')" icon="file-signature">
    @if (can_do('sales.manage'))
        <a class="btn btn-primary" href="{{ route('sales.create', ['doc' => $type]) }}"><x-icon name="plus" class="me-1" /> {{ $quote ? __('New quotation') : __('New sales order') }}</a>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search by number or customer…') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-8 col-md-auto">
                <select class="form-select" name="status" aria-label="{{ __('Status') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('Every status') }}</option>
                    @foreach ($badges as $value => [$tone, $label])
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button></div>
        </form>
        @if ($orders->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="file-signature" /></div><h3>{{ __('Nothing found') }}</h3></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td dir="ltr"><a class="text-decoration-none fw-semibold" href="{{ route('sales.show', ['doc' => $type, 'id' => $order->id]) }}">{{ $order->code() }}</a></td>
                            <td class="text-nowrap">{{ fmt_date($order->date) }}</td>
                            <td>{{ $order->customer?->name }}</td>
                            <td class="text-end" dir="ltr">{{ money($order->total, $order->currency) }}</td>
                            <td><span class="badge bg-{{ $badges[$order->status][0] }}-subtle text-{{ $badges[$order->status][0] }}-emphasis">{{ $badges[$order->status][1] }}</span></td>
                            <td class="text-end no-print"><div class="act-group"><x-act kind="view" :href="route('sales.show', ['doc' => $type, 'id' => $order->id])" />@if ($order->status === 'draft' && can_do('sales.manage'))<x-act kind="edit" :href="route('sales.edit', ['doc' => $type, 'id' => $order->id])" />@endif</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $orders->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
