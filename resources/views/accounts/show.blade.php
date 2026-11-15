@extends('layouts.app', ['title' => $account->name])

@section('content')
<x-page-header :title="$account->name" :icon="config('ledger.kinds.'.$account->kind.'.icon', 'book')" :subtitle="$account->code.' · '.$account->kindLabel().($account->currency ? ' · '.$account->currency : '').($account->phone ? ' · '.$account->phone : '')" :crumbs="[[__('Accounts'), route('accounts.index', ['kind' => $account->kind])], [$account->name]]">
    @if (! $account->is_archived && ! $account->isCash() && can_do('money.create'))
        <a class="btn btn-success" href="{{ route('money.create', ['direction' => 'in', 'kind' => 'other', 'account' => $account->id]) }}"><x-icon name="arrow-down" class="me-1" /> {{ __('Money In') }}</a>
        <a class="btn btn-danger" href="{{ route('money.create', ['direction' => 'out', 'kind' => 'other', 'account' => $account->id]) }}"><x-icon name="arrow-up" class="me-1" /> {{ __('Money Out') }}</a>
    @endif
    @if (can_do('accounts.manage'))
        <a class="btn btn-outline-soft" href="{{ route('accounts.edit', ['id' => $account->id]) }}"><x-icon name="pen" class="me-1" /> {{ __('Edit') }}</a>
        <form method="POST" action="{{ route('accounts.archive', ['id' => $account->id]) }}" class="d-inline" data-confirm="{{ $account->is_archived ? __('Bring this account back?') : __('Archive this account?') }}" data-confirm-text="{{ __('Its history stays in the books.') }}" data-confirm-ok="{{ $account->is_archived ? __('Restore') : __('Archive') }}">
            @csrf
            <button type="submit" class="btn btn-outline-soft"><x-icon name="box-archive" class="me-1" /> {{ $account->is_archived ? __('Restore') : __('Archive') }}</button>
        </form>
    @endif
    @if (can_do('reports.export'))
        <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><x-icon name="file-arrow-down" class="me-1" /> {{ __('Excel (CSV)') }}</a>
    @endif
    @if (whatsapp_on())
        <a class="btn btn-outline-soft" href="{{ route('whatsapp.compose', ['account' => $account->id]) }}"><x-icon name="comment-dots" class="me-1" /> {{ __('WhatsApp') }}</a>
    @endif
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

<div class="row g-3 mb-3">
    @forelse ($balances as $currency => $balance)
        <div class="col-6 col-md-3">
            <div class="card h-100 mb-0"><div class="card-body">
                <div class="small fw-semibold text-muted mb-1">{{ __('Balance') }} · {{ $currency }}</div>
                <div class="fs-4 fw-bold {{ \App\Support\Dec::isNegative($balance) ? 'text-danger' : '' }}" dir="ltr">{{ money($balance) }}</div>
            </div></div>
        </div>
    @empty
        <div class="col-12 col-md-4"><div class="card mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('Balance') }}</div><div class="fs-4 fw-bold">0.00</div></div></div></div>
    @endforelse
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">{{ __('Statement') }}</h5></div>
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3 no-print">
            <div class="col-6 col-md-auto"><x-date name="from" :value="$from" aria-label="{{ __('From') }}" /></div>
            <div class="col-6 col-md-auto"><x-date name="until" :value="$until" aria-label="{{ __('Until') }}" /></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Entry') }}</th><th>{{ __('Description') }}</th><th>{{ __('Currency') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
            <tbody>
                @foreach ($opening as $currency => $balance)
                    <tr class="text-muted"><td>{{ fmt_date($from) }}</td><td></td><td>{{ __('Balance brought forward') }}</td><td>{{ $currency }}</td><td></td><td></td><td class="text-end" dir="ltr">{{ money($balance) }}</td></tr>
                @endforeach
                @foreach ($rows as $row)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($row['line']->date) }}</td>
                        <td>@if (can_do('journals.view'))<a class="text-decoration-none" href="{{ route('journals.show', ['id' => $row['line']->journal_entry_id]) }}">#{{ $row['line']->entry?->number }}</a>@else #{{ $row['line']->entry?->number }}@endif</td>
                        <td>{{ $row['line']->description ?: $row['line']->entry?->description }}</td>
                        <td>{{ $row['line']->currency }}</td>
                        <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($row['line']->debit) ? '' : money($row['line']->debit) }}</td>
                        <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($row['line']->credit) ? '' : money($row['line']->credit) }}</td>
                        <td class="text-end fw-semibold" dir="ltr">{{ money($row['balance']) }}</td>
                    </tr>
                @endforeach
                @if (! $rows && ! $opening)
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Nothing was posted to this account in this period.') }}</td></tr>
                @endif
            </tbody>
        </table>
        @if ($cut)
            <p class="small text-muted mb-0">{{ __('Only the first lines of this period are shown. Choose a shorter period to see the rest.') }}</p>
        @endif
        <p class="small text-muted mb-0">{{ $account->normalSide() === 'credit' ? __('A positive balance is an amount we owe or have earned (credit).') : __('A positive balance is an amount we hold or are owed (debit).') }}</p>
    </div>
</div>
@endsection
