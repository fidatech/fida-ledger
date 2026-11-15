{{--
    The lines of an order form (purchase order, quotation, sales order).
    $lines         rows to show (old input or the saved lines)
    $products      the catalogue; $priceField: which price a product fills in ('purchase_price' or 'sale_price')
    $selling       true adds discount, tax and income account columns ($incomeAccounts, $defaultIncome)
--}}
@php
    $selling = $selling ?? false;
    $num = fn ($number) => $number === null || $number === '' ? '' : (str_contains((string) $number, '.') ? rtrim(rtrim((string) $number, '0'), '.') : (string) $number);
    $blank = ['product_id' => null, 'description' => '', 'quantity' => '1', 'unit_price' => '', 'discount' => '', 'tax_rate' => '', 'income_account_id' => $defaultIncome ?? null];
    $incomeAccounts = $incomeAccounts ?? collect();
    $row = function ($i, array $line) use ($products, $priceField, $selling, $num, $incomeAccounts) {
        return view('partials.doc-line', ['i' => $i, 'line' => $line, 'products' => $products, 'priceField' => $priceField, 'selling' => $selling, 'num' => $num, 'incomeAccounts' => $incomeAccounts])->render();
    };
@endphp
<div class="table-responsive mt-4">
    <table class="table align-middle w-100">
        <thead><tr>
            <th style="min-width:170px">{{ __('Product') }}</th><th style="min-width:200px">{{ __('Description') }} *</th><th style="width:100px">{{ __('Quantity') }} *</th><th style="width:130px">{{ __('Price') }} *</th>
            @if ($selling)<th style="width:110px">{{ __('Discount') }}</th><th style="width:90px">{{ __('Tax %') }}</th><th style="min-width:160px">{{ __('Income account') }} *</th>@endif
            <th style="width:44px"></th>
        </tr></thead>
        <tbody id="lineRows">
            @foreach ($lines ?: [$blank] as $i => $line)
                {!! $row($i, $line + $blank) !!}
            @endforeach
        </tbody>
    </table>
</div>
<button type="button" class="btn btn-sm btn-outline-soft" id="addLine"><x-icon name="plus" class="me-1" /> {{ __('Add a line') }}</button>
<template id="lineTemplate">{!! $row('__i__', $blank) !!}</template>

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
            if (button && rows.querySelectorAll('tr').length > 1) button.closest('tr').remove();
        });
        // picking a product fills the description, the price and (when selling) the tax and income account
        rows.addEventListener('change', function (event) {
            var select = event.target.closest('[data-product]');
            if (!select) return;
            var option = select.options[select.selectedIndex];
            if (!option || !option.value) return;
            var row = select.closest('tr');
            ['description', 'unit_price', 'tax_rate', 'income_account_id'].forEach(function (field) {
                var box = row.querySelector('[data-field="' + field + '"]');
                var value = option.getAttribute('data-' + field);
                if (box && value !== null && value !== '') box.value = value;
            });
        });
    })();
</script>
@endpush
