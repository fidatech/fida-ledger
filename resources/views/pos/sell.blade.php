@extends('layouts.app', ['title' => __('Sell').' · '.$session->register->name])

@section('content')
@php
    $n = fn ($number) => rtrim(rtrim(\App\Support\Dec::round($number), '0'), '.') ?: '0';
    $mayPrice = can_do('sales.manage');
@endphp
<x-page-header :title="$session->register->name" icon="cash-register" :subtitle="__('Selling in :currency', ['currency' => base_currency()])" :crumbs="[[__('Point of Sale'), route('pos.index')], [__('Sell')]]">
    <a class="btn btn-outline-soft" href="{{ route('pos.session', ['id' => $session->id]) }}">{{ __('Sales of this session') }}</a>
</x-page-header>

@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ route('pos.store', ['id' => $session->id]) }}" id="posForm">
    @csrf
    <input type="hidden" name="once" value="{{ $once }}">
    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <input type="search" class="form-control form-control-lg mb-3" id="posSearch" placeholder="{{ __('Search or scan a barcode…') }}" autocomplete="off" autofocus aria-label="{{ __('Search') }}">
                    <div class="row g-2" id="posProducts" style="max-height:62vh;overflow-y:auto">
                        @foreach ($products as $product)
                            @php
                                $stocked = $product->isStocked();
                                $have = $stocked ? ($stock[$product->id] ?? '0') : null;
                            @endphp
                            <div class="col-6 col-md-4" data-item data-search="{{ mb_strtolower($product->name.' '.$product->sku.' '.$product->barcode) }}" data-barcode="{{ $product->barcode }}">
                                <button type="button" class="card card-hover w-100 h-100 mb-0 text-start" data-add data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $n($product->sale_price) }}" data-tax="{{ $n($product->tax_rate) }}" @disabled($stocked && ! \App\Support\Dec::isPositive($have))>
                                    <span class="card-body p-2 d-block">
                                        <span class="d-block fw-semibold small text-truncate">{{ $product->name }}</span>
                                        <span class="d-flex justify-content-between small"><span dir="ltr">{{ money($product->sale_price) }}</span>@if ($stocked)<span class="{{ \App\Support\Dec::isPositive($have) ? 'text-muted' : 'text-danger' }}" dir="ltr">{{ \App\Inventory\Stock::qty($have) }}</span>@endif</span>
                                    </span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                    @if ($products->isEmpty())
                        <p class="text-muted mb-0">{{ __('There are no products yet. Add them under Products & Services.') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card mb-0">
                <div class="card-body">
                    <label class="form-label small" for="customer_id">{{ __('Customer') }}</label>
                    <select class="form-select mb-3" id="customer_id" name="customer_id">
                        <option value="">{{ __('Walk-in customer') }}</option>
                        @foreach ($customers as $customer)
                            @continue($customer->name === 'Walk-in customer')
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>

                    <table class="table table-sm align-middle w-100">
                        <thead><tr><th>{{ __('Item') }}</th><th style="width:70px">{{ __('Qty') }}</th><th style="width:95px">{{ __('Price') }}</th><th style="width:80px">{{ __('Disc.') }}</th><th style="width:34px"></th></tr></thead>
                        <tbody id="posCart"><tr id="posEmpty"><td colspan="5" class="text-center text-muted py-4">{{ __('Tap a product to add it.') }}</td></tr></tbody>
                    </table>

                    <div class="d-flex justify-content-between small text-muted"><span>{{ __('Tax') }}</span><span dir="ltr" id="posTax">0.00</span></div>
                    <div class="d-flex justify-content-between fs-3 fw-bold mb-3"><span>{{ __('Total') }}</span><span dir="ltr" id="posTotal">0.00</span></div>

                    <div class="small fw-semibold text-muted mb-1">{{ __('Payment') }}</div>
                    @foreach ($payAccounts as $i => $account)
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text" style="min-width:45%">{{ $account->name }}</span>
                            <input type="hidden" name="payments[{{ $i }}][account_id]" value="{{ $account->id }}">
                            <input type="text" inputmode="decimal" class="form-control text-end" name="payments[{{ $i }}][amount]" data-pay dir="ltr" autocomplete="off" aria-label="{{ $account->name }}">
                            @if ($loop->first)<button type="button" class="btn btn-outline-soft" id="posExact">{{ __('All') }}</button>@endif
                        </div>
                    @endforeach
                    <div class="d-flex justify-content-between small"><span>{{ __('Still to pay') }}</span><span dir="ltr" class="fw-semibold" id="posLeft">0.00</span></div>
                    <div class="input-group input-group-sm mt-2">
                        <span class="input-group-text" style="min-width:45%">{{ __('Cash handed over') }}</span>
                        <input type="text" inputmode="decimal" class="form-control text-end" id="posTendered" dir="ltr" autocomplete="off" aria-label="{{ __('Cash handed over') }}">
                        <span class="input-group-text">{{ __('Change') }}: <strong class="ms-1" dir="ltr" id="posChange">0.00</strong></span>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100 mt-3" id="posPay" disabled><x-icon name="check" class="me-1" /> {{ __('Take payment') }}</button>
                    <p class="small text-muted mt-2 mb-0">{{ __('The payments must equal the total. Split a sale by typing an amount beside more than one account.') }}</p>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    // Amounts shown here are a preview. The server works the sale out again with exact decimals and refuses a payment that does not match.
    (function () {
        var cart = document.getElementById('posCart'), empty = document.getElementById('posEmpty');
        var mayPrice = {{ $mayPrice ? 'true' : 'false' }}, index = 0;
        var round = function (v) { return Math.round((v + Number.EPSILON) * 100) / 100; };
        var show = function (v) { return round(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        var num = function (box) { var v = parseFloat(String(box.value).replace(/,/g, '')); return isNaN(v) ? 0 : v; };
        var total = 0;

        function refresh() {
            var tax = 0; total = 0;
            cart.querySelectorAll('tr[data-row]').forEach(function (row) {
                var net = Math.max(0, num(row.querySelector('[data-q]')) * num(row.querySelector('[data-p]')) - num(row.querySelector('[data-d]')));
                var t = net * parseFloat(row.getAttribute('data-tax')) / 100;
                tax += t; total += net + t;
            });
            total = round(total);
            var paid = 0;
            document.querySelectorAll('[data-pay]').forEach(function (box) { paid += num(box); });
            document.getElementById('posTax').textContent = show(tax);
            document.getElementById('posTotal').textContent = show(total);
            document.getElementById('posLeft').textContent = show(total - paid);
            var first = document.querySelector('[data-pay]');
            document.getElementById('posChange').textContent = show(Math.max(0, num(document.getElementById('posTendered')) - (first ? num(first) : 0)));
            var rows = cart.querySelectorAll('tr[data-row]').length;
            empty.hidden = rows > 0;
            document.getElementById('posPay').disabled = rows === 0 || Math.abs(round(total - paid)) > 0.004;
        }

        function add(button) {
            var id = button.getAttribute('data-id');
            var row = cart.querySelector('tr[data-row="' + id + '"]');
            if (row) { var q = row.querySelector('[data-q]'); q.value = num(q) + 1; refresh(); return; }
            var i = index++;
            row = document.createElement('tr');
            row.setAttribute('data-row', id);
            row.setAttribute('data-tax', button.getAttribute('data-tax') || '0');
            var name = document.createElement('td');
            name.className = 'small';
            name.textContent = button.getAttribute('data-name');
            row.appendChild(name);
            var hidden = '<input type="hidden" name="items[' + i + '][product_id]" value="' + id + '">';
            var lock = mayPrice ? '' : ' readonly tabindex="-1"';
            row.insertAdjacentHTML('beforeend',
                '<td>' + hidden + '<input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="items[' + i + '][quantity]" value="1" data-q dir="ltr" autocomplete="off"></td>' +
                '<td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="items[' + i + '][unit_price]" value="' + button.getAttribute('data-price') + '" data-p dir="ltr" autocomplete="off"' + lock + '></td>' +
                '<td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="items[' + i + '][discount]" value="" data-d dir="ltr" autocomplete="off"' + lock + '></td>' +
                '<td><button type="button" class="btn-act is-red" data-remove aria-label="Remove">&times;</button></td>');
            cart.appendChild(row);
            refresh();
        }

        document.getElementById('posProducts').addEventListener('click', function (event) {
            var button = event.target.closest('[data-add]');
            if (button && !button.disabled) add(button);
        });
        cart.addEventListener('click', function (event) {
            if (event.target.closest('[data-remove]')) { event.target.closest('tr').remove(); refresh(); }
        });
        document.getElementById('posForm').addEventListener('input', refresh);
        document.getElementById('posExact') && document.getElementById('posExact').addEventListener('click', function () {
            document.querySelectorAll('[data-pay]').forEach(function (box, i) { box.value = i === 0 ? total.toFixed(2) : ''; });
            refresh();
        });

        // typing filters the products; Enter on an exact barcode (what a scanner sends) adds the product
        var search = document.getElementById('posSearch');
        search.addEventListener('input', function () {
            var term = search.value.trim().toLowerCase();
            document.querySelectorAll('[data-item]').forEach(function (item) { item.hidden = term !== '' && item.getAttribute('data-search').indexOf(term) === -1; });
        });
        search.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            var term = search.value.trim();
            if (!term) return;
            var hit = null, visible = [];
            document.querySelectorAll('[data-item]').forEach(function (item) {
                if (item.getAttribute('data-barcode') === term) hit = item;
                if (!item.hidden) visible.push(item);
            });
            hit = hit || (visible.length === 1 ? visible[0] : null);
            if (hit) {
                var button = hit.querySelector('[data-add]');
                if (!button.disabled) add(button);
                search.value = '';
                search.dispatchEvent(new Event('input'));
            }
        });
        // Enter in the cart must not send a half-finished sale
        document.getElementById('posForm').addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target.tagName === 'INPUT' && event.target.id !== 'posSearch') event.preventDefault();
        });
        // one tap on "Take payment" is one sale
        document.getElementById('posForm').addEventListener('submit', function () { document.getElementById('posPay').disabled = true; });
        refresh();
    })();
</script>
@endpush
@endsection
