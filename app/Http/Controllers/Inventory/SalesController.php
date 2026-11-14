<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Inventory\Sales;
use App\Inventory\Stock;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Quotations and sales orders with their deliveries, invoices and returns. {type} is "quotation" or "order". */
class SalesController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->route('doc');
        $term = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        return view('sales.index', [
            'type' => $type,
            'term' => $term,
            'status' => $status,
            'orders' => SalesOrder::with('customer')->where('type', $type)
                ->when(in_array($status, ['draft', 'confirmed', 'closed', 'cancelled'], true), fn ($q) => $q->where('status', $status))
                ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('number', ltrim(preg_replace('/\D/', '', $term), '0') ?: '-1')->orWhereHas('customer', fn ($a) => $a->where('name', 'like', '%'.$term.'%'))))
                ->orderByDesc('date')->orderByDesc('id')->paginate(per_page(25))->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form(null, $request->route('doc'));
    }

    public function store(Request $request): RedirectResponse
    {
        $order = Sales::save(company(), ['type' => $request->route('doc')] + $this->validated($request));

        return $this->saved($order);
    }

    public function edit(Request $request): View
    {
        $order = SalesOrder::with('lines')->where('type', $request->route('doc'))->where('status', 'draft')->findOrFail($request->route('id'));

        return $this->form($order, $order->type);
    }

    public function update(Request $request): RedirectResponse
    {
        $order = SalesOrder::where('type', $request->route('doc'))->where('status', 'draft')->findOrFail($request->route('id'));
        Sales::save(company(), $this->validated($request), $order);

        return $this->saved($order);
    }

    public function show(Request $request): View
    {
        return view('sales.show', [
            'order' => SalesOrder::with('customer', 'warehouse', 'lines.product', 'events', 'invoices')->where('type', $request->route('doc'))->findOrFail($request->route('id')),
        ]);
    }

    /** confirm, cancel, deliver, invoice or return. */
    public function act(Request $request): RedirectResponse
    {
        $order = SalesOrder::where('type', $request->route('doc'))->findOrFail($request->route('id'));
        $step = $request->route('step');
        $data = $request->validate([
            'date' => [Rule::requiredIf(in_array($step, ['deliver', 'invoice', 'return'], true)), 'nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'qty' => ['nullable', 'array'],
            'qty.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $quantities = array_filter($data['qty'] ?? [], fn ($quantity) => $quantity !== null && $quantity !== '');

        $result = match ($step) {
            'confirm' => Sales::confirm($order),
            'cancel' => Sales::cancel($order),
            'deliver' => Sales::deliver($order, $quantities ?: null, $data['date']),
            'invoice' => Sales::invoice($order, $data['date'], $data['due_date'] ?? null),
            'return' => Sales::returnGoods($order, $quantities, $data['date']),
        };
        AuditLog::record('sales '.$step, $order->code());

        // a confirmed quotation continues as its new order; an invoice opens for printing and payment
        if ($result instanceof SalesOrder && $result->id !== $order->id) {
            return redirect()->route('sales.show', ['doc' => $result->type, 'id' => $result->id])->with('success', __(':code created.', ['code' => $result->code()]));
        }
        if ($step === 'invoice') {
            return redirect()->route('invoices.show', ['id' => $result->id])->with('success', __(':code issued.', ['code' => $result->code()]));
        }

        return back()->with('success', __(':code saved.', ['code' => $result->code()]));
    }

    private function saved(SalesOrder $order): RedirectResponse
    {
        return redirect()->route('sales.show', ['doc' => $order->type, 'id' => $order->id])->with('success', __(':code saved.', ['code' => $order->code()]));
    }

    private function form(?SalesOrder $order, string $type): View
    {
        Stock::defaultWarehouse(company());

        return view('sales.form', [
            'order' => $order,
            'type' => $type,
            'customers' => Account::postable()->where('kind', 'customer')->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'incomeAccounts' => Account::postable()->where('class', 'income')->where(fn ($q) => $q->whereNull('system_key')->orWhere('system_key', '!=', 'exchange_difference'))->orderBy('code')->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_account_id' => ['required', 'integer', own('accounts')],
            'warehouse_id' => ['required', 'integer', own('warehouses')],
            'date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date'],
            'currency' => ['required', Rule::in(array_keys(currencies()))],
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['nullable', 'integer', own('products')],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.income_account_id' => ['required', 'integer', own('accounts')],
        ], [], ['lines.*.description' => __('description'), 'lines.*.quantity' => __('quantity'), 'lines.*.unit_price' => __('price')]);
    }
}
