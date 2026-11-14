@extends('layouts.app', ['title' => __('Budgets')])

@section('content')
@php
    $manage = can_do('banking.manage');
    $n = fn ($number) => rtrim(rtrim(\App\Support\Dec::round($number, 2), '0'), '.') ?: '';
@endphp
<x-page-header :title="__('Budget of :year', ['year' => $year])" icon="bullseye" :subtitle="__('One amount per account for the year, in :currency. It is spread evenly over the twelve months.', ['currency' => base_currency()])">
    <a class="btn btn-outline-soft" href="{{ route('banking.budget', ['year' => $year - 1]) }}">{{ $year - 1 }}</a>
    <a class="btn btn-outline-soft" href="{{ route('banking.budget', ['year' => $year + 1]) }}">{{ $year + 1 }}</a>
    <a class="btn btn-primary" href="{{ route('banking.budget.comparison', ['year' => $year]) }}"><x-icon name="chart-column" class="me-1" /> {{ __('Budget Comparison') }}</a>
</x-page-header>

<form method="POST" action="{{ route('banking.budget.update', ['year' => $year]) }}" class="card">
    @csrf
    @method('PUT')
    <div class="card-body">
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        @foreach (['income' => __('Income'), 'expense' => __('Expenses')] as $class => $heading)
            <h6 class="text-uppercase small fw-semibold text-muted {{ $loop->first ? '' : 'mt-4' }}">{{ $heading }}</h6>
            <table class="table align-middle w-100 mb-0">
                <tbody>
                    @foreach ($accounts->where('class', $class) as $account)
                        <tr>
                            <td dir="ltr" class="text-muted small" style="width:110px">{{ $account->code }}</td>
                            <td><label class="mb-0" for="budget-{{ $account->id }}">{{ $account->name }}</label></td>
                            <td style="width:200px"><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" id="budget-{{ $account->id }}" name="budget[{{ $account->id }}]" value="{{ old('budget.'.$account->id, isset($totals[$account->id]) ? $n($totals[$account->id]) : '') }}" dir="ltr" autocomplete="off" @disabled(! $manage)></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>
    @if ($manage)
        <div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save budget') }}</button></div>
    @endif
</form>
@endsection
