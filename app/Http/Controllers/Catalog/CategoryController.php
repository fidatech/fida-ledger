<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\CrudController;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CategoryController extends CrudController
{
    protected string $model = ProductCategory::class;

    protected string $route = 'product-categories';

    protected string $title = 'Product Categories';

    protected string $singular = 'Category';

    protected string $icon = 'folder-tree';

    protected ?string $managePermission = 'products.manage';

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'col' => 12, 'rules' => ['required', 'string', 'max:150', Rule::unique('product_categories', 'name')->where('company_id', company_id())->ignore($record?->getKey())]],
        ];
    }

    protected function columns(): array
    {
        return ['Name' => fn (ProductCategory $category) => $category->name];
    }
}
