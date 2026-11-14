<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\CrudController;
use App\Inventory\Stock;
use App\Models\Account;
use App\Models\PosRegister;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** POS registers: each has its own cash drawer (a cash account in the base currency) and sells from one warehouse. */
class RegisterController extends CrudController
{
    protected string $model = PosRegister::class;

    protected string $route = 'pos.registers';

    protected string $title = 'Registers';

    protected string $singular = 'Register';

    protected string $icon = 'cash-register';

    protected ?string $managePermission = 'settings.manage';

    protected array $with = ['warehouse', 'cashAccount'];

    protected function fields(?Model $record): array
    {
        Stock::defaultWarehouse(company());

        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150', Rule::unique('pos_registers', 'name')->where('company_id', company_id())->ignore($record?->getKey())]],
            ['name' => 'warehouse_id', 'label' => 'Sells from warehouse', 'type' => 'select', 'col' => 3, 'options' => Warehouse::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['required', 'integer', own('warehouses')]],
            ['name' => 'cash_account_id', 'label' => 'Cash drawer account', 'type' => 'select', 'col' => 3,
                'options' => Account::postable()->where('kind', 'cash')->where('currency', base_currency())->orderBy('name')->pluck('name', 'id')->all(),
                'rules' => ['required', 'integer', Rule::exists('accounts', 'id')->where('company_id', company_id())->where('kind', 'cash')->where('currency', base_currency())],
                'help' => 'A cash account in the base currency. Give each register its own, so each drawer can be counted.'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (PosRegister $register) => $register->name.($register->is_active ? '' : ' ('.__('inactive').')'),
            'Warehouse' => fn (PosRegister $register) => $register->warehouse?->name,
            'Cash drawer account' => fn (PosRegister $register) => $register->cashAccount?->name,
        ];
    }
}
