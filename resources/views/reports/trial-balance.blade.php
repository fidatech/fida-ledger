@extends('layouts.app', ['title' => __('Trial Balance')])

@section('content')
<x-page-header :title="__('Trial Balance')" icon="scale-balanced" :subtitle="__('As of :date, in :currency', ['date' => fmt_date($until), 'currency' => base_currency()])" :crumbs="[[__('Reports'), route('reports.index')], [__('Trial Balance')]]">
    @if (can_do('reports.export'))
        <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><x-icon name="file-arrow-down" class="me-1" /> {{ __('Excel (CSV)') }}</a>
    @endif
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3 no-print">
            <div class="col-auto"><x-date name="until" :value="$until" aria-label="{{ __('As of') }}" /></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Account') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td dir="ltr" class="text-muted small">{{ $row['account']->code }}</td>
                        <td><a class="text-decoration-none" href="{{ route('accounts.show', ['id' => $row['account']->id, 'from' => '2000-01-01', 'until' => $until]) }}">{{ $row['account']->name }}</a></td>
                        <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($row['debit']) ? '' : money($row['debit']) }}</td>
                        <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($row['credit']) ? '' : money($row['credit']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">{{ __('Nothing was posted yet.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="fw-bold"><td colspan="2" class="text-end">{{ __('Total') }}</td><td class="text-end" dir="ltr">{{ money($debits) }}</td><td class="text-end" dir="ltr">{{ money($credits) }}</td></tr>
            </tfoot>
        </table>
        @if (\App\Support\Dec::cmp($debits, $credits) !== 0)
            <div class="alert alert-danger mb-0">{{ __('The ledger does not balance. Please contact support.') }}</div>
        @endif
    </div>
</div>
@endsection
