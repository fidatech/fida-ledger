<?php

namespace App\Http\Controllers\Inventory;

use App\Accounting\Balances;
use App\Http\Controllers\Controller;
use App\Inventory\Stock;
use App\Models\Account;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\Csv;
use App\Support\Dec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stock overview and valuation, movements, transfers between warehouses, adjustments and counts. */
class InventoryController extends Controller
{
    /** What is on hand, what it is worth, what is running low, and whether stock agrees with the ledger. */
    public function overview(Request $request): View|StreamedResponse
    {
        $warehouseId = $request->integer('warehouse') ?: null;
        $term = trim((string) $request->query('q', ''));

        // one row per stocked product: quantity and value summed over the chosen warehouse(s), by the database
        $rows = Product::query()->where('products.type', Product::PRODUCT)->where('track_stock', true)
            ->leftJoin('stock_levels', fn ($join) => $join->on('stock_levels.product_id', '=', 'products.id')->when($warehouseId, fn ($q) => $q->where('stock_levels.warehouse_id', $warehouseId)))
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('products.name', 'like', '%'.$term.'%')->orWhere('products.sku', 'like', '%'.$term.'%')->orWhere('products.barcode', 'like', '%'.$term.'%')))
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.reorder_level')
            ->selectRaw('products.id, products.name, products.sku, products.reorder_level, COALESCE(SUM(stock_levels.quantity), 0) AS quantity, COALESCE(SUM(stock_levels.value), 0) AS value')
            ->when($request->boolean('low'), fn ($q) => $q->havingRaw('COALESCE(SUM(stock_levels.quantity), 0) <= products.reorder_level'))
            ->orderBy('products.name');

        if ($request->query('export') === 'csv') {
            abort_unless(can_do('reports.export'), 403);

            return Csv::download('stock', ['Product', 'SKU', 'Quantity', 'Value '.base_currency(), 'Average cost', 'Reorder level'], (function () use ($rows) {
                foreach ($rows->cursor() as $row) {
                    yield [$row->name, $row->sku, Stock::qty($row->quantity), Dec::round($row->value, 2), Dec::isPositive($row->quantity) ? Dec::div($row->value, $row->quantity) : '0', Stock::qty($row->reorder_level)];
                }
            })());
        }

        $stockValue = Dec::round(StockLevel::sum('value'));
        $ledgerValue = Balances::baseByAccount(company_id(), null, null)->get(Account::system(company_id(), 'inventory')->id, '0.0000');

        return view('inventory.overview', [
            'rows' => $rows->paginate(per_page(50))->withQueryString(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'warehouseId' => $warehouseId,
            'term' => $term,
            'stockValue' => $stockValue,
            'ledgerValue' => $ledgerValue,
            'reconciled' => Dec::cmp($stockValue, $ledgerValue) === 0,
        ]);
    }

    public function movements(Request $request): View
    {
        return view('inventory.movements', [
            'movements' => StockMovement::with('product', 'warehouse')
                ->when($request->integer('product'), fn ($q, $id) => $q->where('product_id', $id))
                ->when($request->integer('warehouse'), fn ($q, $id) => $q->where('warehouse_id', $id))
                ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
                ->when(query_date('from'), fn ($q, $date) => $q->where('date', '>=', $date))
                ->when(query_date('until'), fn ($q, $date) => $q->where('date', '<=', $date))
                ->orderByDesc('date')->orderByDesc('id')->paginate(per_page(50))->withQueryString(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'products' => Product::where('type', Product::PRODUCT)->where('track_stock', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** One form for three jobs: a stock transfer, an adjustment (+ or -), and a physical count (the counted quantity). */
    public function change(Request $request): View
    {
        $mode = in_array($request->query('mode'), ['transfer', 'adjust', 'count'], true) ? $request->query('mode') : 'adjust';
        Stock::defaultWarehouse(company());

        return view('inventory.change', [
            'mode' => $mode,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::where('type', Product::PRODUCT)->where('track_stock', true)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['transfer', 'adjust', 'count'])],
            'product_id' => ['required', 'integer', own('products')],
            'warehouse_id' => ['required', 'integer', own('warehouses')],
            'to_warehouse_id' => ['required_if:mode,transfer', 'nullable', 'integer', own('warehouses')],
            'quantity' => ['required', 'numeric'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:200'],
            'opening' => ['nullable', 'boolean'],
        ]);
        $company = company();
        $product = Product::findOrFail($data['product_id']);

        DB::transaction(function () use ($data, $company, $product) {
            if ($data['mode'] === 'transfer') {
                Stock::transfer($company, $product, (int) $data['warehouse_id'], (int) $data['to_warehouse_id'], $data['quantity'], $data['date'], $data['reason'] ?? null);

                return;
            }
            $change = $data['quantity'];
            if ($data['mode'] === 'count') {
                // a count gives what is really there: the difference from the books is adjusted
                $onHand = StockLevel::where('product_id', $product->id)->where('warehouse_id', $data['warehouse_id'])->lockForUpdate()->value('quantity') ?? '0';
                $change = Dec::sub($data['quantity'], $onHand);
            }
            // stock found or added comes in at the cost given, else at the product's purchase price
            Stock::adjust($company, $product, (int) $data['warehouse_id'], $change, $data['unit_cost'] ?? $product->purchase_price, $data['date'],
                $data['reason'] ?? ($data['mode'] === 'count' ? __('Physical count') : null), ! empty($data['opening']));
        });

        return redirect()->route('inventory.movements', ['product' => $product->id])->with('success', __('Stock updated.'));
    }
}
