<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\CrudController;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class UnitController extends CrudController
{
    protected string $model = Unit::class;

    protected string $route = 'units';

    protected string $title = 'Units';

    protected string $singular = 'Unit';

    protected string $icon = 'ruler';

    protected ?string $managePermission = 'products.manage';

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:60', Rule::unique('units', 'name')->where('company_id', company_id())->ignore($record?->getKey())], 'help' => 'Piece, kilogram, box, hour …'],
            ['name' => 'symbol', 'label' => 'Short form', 'rules' => ['nullable', 'string', 'max:20'], 'help' => 'pc, kg, box, h …'],
        ];
    }

    protected function columns(): array
    {
        return ['Name' => fn (Unit $unit) => $unit->name, 'Short form' => fn (Unit $unit) => $unit->symbol];
    }
}
