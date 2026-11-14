@extends('layouts.app', ['title' => __('New journal entry')])

@section('content')
@php
    $lines = old('lines') ?? [[], [], [], []];
@endphp
<x-page-header :title="__('New journal entry')" icon="book" :subtitle="__('For what no other screen records: corrections, accruals, opening figures. Debits must equal credits.')" :crumbs="[[__('General Journal'), route('journals.index')], [__('New')]]" />

<form method="POST" action="{{ route('journals.store') }}" class="card" data-confirm="{{ __('Post this entry?') }}" data-confirm-text="{{ __('A posted entry cannot be edited. A mistake is corrected by reversing it.') }}" data-confirm-ok="{{ __('Post') }}" data-confirm-style="primary">
    @csrf
    <input type="hidden" name="once" value="{{ old('once', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="card-body">
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="row g-3">
            <div class="col-6 col-md-2"><label class="form-label" for="date">{{ __('Date') }} <span class="text-danger">*</span></label><x-date name="date" :value="old('date', today_iso())" :required="true" id="date" /></div>
            <div class="col-12 col-md-5"><label class="form-label" for="description">{{ __('Description') }} <span class="text-danger">*</span></label><input type="text" class="form-control" id="description" name="description" value="{{ old('description') }}" maxlength="255" required></div>
            <div class="col-6 col-md-2"><label class="form-label" for="reference">{{ __('Reference') }}</label><input type="text" class="form-control" id="reference" name="reference" value="{{ old('reference') }}" maxlength="100"></div>
            <div class="col-6 col-md-1"><label class="form-label" for="currency">{{ __('Currency') }}</label>
                <select class="form-select" id="currency" name="currency" required data-plain>@foreach (currencies() as $code)<option value="{{ $code }}" @selected(old('currency', base_currency()) === $code)>{{ $code }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label" for="rate">{{ __('Exchange rate') }}</label><input type="text" inputmode="decimal" class="form-control" id="rate" name="rate" value="{{ old('rate') }}" dir="ltr" autocomplete="off" title="{{ __('Only for a currency other than :currency.', ['currency' => base_currency()]) }}"></div>
        </div>

        <div class="table-responsive mt-4">
            <table class="table align-middle w-100">
                <thead><tr><th style="min-width:240px">{{ __('Account') }}</th><th style="min-width:180px">{{ __('Description') }}</th><th style="width:150px" class="text-end">{{ __('Debit') }}</th><th style="width:150px" class="text-end">{{ __('Credit') }}</th></tr></thead>
                <tbody id="journalRows">
                    @foreach ($lines as $i => $line)
                        <tr>
                            <td>
                                <select class="form-select form-select-sm" name="lines[{{ $i }}][account_id]" data-plain aria-label="{{ __('Account') }}" @required($i < 2)>
                                    <option value="">—</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}" @selected((int) ($line['account_id'] ?? 0) === $account->id)>{{ $account->code }} · {{ $account->name }}{{ $account->currency ? ' ('.$account->currency.')' : '' }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" name="lines[{{ $i }}][description]" value="{{ $line['description'] ?? '' }}" maxlength="255" aria-label="{{ __('Description') }}"></td>
                            <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="lines[{{ $i }}][debit]" value="{{ $line['debit'] ?? '' }}" dir="ltr" autocomplete="off" data-debit aria-label="{{ __('Debit') }}"></td>
                            <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="lines[{{ $i }}][credit]" value="{{ $line['credit'] ?? '' }}" dir="ltr" autocomplete="off" data-credit aria-label="{{ __('Credit') }}"></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="fw-bold"><td colspan="2" class="text-end">{{ __('Total') }}</td><td class="text-end" dir="ltr" id="journalDebits">0.00</td><td class="text-end" dir="ltr" id="journalCredits">0.00</td></tr></tfoot>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-soft" id="journalAdd"><x-icon name="plus" class="me-1" /> {{ __('Add a line') }}</button>
        <p class="small text-muted mt-3 mb-0">{{ __('Inventory and "Goods received not invoiced" are not offered: the stock screens keep them equal to the stock records.') }}</p>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('journals.index') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Post entry') }}</button>
    </div>
</form>

@push('scripts')
<script>
    // The totals here are a preview; the ledger checks the balance again with exact decimals.
    (function () {
        var rows = document.getElementById('journalRows');
        var num = function (box) { var v = parseFloat(String(box.value).replace(/,/g, '')); return isNaN(v) ? 0 : v; };
        var show = function (v) { return (Math.round(v * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        function totals() {
            var d = 0, c = 0;
            rows.querySelectorAll('[data-debit]').forEach(function (box) { d += num(box); });
            rows.querySelectorAll('[data-credit]').forEach(function (box) { c += num(box); });
            document.getElementById('journalDebits').textContent = show(d);
            document.getElementById('journalCredits').textContent = show(c);
        }
        rows.addEventListener('input', totals);
        document.getElementById('journalAdd').addEventListener('click', function () {
            var index = rows.querySelectorAll('tr').length;
            var copy = rows.querySelector('tr:last-child').cloneNode(true);
            copy.querySelectorAll('input, select').forEach(function (field) {
                field.name = field.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
                field.value = '';
                field.required = false;
            });
            rows.appendChild(copy);
        });
        totals();
    })();
</script>
@endpush
@endsection
