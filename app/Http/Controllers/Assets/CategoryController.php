<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\CrudController;
use App\Models\AssetCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CategoryController extends CrudController
{
    protected string $model = AssetCategory::class;

    protected string $route = 'asset-categories';

    protected string $title = 'Asset Categories';

    protected string $singular = 'Category';

    protected string $icon = 'folder-tree';

    protected ?string $managePermission = 'assets.manage';

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150', Rule::unique('asset_categories', 'name')->where('company_id', company_id())->ignore($record?->getKey())]],
            ['name' => 'useful_life_months', 'label' => 'Useful life (months)', 'type' => 'number', 'col' => 3, 'rules' => ['required', 'integer', 'min:1', 'max:1200'], 'default' => 60, 'help' => 'Offered when an asset of this category is added.'],
        ];
    }

    protected function columns(): array
    {
        return ['Name' => fn (AssetCategory $category) => $category->name, 'Useful life (months)' => fn (AssetCategory $category) => $category->useful_life_months];
    }
}
