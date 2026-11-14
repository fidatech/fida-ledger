<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Inventory\Purchasing;
use App\Inventory\Stock;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Purchase orders with their receipts, supplier bills and returns. */
class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        return view('purchases.index', [
            'term' => $term,
            'status' => $status,
            'orders' => PurchaseOrder::with('supplier')
                ->when(in_array($status, ['draft', 'approved', 'closed', 'cancelled'], true), fn ($q) => $q->where('status', $status))
                ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('number', ltrim(preg_replace('/\D/', '', $term), '0') ?: '-1')->orWhereHas('supplier', fn ($a) => $a->where('name', 'like', '%'.$term.'%'))))
                ->orderByDesc('date')->orderByDesc('id')->paginate(per_page(25))->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return $this->form(null);
    }

    public function store(Request $request): RedirectResponse
    {
        $order = Purchasing::save(company(), $this->validated($request));

        return redirect()->route('purchases.show', ['id' => $order->id])->with('success', __(':code saved.', ['code' => $order->code()]));
    }

    public function edit(Request $request): View
    {
        return $this->form(PurchaseOrder::with('lines')->where('status', 'draft')->findOrFail($request->route('id')));
    }

    public function update(Request $request): RedirectResponse
    {
        $order = PurchaseOrder::where('status', 'draft')->findOrFail($request->route('id'));
        Purchasing::save(company(), $this->validated($request), $order);

        return redirect()->route('purchases.show', ['id' => $order->id])->with('success', __(':code saved.', ['code' => $order->code()]));
    }

    public function show(Request $request): View
    {
        $order = PurchaseOrder::with('supplier', 'warehouse', 'lines.product', 'events')->findOrFail($request->route('id'));

        return view('purchases.show', ['order' => $order, 'stocked' => $order->lines->mapWithKeys(fn ($line) => [$line->id => (bool) $line->product?->isStocked()])]);
    }

    /** approve, cancel, receive, bill or return: the step is the last part of the address. */
    public function act(Request $request): RedirectResponse
    {
        $order = PurchaseOrder::findOrFail($request->route('id'));
        $step = $request->route('step');
        // approving an order is a permission of its own: the person who writes orders need not be the one who approves them
        abort_unless(can_do($step === 'approve' ? 'purchases.approve' : 'purchases.manage'), 403);

        $data = $request->validate([
            'date' => [Rule::requiredIf(in_array($step, ['receive', 'bill', 'return'], true)), 'nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'qty' => ['nullable', 'array'],
            'qty.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $quantities = array_filter($data['qty'] ?? [], fn ($quantity) => $quantity !== null && $quantity !== '');

        $result = match ($step) {
            'approve' => Purchasing::approve($order),
            'cancel' => Purchasing::cancel($order),
            'receive' => Purchasing::receive($order, $quantities, $data['date']),
            'bill' => Purchasing::bill($order, $quantities ?: null, $data['date'], $data['reference'] ?? null, $data['due_date'] ?? null),
            'return' => Purchasing::returnGoods($order, $quantities, $data['date']),
        };
        AuditLog::record('purchase '.$step, $order->code());

        return back()->with('success', __(':code saved.', ['code' => $result->code()]));
    }

    private function form(?PurchaseOrder $order): View
    {
        Stock::defaultWarehouse(company());

        return view('purchases.form', [
            'order' => $order,
            'suppliers' => Account::postable()->where('kind', 'supplier')->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_account_id' => ['required', 'integer', own('accounts')],
            'warehouse_id' => ['required', 'integer', own('warehouses')],
            'date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date'],
            'currency' => ['required', Rule::in(array_keys(currencies()))],
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['nullable', 'integer', own('products')],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ], [], ['lines.*.description' => __('description'), 'lines.*.quantity' => __('quantity'), 'lines.*.unit_price' => __('price')]);
    }
}
