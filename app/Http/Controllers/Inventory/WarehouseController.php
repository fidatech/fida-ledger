<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\CrudController;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class WarehouseController extends CrudController
{
    protected string $model = Warehouse::class;

    protected string $route = 'warehouses';

    protected string $title = 'Warehouses';

    protected string $singular = 'Warehouse';

    protected string $icon = 'warehouse';

    protected ?string $managePermission = 'inventory.manage';

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150', Rule::unique('warehouses', 'name')->where('company_id', company_id())->ignore($record?->getKey())]],
            ['name' => 'location', 'label' => 'Location', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Warehouse $warehouse) => $warehouse->name.($warehouse->is_active ? '' : ' ('.__('inactive').')'),
            'Location' => fn (Warehouse $warehouse) => $warehouse->location,
        ];
    }
}
