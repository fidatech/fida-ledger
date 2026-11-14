@extends('layouts.app', ['title' => __('POS Sales')])

@section('content')
<x-page-header :title="__('POS Sales')" icon="receipt" :crumbs="[[__('Point of Sale'), route('pos.index')], [__('POS Sales')]]" />

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-6 col-md-auto">
                <select class="form-select" name="status" aria-label="{{ __('Status') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('Sales and returns') }}</option>
                    <option value="paid" @selected(request('status') === 'paid')>{{ __('Sales') }}</option>
                    <option value="refunded" @selected(request('status') === 'refunded')>{{ __('POS Returns') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-auto"><x-date name="from" :value="query_date('from')" title="{{ __('From') }}" aria-label="{{ __('From') }}" /></div>
            <div class="col-6 col-md-auto"><x-date name="until" :value="query_date('until')" title="{{ __('Until') }}" aria-label="{{ __('Until') }}" /></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>
        @if ($sales->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="receipt" /></div><h3>{{ __('Nothing found') }}</h3></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Time') }}</th><th>{{ __('Register') }}</th><th>{{ __('Customer') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @foreach ($sales as $sale)
                        <tr>
                            <td dir="ltr"><a class="text-decoration-none fw-semibold" href="{{ route('pos.sale', ['id' => $sale->id]) }}">{{ $sale->code() }}</a></td>
                            <td class="text-nowrap">{{ $sale->created_at->timezone(company()->timezone)->format('j M Y H:i') }}</td>
                            <td>{{ $sale->session?->register?->name }}</td>
                            <td>{{ $sale->customer?->name }}</td>
                            <td class="text-end" dir="ltr">{{ money($sale->total) }}</td>
                            <td>@if ($sale->status === 'refunded')<span class="badge bg-danger-subtle text-danger-emphasis">{{ __('Refunded') }}</span>@else<span class="badge bg-success-subtle text-success-emphasis">{{ __('Paid') }}</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $sales->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
