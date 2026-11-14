<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\CrudController;
use App\Models\Account;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The catalogue. /products/product lists products, /products/service lists services. */
class ProductController extends CrudController
{
    protected string $model = Product::class;

    protected string $route = 'products';

    protected string $title = 'Products';

    protected string $singular = 'Product';

    protected string $icon = 'boxes-stacked';

    protected ?string $managePermission = 'products.manage';

    protected array $search = ['name', 'sku', 'barcode'];

    protected array $with = ['category', 'unit'];

    private function type(): string
    {
        return request()->route('type') === Product::SERVICE ? Product::SERVICE : Product::PRODUCT;
    }

    public function callAction($method, $parameters)
    {
        if ($this->type() === Product::SERVICE) {
            $this->title = 'Services';
            $this->singular = 'Service';
            $this->icon = 'screwdriver-wrench';
        }

        return parent::callAction($method, $parameters);
    }

    protected function routeParams(): array
    {
        return ['type' => $this->type()];
    }

    protected function query(Request $request): Builder
    {
        return Product::query()->where('type', $this->type());
    }

    protected function fields(?Model $record): array
    {
        $service = $this->type() === Product::SERVICE;
        $fields = [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:255']],
            ['name' => 'sku', 'label' => 'Code (SKU)', 'col' => 3, 'rules' => ['nullable', 'string', 'max:60', Rule::unique('products', 'sku')->where('company_id', company_id())->ignore($record?->getKey())]],
            ['name' => 'barcode', 'label' => 'Barcode', 'col' => 3, 'rules' => ['nullable', 'string', 'max:60']],
            ['name' => 'product_category_id', 'label' => 'Category', 'type' => 'select', 'col' => 4, 'options' => ProductCategory::orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['nullable', 'integer', own('product_categories')], 'quick' => ['New category', 'product-categories.create']],
            ['name' => 'unit_id', 'label' => 'Unit', 'type' => 'select', 'col' => 4, 'options' => Unit::orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['nullable', 'integer', own('units')], 'quick' => ['New unit', 'units.create']],
            ['name' => 'income_account_id', 'label' => 'Income account', 'type' => 'select', 'col' => 4, 'options' => Account::postable()->where('class', 'income')->where(fn ($q) => $q->whereNull('system_key')->orWhere('system_key', '!=', 'exchange_difference'))->orderBy('code')->pluck('name', 'id')->all(), 'rules' => ['nullable', 'integer', own('accounts')], 'help' => 'Where its sales are counted. Empty uses the account chosen on the invoice.'],
            ['name' => 'sale_price', 'label' => 'Selling price', 'type' => 'decimal', 'col' => 3, 'rules' => ['nullable', 'numeric', 'min:0'], 'default' => '0'],
            ['name' => 'purchase_price', 'label' => 'Purchase price', 'type' => 'decimal', 'col' => 3, 'rules' => ['nullable', 'numeric', 'min:0'], 'default' => '0'],
            ['name' => 'tax_rate', 'label' => 'Tax %', 'type' => 'decimal', 'col' => 3, 'rules' => ['nullable', 'numeric', 'min:0', 'max:100'], 'default' => '0'],
        ];
        if (! $service) {
            $fields[] = ['name' => 'reorder_level', 'label' => 'Reorder level', 'type' => 'decimal', 'col' => 3, 'rules' => ['nullable', 'numeric', 'min:0'], 'default' => '0'];
            $fields[] = ['name' => 'track_stock', 'label' => 'Keep stock of this product', 'type' => 'checkbox', 'default' => true];
        }
        $fields[] = ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true];
        $fields[] = ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'col' => 12, 'rules' => ['nullable', 'string', 'max:2000']];

        return $fields;
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['type'] = $this->type();
        foreach (['sale_price', 'purchase_price', 'tax_rate', 'reorder_level'] as $number) {
            if (array_key_exists($number, $data)) {
                $data[$number] = $data[$number] ?? 0;
            }
        }
        if ($data['type'] === Product::SERVICE) {
            $data['track_stock'] = false; // a service never moves inventory
        }

        return $data;
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Product $product) => $product->name.($product->is_active ? '' : ' ('.__('inactive').')'),
            'Code (SKU)' => fn (Product $product) => $product->sku,
            'Category' => fn (Product $product) => $product->category?->name,
            'Unit' => fn (Product $product) => $product->unit?->symbol ?: $product->unit?->name,
            'Selling price' => fn (Product $product) => money($product->sale_price),
            'Purchase price' => fn (Product $product) => money($product->purchase_price),
        ];
    }

    protected function headerLinks(Request $request): array
    {
        return [
            ['label' => $this->type() === Product::SERVICE ? __('Products') : __('Services'), 'url' => route('products.index', ['type' => $this->type() === Product::SERVICE ? Product::PRODUCT : Product::SERVICE])],
            ['label' => __('Categories'), 'url' => route('product-categories.index')],
            ['label' => __('Units'), 'url' => route('units.index')],
        ];
    }
}
