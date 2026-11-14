@extends('layouts.app', ['title' => __('Payroll Runs')])

@section('content')
@php
    $badges = ['draft' => ['secondary', __('Draft')], 'approved' => ['primary', __('Approved, not paid')], 'paid' => ['success', __('Paid')]];
@endphp
<x-page-header :title="__('Payroll Runs')" icon="users-gear" :subtitle="trans_choice('{1} 1 active employee|[0,*] :count active employees', $employees, ['count' => $employees])">
    <a class="btn btn-outline-soft" href="{{ route('employees.index') }}">{{ __('Employees') }}</a>
    <a class="btn btn-outline-soft" href="{{ route('payroll.advances') }}">{{ __('Salary Advances') }}</a>
</x-page-header>

@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@if (can_do('payroll.manage'))
    <form method="POST" action="{{ route('payroll.prepare') }}" class="card">
        @csrf
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div><label class="form-label small" for="period">{{ __('Prepare the payroll of') }}</label><input type="month" class="form-control" id="period" name="period" value="{{ $period }}" required></div>
            <button type="submit" class="btn btn-primary"><x-icon name="plus" class="me-1" /> {{ __('Prepare payroll') }}</button>
            <span class="small text-muted">{{ __('One payroll per month. It starts as a draft you can check and change.') }}</span>
        </div>
    </form>
@endif

<div class="card">
    <div class="card-body">
        @if ($runs->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="users-gear" /></div><h3>{{ __('No payroll yet') }}</h3><p>{{ __('Add your employees with their salaries, then prepare the first month.') }}</p></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Employees') }}</th><th class="text-end">{{ __('Gross') }}</th><th class="text-end">{{ __('Deductions') }}</th><th class="text-end">{{ __('Advances recovered') }}</th><th class="text-end">{{ __('Net to pay') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr>
                            <td><a class="text-decoration-none fw-semibold" href="{{ route('payroll.show', ['id' => $run->id]) }}">{{ $run->label() }}</a></td>
                            <td>{{ $run->lines_count }}</td>
                            <td class="text-end" dir="ltr">{{ money($run->gross) }}</td>
                            <td class="text-end" dir="ltr">{{ money($run->deductions) }}</td>
                            <td class="text-end" dir="ltr">{{ money($run->advances) }}</td>
                            <td class="text-end fw-semibold" dir="ltr">{{ money($run->net) }}</td>
                            <td><span class="badge bg-{{ $badges[$run->status][0] }}-subtle text-{{ $badges[$run->status][0] }}-emphasis">{{ $badges[$run->status][1] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $runs->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
