<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\CrudController;
use App\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class DepartmentController extends CrudController
{
    protected string $model = Department::class;

    protected string $route = 'departments';

    protected string $title = 'Departments';

    protected string $singular = 'Department';

    protected string $icon = 'sitemap';

    protected ?string $managePermission = 'payroll.manage';

    protected function fields(?Model $record): array
    {
        return [['name' => 'name', 'label' => 'Name', 'col' => 12, 'rules' => ['required', 'string', 'max:150', Rule::unique('departments', 'name')->where('company_id', company_id())->ignore($record?->getKey())]]];
    }

    protected function columns(): array
    {
        return ['Name' => fn (Department $department) => $department->name];
    }
}
