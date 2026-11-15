<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Inventory\ProductImport;
use App\Inventory\Stock;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Warehouse;
use App\Support\Barcode;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Tools around the catalogue: bringing products in from a file, and printing barcode labels. */
class ProductToolsController extends Controller
{
    public function importForm(): View
    {
        $stock = $this->canStock();
        if ($stock) {
            Stock::defaultWarehouse(company());
        }

        return view('products.import', [
            'stock' => $stock,
            'warehouses' => $stock ? Warehouse::where('is_active', true)->orderBy('name')->get() : collect(),
        ]);
    }

    /** An example file with the right headings. */
    public function template(): StreamedResponse
    {
        return Csv::download('products-template', ['Name', 'Code', 'Barcode', 'Category', 'Unit', 'Selling price', 'Purchase price', 'Tax %', 'Reorder level', 'Quantity'], [
            ['Example product', 'P-0001', '8690000000011', 'General', 'Piece', '150', '100', '0', '5', '20'],
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $stock = $this->canStock();
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'warehouse_id' => ['nullable', 'integer', own('warehouses')],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $result = ProductImport::import(company(), $request->file('file')->getRealPath(), $stock && ! empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : null, $data['date'] ?? today_iso());
        AuditLog::record('imported products', $result['added'].' added, '.$result['updated'].' updated');

        return redirect()->route('products.index', ['type' => Product::PRODUCT])
            ->with('success', __(':added products added, :updated updated, opening stock entered for :stocked.', $result));
    }

    /** Choose what to print, or (with ?print=1) the sheet of labels itself. */
    public function labels(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $products = Product::with('category')->where('type', Product::PRODUCT)->where('is_active', true)
            ->when($request->integer('category'), fn ($q, $id) => $q->where('product_category_id', $id))
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('name', 'like', '%'.$term.'%')->orWhere('sku', 'like', '%'.$term.'%')->orWhere('barcode', 'like', '%'.$term.'%')))
            ->orderBy('name')->limit(300)->get();

        // the printed sheet: the chosen products, each as many times as asked (at most 500 labels)
        $labels = [];
        if ($request->boolean('print')) {
            $copies = (array) $request->query('copies', []);
            foreach ($products as $product) {
                $code = $product->barcode ?: $product->sku;
                $count = max(0, min(100, (int) ($copies[$product->id] ?? 0)));
                for ($i = 0; $i < $count && count($labels) < 500 && Barcode::fits($code); $i++) {
                    $labels[] = ['name' => $product->name, 'code' => $code, 'price' => $product->sale_price, 'svg' => Barcode::svg($code)];
                }
            }
        }

        return view($request->boolean('print') ? 'products.labels-print' : 'products.labels', [
            'products' => $products,
            'labels' => $labels,
            'categories' => ProductCategory::orderBy('name')->get(),
            'showPrice' => $request->boolean('price', ! $request->boolean('print')),
            'columns' => max(2, min(5, $request->integer('columns') ?: 3)),
        ]);
    }

    /** Opening stock can come with the file only where the Inventory module is on and the user may change stock. */
    private function canStock(): bool
    {
        return module_on('inventory') && can_do('inventory.manage');
    }
}
