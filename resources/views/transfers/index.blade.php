@extends('layouts.app', ['title' => __('Transfer & Exchange History')])

@section('content')
<x-page-header :title="__('Transfer & Exchange History')" icon="right-left">
    @if (can_do('transfers.create'))
        <a class="btn btn-outline-soft" href="{{ route('transfers.create', ['type' => 'exchange']) }}"><x-icon name="coins" class="me-1" /> {{ __('Currency Exchange') }}</a>
        <a class="btn btn-primary" href="{{ route('transfers.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Account Transfer') }}</a>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a class="btn btn-sm {{ $type === null ? 'btn-primary' : 'btn-outline-soft' }}" href="{{ route('transfers.index') }}">{{ __('All') }}</a>
            <a class="btn btn-sm {{ $type === 'transfer' ? 'btn-primary' : 'btn-outline-soft' }}" href="{{ route('transfers.index', ['type' => 'transfer']) }}">{{ __('Transfers') }}</a>
            <a class="btn btn-sm {{ $type === 'exchange' ? 'btn-primary' : 'btn-outline-soft' }}" href="{{ route('transfers.index', ['type' => 'exchange']) }}">{{ __('Exchanges') }}</a>
        </div>
        @if ($transfers->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="right-left" /></div><h3>{{ __('Nothing here yet') }}</h3></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th class="text-end">{{ __('Given') }}</th><th class="text-end">{{ __('Received') }}</th><th class="text-end">{{ __('Rate') }}</th><th class="text-end">{{ __('Gain / loss') }}</th></tr></thead>
                <tbody>
                    @foreach ($transfers as $transfer)
                        <tr>
                            <td dir="ltr" class="small">@if (can_do('journals.view') && $transfer->journal_entry_id)<a class="text-decoration-none" href="{{ route('journals.show', ['id' => $transfer->journal_entry_id]) }}">{{ $transfer->code() }}</a>@else{{ $transfer->code() }}@endif</td>
                            <td class="text-nowrap">{{ fmt_date($transfer->date) }}</td>
                            <td>{{ $transfer->fromAccount?->name }}</td>
                            <td>{{ $transfer->toAccount?->name }}</td>
                            <td class="text-end" dir="ltr">{{ money($transfer->from_amount, $transfer->from_currency) }}</td>
                            <td class="text-end" dir="ltr">{{ money($transfer->to_amount, $transfer->to_currency) }}</td>
                            <td class="text-end" dir="ltr">{{ $transfer->type === 'exchange' ? rtrim(rtrim($transfer->rate, '0'), '.') : '' }}</td>
                            <td class="text-end {{ \App\Support\Dec::isNegative($transfer->exchange_difference) ? 'text-danger' : 'text-success' }}" dir="ltr">{{ \App\Support\Dec::isZero($transfer->exchange_difference) ? '' : money($transfer->exchange_difference, base_currency()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $transfers->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
