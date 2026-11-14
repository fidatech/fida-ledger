@extends('layouts.app', ['title' => __('Journal entry').' #'.$entry->number])

@section('content')
<x-page-header :title="__('Journal entry').' #'.$entry->number" icon="book" :subtitle="fmt_date($entry->date).($entry->reference ? ' · '.$entry->reference : '').($entry->creator ? ' · '.$entry->creator->name : '')" :crumbs="[[__('General Journal'), route('journals.index')], ['#'.$entry->number]]">
    @if ($entry->source_type === null && ! $entry->isReversed() && ! $entry->reversal_of_id && can_do('journals.create'))
        <form method="POST" action="{{ route('journals.reverse', ['id' => $entry->id]) }}" class="d-inline" data-confirm="{{ __('Reverse this entry?') }}" data-confirm-text="{{ __('A mirror entry is posted today. Both stay in the journal.') }}" data-confirm-ok="{{ __('Reverse') }}">
            @csrf
            <button type="submit" class="btn btn-danger"><x-icon name="rotate-left" class="me-1" /> {{ __('Reverse') }}</button>
        </form>
    @endif
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

@if ($entry->reversedBy)
    <div class="alert alert-danger">{{ __('This entry was reversed by entry') }} <a href="{{ route('journals.show', ['id' => $entry->reversedBy->id]) }}">#{{ $entry->reversedBy->number }}</a>.</div>
@elseif ($entry->reversalOf)
    <div class="alert alert-secondary">{{ __('This entry reverses entry') }} <a href="{{ route('journals.show', ['id' => $entry->reversalOf->id]) }}">#{{ $entry->reversalOf->number }}</a>.</div>
@endif

<div class="card">
    <div class="card-body">
        <p>{{ $entry->description }}</p>
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="text-end">{{ __('Rate') }}</th><th class="text-end">{{ __('Debit') }} ({{ base_currency() }})</th><th class="text-end">{{ __('Credit') }} ({{ base_currency() }})</th></tr></thead>
            <tbody>
                @foreach ($entry->lines as $line)
                    <tr>
                        <td><a class="text-decoration-none" href="{{ route('accounts.show', ['id' => $line->account_id]) }}">{{ $line->account?->code }} · {{ $line->account?->name }}</a></td>
                        <td>{{ $line->description }}</td>
                        <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($line->debit) ? '' : money($line->debit, $line->currency) }}</td>
                        <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($line->credit) ? '' : money($line->credit, $line->currency) }}</td>
                        <td class="text-end" dir="ltr">{{ $line->currency === base_currency() ? '' : rtrim(rtrim($line->rate, '0'), '.') }}</td>
                        <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($line->base_debit) ? '' : money($line->base_debit) }}</td>
                        <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($line->base_credit) ? '' : money($line->base_credit) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-bold"><td colspan="5" class="text-end">{{ __('Total') }}</td><td class="text-end" dir="ltr">{{ money(\App\Support\Dec::sum($entry->lines->pluck('base_debit'))) }}</td><td class="text-end" dir="ltr">{{ money(\App\Support\Dec::sum($entry->lines->pluck('base_credit'))) }}</td></tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
