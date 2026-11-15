{{-- One line of the invoice form. $i is its index ("__i__" in the template the script copies). --}}
@php
    $show = fn ($number) => $number === null || $number === '' ? '' : (str_contains((string) $number, '.') ? rtrim(rtrim((string) $number, '0'), '.') : (string) $number);
@endphp
<tr>
    @if ($products->isNotEmpty())
        <td>
            <select class="form-select form-select-sm" name="lines[{{ $i }}][product_id]" data-product data-plain aria-label="{{ __('Product') }}">
                <option value="">—</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $show($product->sale_price) }}" data-tax="{{ $show($product->tax_rate) }}" data-income="{{ $product->income_account_id }}" @selected((int) $line['product_id'] === $product->id)>{{ $product->name }}</option>
                @endforeach
            </select>
        </td>
    @endif
    <td><input type="text" class="form-control form-control-sm" name="lines[{{ $i }}][description]" value="{{ $line['description'] }}" maxlength="255" required data-field="description" aria-label="{{ __('Description') }}"></td>
    <td><input type="text" inputmode="decimal" class="form-control form-control-sm" name="lines[{{ $i }}][quantity]" value="{{ $show($line['quantity']) }}" required dir="ltr" autocomplete="off" aria-label="{{ __('Quantity') }}"></td>
    <td><input type="text" inputmode="decimal" class="form-control form-control-sm" name="lines[{{ $i }}][unit_price]" value="{{ $show($line['unit_price']) }}" required dir="ltr" autocomplete="off" data-field="unit_price" aria-label="{{ __('Price') }}"></td>
    <td><input type="text" inputmode="decimal" class="form-control form-control-sm" name="lines[{{ $i }}][discount]" value="{{ $show($line['discount']) }}" dir="ltr" autocomplete="off" aria-label="{{ __('Discount') }}"></td>
    <td><input type="text" inputmode="decimal" class="form-control form-control-sm" name="lines[{{ $i }}][tax_rate]" value="{{ $show($line['tax_rate']) }}" dir="ltr" autocomplete="off" data-field="tax_rate" aria-label="{{ __('Tax %') }}"></td>
    <td>
        <select class="form-select form-select-sm" name="lines[{{ $i }}][income_account_id]" required data-plain data-field="income_account_id" aria-label="{{ __('Income account') }}">
            @foreach ($incomeAccounts as $account)
                <option value="{{ $account->id }}" @selected((int) $line['income_account_id'] === $account->id)>{{ $account->name }}</option>
            @endforeach
        </select>
    </td>
    <td><button type="button" class="btn-act is-red" data-remove-line title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}"><x-icon name="trash" /></button></td>
</tr>
