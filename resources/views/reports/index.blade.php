@extends('layouts.app', ['title' => __('Reports')])

@section('content')
<x-page-header :title="__('Financial Reports')" icon="chart-column" :subtitle="__('All reports are prepared from the general ledger in :currency.', ['currency' => base_currency()])" />

@php
    $reports = [
        [__('Trial Balance'), __('Every account with its debit or credit balance on a day.'), 'scale-balanced', 'primary', route('reports.trial-balance')],
        [__('Profit & Loss'), __('Income and expenses of a period, and the profit that is left.'), 'chart-line', 'success', route('reports.profit-loss')],
        [__('Balance Sheet'), __('What the company owns and owes on a day.'), 'landmark', 'info', route('reports.balance-sheet')],
        [__('General Journal'), __('Every entry posted to the ledger.'), 'book', 'purple', can_do('journals.view') ? route('journals.index') : null],
        [__('Account Statements'), __('Open any account to see its statement: customers, suppliers, sarafs, cash and bank.'), 'address-book', 'teal', can_do('accounts.view') ? route('accounts.index') : null],
        [__('Money In Report'), __('Money received, with filters by kind, currency and date.'), 'arrow-down', 'success', can_do('money.view') ? route('money.index', ['direction' => 'in']) : null],
        [__('Money Out Report'), __('Money paid, with filters by kind, currency and date.'), 'arrow-up', 'danger', can_do('money.view') ? route('money.index', ['direction' => 'out']) : null],
    ];
@endphp
<div class="row g-3">
    @foreach ($reports as [$name, $text, $icon, $tone, $url])
        @continue($url === null)
        <div class="col-12 col-md-6 col-xl-4">
            <a href="{{ $url }}" class="card card-hover h-100 mb-0 text-decoration-none text-reset">
                <div class="card-body d-flex gap-3 align-items-start">
                    <span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:42px;height:42px"><x-icon :name="$icon" /></span>
                    <span><span class="d-block fw-semibold">{{ $name }}</span><span class="d-block small text-muted">{{ $text }}</span></span>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endsection
