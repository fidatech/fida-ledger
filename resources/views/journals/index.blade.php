@extends('layouts.app', ['title' => __('General Journal')])

@section('content')
<x-page-header :title="__('General Journal')" icon="book" :subtitle="__('Every entry posted to the ledger. Entries are never edited; a correction is a reversing entry.')">
    @if (can_do('journals.create'))
        <a class="btn btn-primary" href="{{ route('journals.create') }}"><x-icon name="plus" class="me-1" /> {{ __('New journal entry') }}</a>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search by number, description or reference…') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-6 col-md-auto"><x-date name="from" :value="query_date('from')" title="{{ __('From') }}" aria-label="{{ __('From') }}" /></div>
            <div class="col-6 col-md-auto"><x-date name="until" :value="query_date('until')" title="{{ __('Until') }}" aria-label="{{ __('Until') }}" /></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button></div>
        </form>
        @if ($entries->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="book" /></div><h3>{{ __('Nothing found') }}</h3></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Entry') }}</th><th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th>{{ __('Reference') }}</th><th class="text-end">{{ __('Amount') }} ({{ base_currency() }})</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr>
                            <td>#{{ $entry->number }}</td>
                            <td class="text-nowrap">{{ fmt_date($entry->date) }}</td>
                            <td>{{ $entry->description }}@if ($entry->isReversed()) <span class="badge bg-danger-subtle text-danger-emphasis">{{ __('Reversed') }}</span>@elseif ($entry->reversal_of_id) <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Reversal') }}</span>@endif</td>
                            <td dir="ltr">{{ $entry->reference }}</td>
                            <td class="text-end" dir="ltr">{{ money($entry->total) }}</td>
                            <td class="text-end no-print"><div class="act-group"><x-act kind="view" :href="route('journals.show', ['id' => $entry->id])" /></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $entries->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
