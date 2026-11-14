@extends('layouts.app', ['title' => $invoice ? __('Edit :code', ['code' => $invoice->code()]) : ($type === 'credit_note' ? __('New credit note') : __('Create Invoice'))])

@section('content')
@php
    $credit = $type === 'credit_note';
    $defaultIncome = $incomeAccounts->firstWhere('system_key', 'sales')?->id ?? $incomeAccounts->first()?->id;
    $blank = ['description' => '', 'quantity' => '1', 'unit_price' => '', 'discount' => '', 'tax_rate' => '', 'income_account_id' => $defaultIncome, 'product_id' => null];
    $trim = fn ($number) => $number === null || $number === '' ? '' : (str_contains((string) $number, '.') ? rtrim(rtrim((string) $number, '0'), '.') : (string) $number);
    $lines = old('lines') ?? ($invoice ? $invoice->lines->map(fn ($line) => $line->only(array_keys($blank)))->all() : [$blank]);
    $value = fn (string $name, $default = null) => old($name, $invoice?->{$name} ?? $default);
@endphp
<x-page-header :title="$invoice ? __('Edit :code', ['code' => $invoice->code()]) : ($credit ? __('New credit note') : __('Create Invoice'))" icon="file-invoice-dollar" :crumbs="[[$credit ? __('Credit Notes') : __('Invoices'), route('invoices.index', $credit ? ['type' => 'credit_note'] : [])], [$invoice ? __('Edit') : __('New')]]" />

@if ($customers->isEmpty())
    <div class="card"><div class="card-body"><div class="empty-state">
        <div class="es-icon"><x-icon name="user-tag" /></div>
        <h3>{{ __('Add a customer first') }}</h3>
        @if (can_do('accounts.manage'))
            <a class="btn btn-primary" href="{{ route('accounts.create', ['kind' => 'customer']) }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => __('Customer')]) }}</a>
        @endif
    </div></div></div>
@else
<form method="POST" action="{{ $invoice ? route('invoices.update', ['id' => $invoice->id]) : route('invoices.store') }}" class="card" id="invoiceForm">
    @csrf
    @if ($invoice)
        @method('PUT')
    @endif
    <input type="hidden" name="type" value="{{ $type }}">
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        @if ($credit)
            <div class="alert alert-info">{{ __('A credit note lowers what the customer owes and takes the amount back out of income.') }}</div>
        @endif
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="customer_account_id">{{ __('Customer') }} <span class="text-danger">*</span></label>
                <select class="form-select" id="customer_account_id" name="customer_account_id" required>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) $value('customer_account_id', request('customer')) === $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="date">{{ __('Date') }} <span class="text-danger">*</span></label>
                <x-date name="date" :value="$value('date', today_iso())" :required="true" id="date" />
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="due_date">{{ __('Due date') }}</label>
                <x-date name="due_date" :value="$value('due_date')" id="due_date" />
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="currency">{{ __('Currency') }} <span class="text-danger">*</span></label>
                <select class="form-select" id="currency" name="currency" required data-plain>
                    @foreach (currencies() as $code)
                        <option value="{{ $code }}" @selected($value('currency', base_currency()) === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="rate">{{ __('Exchange rate') }}</label>
                <input type="text" inputmode="decimal" class="form-control" id="rate" name="rate" value="{{ $trim(old('rate', $invoice && $invoice->currency !== base_currency() ? $invoice->rate : '')) }}" autocomplete="off" dir="ltr">
                <div class="form-text">{{ __('Only for a currency other than :currency.', ['currency' => base_currency()]) }}</div>
            </div>
        </div>

        <div class="table-responsive mt-4">
            <table class="table align-middle w-100" id="lineTable">
                <thead><tr>
                    @if ($products->isNotEmpty())<th style="min-width:160px">{{ __('Product or service') }}</th>@endif
                    <th style="min-width:220px">{{ __('Description') }} *</th><th style="width:100px">{{ __('Quantity') }} *</th><th style="width:130px">{{ __('Price') }} *</th><th style="width:110px">{{ __('Discount') }}</th><th style="width:90px">{{ __('Tax %') }}</th><th style="min-width:170px">{{ __('Income account') }} *</th><th style="width:44px"></th>
                </tr></thead>
                <tbody id="lineRows">
                    @foreach ($lines as $i => $line)
                        @include('invoices._line', ['i' => $i, 'line' => $line + $blank])
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-soft" id="addLine"><x-icon name="plus" class="me-1" /> {{ __('Add a line') }}</button>
        <template id="lineTemplate">@include('invoices._line', ['i' => '__i__', 'line' => $blank])</template>

        <div class="row g-3 mt-2">
            <div class="col-12 col-md-4">
                <label class="form-label" for="reference">{{ __('Reference') }}</label>
                <input type="text" class="form-control" id="reference" name="reference" value="{{ $value('reference') }}" maxlength="100">
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label" for="notes">{{ __('Notes on the invoice') }}</label>
                <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000">{{ $value('notes') }}</textarea>
            </div>
        </div>
        <p class="small text-muted mt-3 mb-0">{{ __('Totals are worked out when you save. A draft can be changed; an issued invoice is posted to the ledger and can only be cancelled.') }}</p>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('invoices.index', $credit ? ['type' => 'credit_note'] : []) }}">{{ __('Cancel') }}</a>
        <button type="submit" name="action" value="draft" class="btn btn-outline-soft">{{ __('Save draft') }}</button>
        <button type="submit" name="action" value="issue" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save and issue') }}</button>
    </div>
</form>

@push('scripts')
<script>
    (function () {
        var rows = document.getElementById('lineRows');
        var template = document.getElementById('lineTemplate');
        var next = rows.querySelectorAll('tr').length;

        document.getElementById('addLine').addEventListener('click', function () {
            var holder = document.createElement('tbody');
            holder.innerHTML = template.innerHTML.replace(/__i__/g, String(next++));
            rows.appendChild(holder.firstElementChild);
        });

        rows.addEventListener('click', function (event) {
            var button = event.target.closest('[data-remove-line]');
            if (button && rows.querySelectorAll('tr').length > 1) {
                button.closest('tr').remove();
            }
        });

        // picking a product fills the description, the price and the tax of its line
        rows.addEventListener('change', function (event) {
            var select = event.target.closest('[data-product]');
            if (!select) return;
            var option = select.options[select.selectedIndex];
            var row = select.closest('tr');
            if (!option || !option.value) return;
            row.querySelector('[data-field="description"]').value = option.getAttribute('data-name');
            row.querySelector('[data-field="unit_price"]').value = option.getAttribute('data-price');
            row.querySelector('[data-field="tax_rate"]').value = option.getAttribute('data-tax');
            var income = option.getAttribute('data-income');
            if (income) row.querySelector('[data-field="income_account_id"]').value = income;
        });
    })();
</script>
@endpush
@endif
@endsection
