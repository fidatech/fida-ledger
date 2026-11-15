@extends('layouts.app', ['title' => $movement->code()])

@section('content')
@php
    $in = $direction === 'in';
@endphp
<x-page-header :title="($in ? __('Money In receipt') : __('Money Out receipt')).' '.$movement->code()" icon="receipt" :crumbs="[[$in ? __('Money In') : __('Money Out'), route('money.index', ['direction' => $direction])], [$movement->code()]]">
    @if ($in && ! $movement->isVoid() && whatsapp_on())
        <a class="btn btn-outline-soft" href="{{ route('whatsapp.compose', ['money' => $movement->id]) }}"><x-icon name="comment-dots" class="me-1" /> {{ __('WhatsApp') }}</a>
    @endif
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
    @if ($movement->attachment_path)
        <a class="btn btn-outline-soft" href="{{ route('money.attachment', ['direction' => $direction, 'id' => $movement->id]) }}" target="_blank" rel="noopener"><x-icon name="paperclip" class="me-1" /> {{ __('Attachment') }}</a>
    @endif
    @if (! $movement->isVoid() && can_do('money.void'))
        <form method="POST" action="{{ route('money.void', ['direction' => $direction, 'id' => $movement->id]) }}" class="d-inline" data-confirm="{{ __('Cancel this?') }}" data-confirm-text="{{ __('A reversing entry is posted. The record stays in the history, marked cancelled.') }}" data-confirm-ok="{{ __('Yes, cancel it') }}">
            @csrf
            <button type="submit" class="btn btn-danger"><x-icon name="ban" class="me-1" /> {{ __('Cancel it') }}</button>
        </form>
    @endif
</x-page-header>

@if ($movement->isVoid())
    <div class="alert alert-danger"><x-icon name="ban" class="me-1" /> {{ __('This was cancelled. Its journal entry was reversed.') }}</div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100 mb-0">
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="small text-muted">{{ company()->name }}</div>
                    <div class="fs-2 fw-bold {{ $in ? 'text-success' : 'text-danger' }}" dir="ltr">{{ money($movement->amount, $movement->currency) }}</div>
                    <div class="small text-muted">{{ $movement->kindLabel() }}</div>
                </div>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th class="fw-normal text-muted">{{ __('Date') }}</th><td class="text-end">{{ fmt_date($movement->date) }}</td></tr>
                        <tr><th class="fw-normal text-muted">{{ $in ? __('Received from / for') : __('Paid to / for') }}</th><td class="text-end"><a class="text-decoration-none" href="{{ route('accounts.show', ['id' => $movement->counter_account_id]) }}">{{ $movement->counterAccount?->name }}</a></td></tr>
                        <tr><th class="fw-normal text-muted">{{ $in ? __('Into') : __('From') }}</th><td class="text-end">{{ $movement->cashAccount?->label() }}</td></tr>
                        @if ($movement->currency !== company()->base_currency)
                            <tr><th class="fw-normal text-muted">{{ __('Exchange rate') }}</th><td class="text-end" dir="ltr">{{ rtrim(rtrim($movement->rate, '0'), '.') }} → {{ money($movement->base_amount, company()->base_currency) }}</td></tr>
                        @endif
                        @if ($movement->description)<tr><th class="fw-normal text-muted">{{ __('Description') }}</th><td class="text-end">{{ $movement->description }}</td></tr>@endif
                        @if ($movement->reference)<tr><th class="fw-normal text-muted">{{ __('Reference') }}</th><td class="text-end">{{ $movement->reference }}</td></tr>@endif
                        <tr><th class="fw-normal text-muted">{{ __('Recorded by') }}</th><td class="text-end">{{ $movement->creator?->name }} · {{ $movement->created_at?->timezone(company()->timezone)->format('j M Y H:i') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @if ($movement->entry && can_do('journals.view'))
        <div class="col-12 col-lg-6 no-print">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">{{ __('Journal entry') }} #{{ $movement->entry->number }}</h5>
                    <a class="small text-decoration-none" href="{{ route('journals.show', ['id' => $movement->entry->id]) }}">{{ __('Open') }}</a>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Account') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
                        <tbody>
                            @foreach ($movement->entry->lines as $line)
                                <tr>
                                    <td>{{ $line->account?->name }}</td>
                                    <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($line->debit) ? '' : money($line->debit, $line->currency) }}</td>
                                    <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($line->credit) ? '' : money($line->credit, $line->currency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
