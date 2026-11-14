@extends('layouts.app', ['title' => $type === 'credit_note' ? __('Credit Notes') : __('Invoices')])

@section('content')
@php
    $credit = $type === 'credit_note';
    $badges = ['draft' => ['secondary', __('Draft')], 'void' => ['danger', __('Cancelled')], 'credit' => ['info', __('Credit note')], 'paid' => ['success', __('Paid')], 'partial' => ['warning', __('Part paid')], 'overdue' => ['danger', __('Overdue')], 'unpaid' => ['primary', __('Unpaid')]];
    $states = $credit ? ['draft' => __('Drafts'), 'void' => __('Cancelled')] : ['unpaid' => __('Unpaid'), 'overdue' => __('Overdue'), 'paid' => __('Paid'), 'draft' => __('Drafts'), 'void' => __('Cancelled')];
@endphp
<x-page-header :title="$credit ? __('Credit Notes') : __('Invoices')" icon="file-invoice-dollar" :subtitle="$credit ? null : __('Customers still owe: :amount', ['amount' => sums($outstanding)])">
    @if (can_do('invoices.manage'))
        <a class="btn btn-primary" href="{{ route('invoices.create', $credit ? ['type' => 'credit_note'] : []) }}"><x-icon name="plus" class="me-1" /> {{ $credit ? __('New credit note') : __('Create Invoice') }}</a>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="col-12 col-md"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search by number, customer or reference…') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-6 col-md-auto">
                <select class="form-select" name="state" aria-label="{{ __('Status') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('Every status') }}</option>
                    @foreach ($states as $value => $label)
                        <option value="{{ $value }}" @selected($state === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto"><x-date name="from" :value="query_date('from')" title="{{ __('From') }}" aria-label="{{ __('From') }}" /></div>
            <div class="col-6 col-md-auto"><x-date name="until" :value="query_date('until')" title="{{ __('Until') }}" aria-label="{{ __('Until') }}" /></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button></div>
        </form>

        @if ($invoices->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="file-invoice-dollar" /></div><h3>{{ __('Nothing found') }}</h3></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Due date') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Still due') }}</th><th>{{ __('Status') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        @php
                            [$tone, $label] = $badges[$invoice->state()];
                        @endphp
                        <tr>
                            <td dir="ltr"><a class="text-decoration-none fw-semibold" href="{{ route('invoices.show', ['id' => $invoice->id]) }}">{{ $invoice->code() }}</a></td>
                            <td class="text-nowrap">{{ fmt_date($invoice->date) }}</td>
                            <td>{{ $invoice->customer?->name }}</td>
                            <td class="text-nowrap">{{ fmt_date($invoice->due_date) }}</td>
                            <td class="text-end" dir="ltr">{{ money($invoice->total, $invoice->currency) }}</td>
                            <td class="text-end" dir="ltr">{{ \App\Support\Dec::isPositive($invoice->due()) ? money($invoice->due(), $invoice->currency) : '' }}</td>
                            <td><span class="badge bg-{{ $tone }}-subtle text-{{ $tone }}-emphasis">{{ $label }}</span></td>
                            <td class="text-end no-print"><div class="act-group">
                                <x-act kind="view" :href="route('invoices.show', ['id' => $invoice->id])" />
                                @if ($invoice->status === 'draft' && can_do('invoices.manage'))
                                    <x-act kind="edit" :href="route('invoices.edit', ['id' => $invoice->id])" />
                                @endif
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $invoices->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
