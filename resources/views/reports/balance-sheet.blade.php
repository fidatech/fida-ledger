@extends('layouts.app', ['title' => __('Balance Sheet')])

@section('content')
<x-page-header :title="__('Balance Sheet')" icon="landmark" :subtitle="__('As of :date, in :currency', ['date' => fmt_date($until), 'currency' => base_currency()])" :crumbs="[[__('Reports'), route('reports.index')], [__('Balance Sheet')]]">
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3 no-print">
            <div class="col-auto"><x-date name="until" :value="$until" aria-label="{{ __('As of') }}" /></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>
        <table class="table w-100">
            <tbody>
                @include('reports._section', ['heading' => __('Assets'), 'rows' => $assets, 'total' => $totalAssets, 'totalLabel' => __('Total assets')])
                @include('reports._section', ['heading' => __('Liabilities'), 'rows' => $liabilities, 'total' => $totalLiabilities, 'totalLabel' => __('Total liabilities')])
                @include('reports._section', ['heading' => __('Equity'), 'rows' => $equity, 'extra' => [__('Profit not yet closed to equity') => $earnings], 'total' => $totalEquity, 'totalLabel' => __('Total equity')])
                <tr class="fw-bold fs-5"><td>{{ __('Liabilities and equity') }}</td><td class="text-end" dir="ltr">{{ money(\App\Support\Dec::add($totalLiabilities, $totalEquity)) }}</td></tr>
            </tbody>
        </table>
        @unless ($balanced)
            <div class="alert alert-danger mb-0">{{ __('The ledger does not balance. Please contact support.') }}</div>
        @endunless
        <p class="small text-muted mb-0">{{ __('Accounts in other currencies are shown at the value they were posted at. Revaluation at today\'s rates is not applied.') }}</p>
    </div>
</div>
@endsection
