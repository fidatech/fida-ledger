<tr>
    <td>
        <select class="form-select form-select-sm" name="lines[{{ $i }}][product_id]" data-product data-plain aria-label="{{ __('Product or service') }}">
            <option value="">—</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" data-description="{{ $product->name }}" data-unit_price="{{ $num($product->{$priceField}) }}" data-tax_rate="{{ $selling ? $num($product->tax_rate) : '' }}" data-income_account_id="{{ $selling ? $product->income_account_id : '' }}" @selected((int) $line['product_id'] === $product->id)>{{ $product->name }}{{ $product->sku ? ' · '.$product->sku : '' }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="text" class="form-control form-control-sm" name="lines[{{ $i }}][description]" value="{{ $line['description'] }}" maxlength="255" required data-field="description" aria-label="{{ __('Description') }}"></td>
    <td><input type="text" inputmode="decimal" class="form-control form-control-sm" name="lines[{{ $i }}][quantity]" value="{{ $num($line['quantity']) }}" required dir="ltr" autocomplete="off" aria-label="{{ __('Quantity') }}"></td>
    <td><input type="text" inputmode="decimal" class="form-control form-control-sm" name="lines[{{ $i }}][unit_price]" value="{{ $num($line['unit_price']) }}" required dir="ltr" autocomplete="off" data-field="unit_price" aria-label="{{ __('Price') }}"></td>
    @if ($selling)
        <td><input type="text" inputmode="decimal" class="form-control form-control-sm" name="lines[{{ $i }}][discount]" value="{{ $num($line['discount']) }}" dir="ltr" autocomplete="off" aria-label="{{ __('Discount') }}"></td>
        <td><input type="text" inputmode="decimal" class="form-control form-control-sm" name="lines[{{ $i }}][tax_rate]" value="{{ $num($line['tax_rate']) }}" dir="ltr" autocomplete="off" data-field="tax_rate" aria-label="{{ __('Tax %') }}"></td>
        <td>
            <select class="form-select form-select-sm" name="lines[{{ $i }}][income_account_id]" required data-plain data-field="income_account_id" aria-label="{{ __('Income account') }}">
                @foreach ($incomeAccounts as $account)
                    <option value="{{ $account->id }}" @selected((int) $line['income_account_id'] === $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
        </td>
    @endif
    <td><button type="button" class="btn-act is-red" data-remove-line title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}"><x-icon name="trash" /></button></td>
</tr>
