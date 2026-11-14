@extends('layouts.admin', ['title' => __('Revenue Reports')])

@section('content')
<x-page-header :title="__('Revenue of :year', ['year' => $year])" icon="chart-line" :subtitle="__('Approved subscription payments, in :currency', ['currency' => $currency])">
    <a class="btn btn-outline-soft" href="{{ route('admin.revenue', ['year' => $year - 1]) }}">{{ $year - 1 }}</a>
    <a class="btn btn-outline-soft" href="{{ route('admin.revenue', ['year' => $year + 1]) }}">{{ $year + 1 }}</a>
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

<div class="row g-3 mb-3">
    @foreach ([[__('Setup fees'), $setup], [__('Annual subscriptions'), $annual], [__('Total received'), \App\Support\Dec::add($setup, $annual, 2)], [__('Renewals due if all renew'), $renewals]] as [$label, $amount])
        <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ $label }}</div><div class="fs-4 fw-bold" dir="ltr">{{ money($amount) }}</div></div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card h-100 mb-0"><div class="card-body">
            <table class="table table-hover w-100 mb-0">
                <thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Payments') }}</th><th class="text-end">{{ __('Setup fees') }}</th><th class="text-end">{{ __('Annual subscriptions') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                <tbody>
                    @foreach ($months as $number => $month)
                        <tr class="{{ $month['count'] ? '' : 'text-muted' }}"><td>{{ __(date('F', mktime(0, 0, 0, $number, 1, 2000))) }}</td><td class="text-end">{{ $month['count'] ?: '' }}</td><td class="text-end" dir="ltr">{{ money($month['setup']) }}</td><td class="text-end" dir="ltr">{{ money($month['annual']) }}</td><td class="text-end fw-semibold" dir="ltr">{{ money(\App\Support\Dec::add($month['setup'], $month['annual'], 2)) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100 mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('By version') }}</h5></div>
            <div class="card-body">
                @forelse ($plans as $name => $amount)
                    <div class="d-flex justify-content-between border-bottom py-2"><span>{{ $name }}</span><strong dir="ltr">{{ money($amount, $currency) }}</strong></div>
                @empty
                    <p class="text-muted mb-0">{{ __('No payment was approved in this year.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
