@extends('layouts.app', ['title' => __('Payroll').' '.$run->label()])

@section('content')
@php
    $badges = ['draft' => ['secondary', __('Draft')], 'approved' => ['primary', __('Approved, not paid')], 'paid' => ['success', __('Paid')]];
    $manage = can_do('payroll.manage');
    $draft = $run->status === 'draft' && $manage;
    $act = fn (string $step) => route('payroll.act', ['id' => $run->id, 'step' => $step]);
    $n = fn ($number) => rtrim(rtrim(\App\Support\Dec::round($number), '0'), '.') ?: '0';
@endphp
<x-page-header :title="__('Payroll').' · '.$run->label()" icon="users-gear" :subtitle="__('Amounts in :currency', ['currency' => base_currency()])" :crumbs="[[__('Payroll Runs'), route('payroll.index')], [$run->label()]]">
    <span class="badge bg-{{ $badges[$run->status][0] }}-subtle text-{{ $badges[$run->status][0] }}-emphasis align-self-center">{{ $badges[$run->status][1] }}</span>
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row g-3 mb-3">
    @foreach ([[__('Gross salaries'), $run->gross], [__('Deductions'), $run->deductions], [__('Advances recovered'), $run->advances], [__('Net to pay'), $run->net]] as [$label, $amount])
        <div class="col-6 col-md-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small fw-semibold text-muted mb-1">{{ $label }}</div><div class="fs-4 fw-bold" dir="ltr">{{ money($amount) }}</div></div></div></div>
    @endforeach
</div>

<form method="POST" class="card">
    @csrf
    <div class="card-body">
        <table class="table align-middle w-100">
            <thead><tr><th>{{ __('Employee') }}</th><th class="text-end">{{ __('Basic') }}</th><th class="text-end" style="width:130px">{{ __('Allowances') }}</th><th class="text-end" style="width:130px">{{ __('Deductions') }}</th><th class="text-end" style="width:130px">{{ __('Advance recovered') }}</th><th class="text-end">{{ __('Net') }}</th><th class="no-print"></th></tr></thead>
            <tbody>
                @foreach ($run->lines->sortBy(fn ($line) => $line->employee?->name) as $line)
                    <tr>
                        <td><span class="fw-semibold">{{ $line->employee?->name }}</span><span class="d-block small text-muted">{{ trim($line->employee?->position.' · '.$line->employee?->department?->name, ' ·') }}</span></td>
                        <td class="text-end" dir="ltr">{{ money($line->basic) }}</td>
                        @if ($draft)
                            <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="lines[{{ $line->id }}][allowances]" value="{{ $n($line->allowances) }}" dir="ltr" autocomplete="off" aria-label="{{ __('Allowances') }}"></td>
                            <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="lines[{{ $line->id }}][deductions]" value="{{ $n($line->deductions) }}" dir="ltr" autocomplete="off" aria-label="{{ __('Deductions') }}"></td>
                            <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="lines[{{ $line->id }}][advance]" value="{{ $n($line->advance) }}" dir="ltr" autocomplete="off" aria-label="{{ __('Advance recovered') }}" title="{{ __('Owes') }}: {{ money($line->employee?->advance_balance) }}"></td>
                        @else
                            <td class="text-end" dir="ltr">{{ money($line->allowances) }}</td>
                            <td class="text-end" dir="ltr">{{ money($line->deductions) }}</td>
                            <td class="text-end" dir="ltr">{{ money($line->advance) }}</td>
                        @endif
                        <td class="text-end fw-semibold" dir="ltr">{{ money($line->net) }}</td>
                        <td class="text-end no-print">@if ($run->status !== 'draft')<a class="btn btn-sm btn-light-secondary" href="{{ route('payroll.payslip', ['id' => $line->id]) }}">{{ __('Payslip') }}</a>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($manage && $run->status !== 'paid')
        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end align-items-end no-print">
            @if ($draft)
                <button type="submit" class="btn btn-outline-soft" formaction="{{ $act('discard') }}" formnovalidate>{{ __('Remove draft') }}</button>
                <button type="submit" class="btn btn-outline-soft" formaction="{{ $act('save') }}">{{ __('Save changes') }}</button>
                <button type="submit" class="btn btn-primary" formaction="{{ $act('approve') }}"><x-icon name="check" class="me-1" /> {{ __('Approve payroll') }}</button>
            @else
                <div><label class="form-label small" for="date">{{ __('Paid on') }}</label><x-date name="date" :value="today_iso()" :required="true" id="date" /></div>
                <div><label class="form-label small" for="account_id">{{ __('Paid from') }}</label>
                    <select class="form-select" id="account_id" name="account_id" required data-plain>@foreach ($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select></div>
                <button type="submit" class="btn btn-success" formaction="{{ $act('pay') }}"><x-icon name="money-bill-wave" class="me-1" /> {{ __('Pay :amount', ['amount' => money($run->net)]) }}</button>
            @endif
        </div>
    @endif
</form>
<p class="small text-muted">{{ $run->status === 'draft' ? __('Approving posts the salary expense and what is owed to the employees. Paying is a separate step.') : __('The salary expense was posted on approval. Paying only settles what is owed, so it is not counted twice.') }}</p>
@endsection
