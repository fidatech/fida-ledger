@extends('layouts.app', ['title' => __('Payslip')])

@section('content')
<x-page-header :title="__('Payslip').' · '.$line->run->label()" icon="file-invoice-dollar" :crumbs="[[__('Payroll Runs'), route('payroll.index')], [$line->run->label(), route('payroll.show', ['id' => $line->payroll_run_id])], [$line->employee?->name]]">
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

<div class="card" style="max-width:560px">
    <div class="card-body">
        <div class="text-center mb-3">
            <div class="fs-5 fw-bold">{{ company()->name }}</div>
            <div class="text-uppercase small text-muted">{{ __('Payslip') }} · {{ $line->run->label() }}</div>
        </div>
        <div class="d-flex justify-content-between mb-3">
            <div><div class="fw-semibold">{{ $line->employee?->name }}</div><div class="small text-muted">{{ trim($line->employee?->position.' · '.$line->employee?->department?->name, ' ·') }}</div></div>
            <div class="small text-muted text-end">{{ $line->run->status === 'paid' ? __('Paid') : __('Approved, not paid') }}</div>
        </div>
        <table class="table table-sm w-100 mb-0">
            <tbody>
                <tr><td>{{ __('Basic salary') }}</td><td class="text-end" dir="ltr">{{ money($line->basic) }}</td></tr>
                <tr><td>{{ __('Allowances') }}</td><td class="text-end" dir="ltr">{{ money($line->allowances) }}</td></tr>
                <tr class="fw-semibold"><td>{{ __('Gross') }}</td><td class="text-end" dir="ltr">{{ money(\App\Support\Dec::add($line->basic, $line->allowances)) }}</td></tr>
                <tr><td>{{ __('Deductions') }}</td><td class="text-end" dir="ltr">− {{ money($line->deductions) }}</td></tr>
                <tr><td>{{ __('Advance recovered') }}</td><td class="text-end" dir="ltr">− {{ money($line->advance) }}</td></tr>
                <tr class="fw-bold fs-5"><td>{{ __('Net pay') }}</td><td class="text-end" dir="ltr">{{ money($line->net, base_currency()) }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
