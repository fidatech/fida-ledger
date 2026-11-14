<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\CrudController;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class EmployeeController extends CrudController
{
    protected string $model = Employee::class;

    protected string $route = 'employees';

    protected string $title = 'Employees';

    protected string $singular = 'Employee';

    protected string $icon = 'id-badge';

    protected ?string $managePermission = 'payroll.manage';

    protected array $with = ['department'];

    protected array $search = ['name', 'position', 'phone'];

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Full name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'position', 'label' => 'Position', 'col' => 3, 'rules' => ['nullable', 'string', 'max:150']],
            ['name' => 'department_id', 'label' => 'Department', 'type' => 'select', 'col' => 3, 'options' => Department::orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['nullable', 'integer', own('departments')], 'quick' => ['New department', 'departments.create']],
            ['name' => 'phone', 'label' => 'Phone', 'col' => 3, 'rules' => ['nullable', 'string', 'max:50']],
            ['name' => 'joined_on', 'label' => 'Joined on', 'type' => 'date', 'col' => 3, 'rules' => ['nullable', 'date']],
            ['name' => 'salary', 'label' => 'Salary structure (per month, '.base_currency().')', 'type' => 'heading', 'virtual' => true],
            ['name' => 'basic_salary', 'label' => 'Basic salary', 'type' => 'decimal', 'col' => 4, 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'allowances', 'label' => 'Allowances', 'type' => 'decimal', 'col' => 4, 'rules' => ['nullable', 'numeric', 'min:0'], 'default' => '0', 'help' => 'Transport, food, housing … added every month.'],
            ['name' => 'deductions', 'label' => 'Deductions', 'type' => 'decimal', 'col' => 4, 'rules' => ['nullable', 'numeric', 'min:0'], 'default' => '0', 'help' => 'Tax, pension … withheld every month. Set the amounts your rules require.'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function beforeSave(array $data, ?Model $record, \Illuminate\Http\Request $request): array
    {
        $data['allowances'] = $data['allowances'] ?? 0;
        $data['deductions'] = $data['deductions'] ?? 0;

        return $data;
    }

    protected function columns(): array
    {
        return [
            'Full name' => fn (Employee $employee) => $employee->name.($employee->is_active ? '' : ' ('.__('inactive').')'),
            'Position' => fn (Employee $employee) => $employee->position,
            'Department' => fn (Employee $employee) => $employee->department?->name,
            'Basic salary' => fn (Employee $employee) => money($employee->basic_salary),
            'Advance owed' => fn (Employee $employee) => \App\Support\Dec::isZero($employee->advance_balance) ? '' : money($employee->advance_balance),
        ];
    }

    /** An employee who was ever on a payroll is switched off, not deleted. */
    protected function deleteProblem(Model $record): ?string
    {
        return \App\Models\PayrollLine::where('employee_id', $record->getKey())->exists() || \App\Support\Dec::isPositive($record->advance_balance)
            ? __('This employee has payroll history. Switch him off instead of deleting.') : null;
    }
}
