@extends('layouts.app', ['title' => __('Bank Reconciliation')])

@section('content')
@php
    $manage = can_do('banking.manage');
    $signed = fn ($line) => \App\Support\Dec::sub($line->debit, $line->credit);
@endphp
<x-page-header :title="__('Bank Reconciliation')" icon="building-columns" :subtitle="$account->label()">
    @if ($manage && $unmatchedStatement->isNotEmpty())
        <form method="POST" action="{{ route('banking.auto') }}" class="d-inline">@csrf<input type="hidden" name="account_id" value="{{ $account->id }}"><button type="submit" class="btn btn-outline-soft"><x-icon name="wand-magic-sparkles" class="me-1" /> {{ __('Match by amount and date') }}</button></form>
    @endif
</x-page-header>

@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <form method="GET" class="col-12 col-md-4">
                <label class="form-label" for="account">{{ __('Account') }}</label>
                <select class="form-select" id="account" name="account" onchange="this.form.submit()">
                    @foreach ($accounts as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $account->id)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </form>
            @if ($manage)
                <form method="POST" action="{{ route('banking.import') }}" enctype="multipart/form-data" class="col-12 col-md-8">
                    @csrf
                    <input type="hidden" name="account_id" value="{{ $account->id }}">
                    <label class="form-label" for="file">{{ __('Import the bank statement (CSV)') }}</label>
                    <div class="d-flex gap-2"><input type="file" class="form-control" id="file" name="file" accept=".csv,.txt" required><button type="submit" class="btn btn-primary text-nowrap"><x-icon name="file-arrow-up" class="me-1" /> {{ __('Import') }}</button></div>
                    <div class="form-text">{{ __('First row: column names. Needed: date and amount (or credit and debit). Optional: description, reference. Dates as 2026-11-15 or 15/11/2026. Importing the same file again adds nothing.') }}</div>
                </form>
            @endif
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('Balance in the books') }}</div><div class="fs-5 fw-bold" dir="ltr">{{ money($bookBalance, $account->currency) }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('Matched lines') }}</div><div class="fs-5 fw-bold">{{ $matchedCount }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('On the statement, not in the books') }}</div><div class="fs-5 fw-bold {{ \App\Support\Dec::isZero($inBankNotBooks) ? '' : 'text-danger' }}" dir="ltr">{{ money($inBankNotBooks) }}</div><div class="small text-muted">{{ $unmatchedStatement->count() }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ __('In the books, not on the statement') }}</div><div class="fs-5 fw-bold {{ \App\Support\Dec::isZero($inBooksNotBank) ? '' : 'text-warning' }}" dir="ltr">{{ money($inBooksNotBank) }}</div><div class="small text-muted">{{ $unmatchedLedger->count() }}</div></div></div></div>
</div>
@if ($matchedCount && $unmatchedStatement->isEmpty() && $unmatchedLedger->isEmpty())
    <div class="alert alert-success"><x-icon name="circle-check" class="me-1" /> {{ __('Reconciled: every statement line and every entry since the first statement date is matched.') }}</div>
@endif

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">{{ __('Statement lines to match') }}</h5></div>
    <div class="card-body">
        @if ($unmatchedStatement->isEmpty())
            <p class="text-muted mb-0">{{ $matchedCount ? __('Nothing left: every imported line is matched.') : __('Import a statement to begin.') }}</p>
        @else
            <div class="table-responsive">
                <table class="table align-middle w-100 mb-0">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Amount') }}</th><th style="min-width:320px">{{ __('Match with an entry in the books') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($unmatchedStatement as $line)
                            @php
                                $in = \App\Support\Dec::isPositive($line->amount);
                            @endphp
                            <tr>
                                <td class="text-nowrap">{{ fmt_date($line->date) }}</td>
                                <td>{{ $line->description }}@if ($line->reference) <span class="small text-muted" dir="ltr">{{ $line->reference }}</span>@endif</td>
                                <td class="text-end fw-semibold {{ $in ? 'text-success' : 'text-danger' }}" dir="ltr">{{ money($line->amount) }}</td>
                                <td>
                                    @if (! $manage)
                                    @elseif ($candidates[$line->id]->isNotEmpty())
                                        <form method="POST" action="{{ route('banking.match', ['id' => $line->id]) }}" class="d-flex gap-2">
                                            @csrf
                                            <select class="form-select form-select-sm" name="journal_line_id" required data-plain aria-label="{{ __('Entry') }}">
                                                @foreach ($candidates[$line->id] as $candidate)
                                                    <option value="{{ $candidate->id }}">{{ fmt_date($candidate->date) }} · #{{ $candidate->entry?->number }} · {{ \Illuminate\Support\Str::limit($candidate->description ?: $candidate->entry?->description, 40) }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Match') }}</button>
                                        </form>
                                    @else
                                        <span class="small text-muted">{{ __('No entry of this amount.') }}</span>
                                        @if (can_do('money.create'))
                                            <a class="small text-decoration-none" href="{{ route('money.create', ['direction' => $in ? 'in' : 'out', 'kind' => 'other']) }}">{{ $in ? __('Record a Money In') : __('Record a Money Out') }}</a>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-end">@if ($manage)<form method="POST" action="{{ route('banking.statement.destroy', ['id' => $line->id]) }}" class="d-inline" data-confirm="{{ __('Remove this statement line?') }}" data-confirm-ok="{{ __('Remove') }}">@csrf @method('DELETE')<x-act kind="delete" :label="__('Remove')" /></form>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="card h-100 mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('In the books, not on the statement yet') }}</h5></div>
            <div class="card-body">
                @if ($unmatchedLedger->isEmpty())
                    <p class="text-muted mb-0">{{ __('Nothing.') }}</p>
                @else
                    <table class="table table-sm w-100 mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Entry') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                        <tbody>
                            @foreach ($unmatchedLedger as $line)
                                <tr><td class="text-nowrap">{{ fmt_date($line->date) }}</td><td>@if (can_do('journals.view'))<a class="text-decoration-none" href="{{ route('journals.show', ['id' => $line->journal_entry_id]) }}">#{{ $line->entry?->number }}</a>@else #{{ $line->entry?->number }}@endif</td><td class="small">{{ $line->description ?: $line->entry?->description }}</td><td class="text-end" dir="ltr">{{ money($signed($line)) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="small text-muted mt-2 mb-0">{{ __('Usually cheques and transfers the bank has not processed yet. They match when the next statement is imported.') }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card h-100 mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Matched lately') }}</h5></div>
            <div class="card-body">
                @if ($matched->isEmpty())
                    <p class="text-muted mb-0">{{ __('Nothing matched yet.') }}</p>
                @else
                    <table class="table table-sm align-middle w-100 mb-0">
                        <thead><tr><th>{{ __('Statement') }}</th><th>{{ __('Entry') }}</th><th class="text-end">{{ __('Amount') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($matched as $line)
                                <tr>
                                    <td class="small">{{ fmt_date($line->date) }} · {{ \Illuminate\Support\Str::limit($line->description, 30) }}</td>
                                    <td class="small">#{{ $line->journalLine?->entry?->number }} · {{ fmt_date($line->journalLine?->date) }}</td>
                                    <td class="text-end" dir="ltr">{{ money($line->amount) }}</td>
                                    <td class="text-end">@if ($manage)<form method="POST" action="{{ route('banking.unmatch', ['id' => $line->id]) }}">@csrf<button type="submit" class="btn btn-sm btn-light-secondary">{{ __('Unmatch') }}</button></form>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
