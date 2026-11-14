@extends('layouts.app', ['title' => __('Salary Advances')])

@section('content')
<x-page-header :title="__('Salary Advances')" icon="hand-holding-dollar" :crumbs="[[__('Payroll Runs'), route('payroll.index')], [__('Salary Advances')]]" />

<div class="row g-3">
    @if (can_do('payroll.manage'))
        <div class="col-12 col-lg-4">
            <form method="POST" action="{{ route('payroll.advance') }}" class="card" data-confirm="{{ __('Give this advance?') }}" data-confirm-text="{{ __('The money leaves the account now and is recovered from the next salaries.') }}" data-confirm-ok="{{ __('Record') }}" data-confirm-style="primary">
                @csrf
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Give an advance') }}</h5></div>
                <div class="card-body">
                    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
                    <div class="mb-3"><label class="form-label" for="employee_id">{{ __('Employee') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="employee_id" name="employee_id" required><option value="">{{ __('Choose…') }}</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected((int) old('employee_id') === $employee->id)>{{ $employee->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label" for="amount">{{ __('Amount') }} ({{ base_currency() }}) <span class="text-danger">*</span></label><input type="text" inputmode="decimal" class="form-control" id="amount" name="amount" value="{{ old('amount') }}" dir="ltr" autocomplete="off" required></div>
                    <div class="mb-3"><label class="form-label" for="account_id">{{ __('Paid from') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="account_id" name="account_id" required data-plain>@foreach ($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label" for="date">{{ __('Date') }} <span class="text-danger">*</span></label><x-date name="date" :value="old('date', today_iso())" :required="true" id="date" /></div>
                    <div class="mb-3"><label class="form-label" for="notes">{{ __('Notes') }}</label><input type="text" class="form-control" id="notes" name="notes" value="{{ old('notes') }}" maxlength="255"></div>
                    <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Record') }}</button>
                </div>
            </form>
        </div>
    @endif
    <div class="col-12 col-lg-8">
        <div class="card"><div class="card-body">
            @if ($advances->isEmpty())
                <div class="empty-state"><div class="es-icon"><x-icon name="hand-holding-dollar" /></div><h3>{{ __('No advance was given yet') }}</h3></div>
            @else
                <table class="table table-hover w-100">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Employee') }}</th><th>{{ __('Notes') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Still owes') }}</th></tr></thead>
                    <tbody>
                        @foreach ($advances as $advance)
                            <tr><td class="text-nowrap">{{ fmt_date($advance->date) }}</td><td class="fw-semibold">{{ $advance->employee?->name }}</td><td>{{ $advance->notes }}</td><td class="text-end" dir="ltr">{{ money($advance->amount) }}</td><td class="text-end" dir="ltr">{{ money($advance->employee?->advance_balance) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $advances->links('partials.pagination') }}
            @endif
        </div></div>
    </div>
</div>
@endsection
