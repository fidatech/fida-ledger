<?php

namespace App\Http\Controllers\Banking;

use App\Accounting\Recurring;
use App\Http\Controllers\CrudController;
use App\Models\Account;
use App\Models\RecurringTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Recurring Money In and Money Out. /recurring/in and /recurring/out. */
class RecurringController extends CrudController
{
    protected string $model = RecurringTransaction::class;

    protected string $route = 'recurring';

    protected string $title = 'Recurring Money Out';

    protected string $singular = 'Recurring transaction';

    protected string $icon = 'arrows-rotate';

    protected ?string $managePermission = 'banking.manage';

    protected array $with = ['cashAccount', 'counterAccount'];

    private function direction(): string
    {
        return request()->route('direction') === 'in' ? 'in' : 'out';
    }

    public function callAction($method, $parameters)
    {
        $this->title = $this->direction() === 'in' ? 'Recurring Money In' : 'Recurring Money Out';

        return parent::callAction($method, $parameters);
    }

    protected function routeParams(): array
    {
        return ['direction' => $this->direction()];
    }

    protected function query(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return RecurringTransaction::query()->where('direction', $this->direction());
    }

    protected function fields(?Model $record): array
    {
        $kinds = array_map(fn (array $kind) => __($kind['label']), config('ledger.money.'.$this->direction()));

        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150'], 'help' => 'Shown as the description of every posting: "Office rent", "Internet".'],
            ['name' => 'kind', 'label' => 'What it is', 'type' => 'select', 'col' => 3, 'options' => $kinds, 'rules' => ['required', Rule::in(array_keys($kinds))]],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'decimal', 'col' => 3, 'rules' => ['required', 'numeric', 'gt:0'], 'help' => 'In the currency of the cash or bank account.'],
            ['name' => 'cash_account_id', 'label' => 'Cash or bank account', 'type' => 'select', 'options' => Account::postable()->cashOrBank()->orderBy('name')->get()->mapWithKeys(fn (Account $a) => [$a->id => $a->label()])->all(), 'rules' => ['required', 'integer', own('accounts')]],
            ['name' => 'counter_account_id', 'label' => 'Account it is for', 'type' => 'select', 'options' => Account::postable()->whereNotIn('kind', Account::CASH_KINDS)->orderBy('name')->get()->mapWithKeys(fn (Account $a) => [$a->id => $a->name.' · '.$a->kindLabel()])->all(), 'rules' => ['required', 'integer', own('accounts')], 'help' => 'It must fit what was chosen: an expense account for an expense, a supplier for a supplier payment.'],
            ['name' => 'frequency', 'label' => 'How often', 'type' => 'select', 'col' => 3, 'options' => array_map(fn ($label) => __($label), RecurringTransaction::FREQUENCIES), 'rules' => ['required', Rule::in(array_keys(RecurringTransaction::FREQUENCIES))], 'default' => 'monthly'],
            ['name' => 'next_run', 'label' => 'Next date', 'type' => 'date', 'col' => 3, 'rules' => ['required', 'date'], 'default' => today_iso()],
            ['name' => 'end_date', 'label' => 'Last date (optional)', 'type' => 'date', 'col' => 3, 'rules' => ['nullable', 'date', 'after_or_equal:next_run']],
            ['name' => 'missed_policy', 'label' => 'If dates were missed', 'type' => 'select', 'col' => 3, 'options' => ['all' => __('Post every missed date'), 'latest' => __('Post only the latest one')], 'rules' => ['required', Rule::in(['all', 'latest'])], 'default' => 'all'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['direction'] = $this->direction();
        $data['last_error'] = null;

        return $data;
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (RecurringTransaction $item) => $item->name.($item->is_active ? '' : ' ('.__('inactive').')'),
            'Amount' => fn (RecurringTransaction $item) => money($item->amount, $item->cashAccount?->currency ?? base_currency()),
            'Account' => fn (RecurringTransaction $item) => $item->counterAccount?->name,
            'How often' => fn (RecurringTransaction $item) => __(RecurringTransaction::FREQUENCIES[$item->frequency] ?? $item->frequency),
            'Next date' => fn (RecurringTransaction $item) => fmt_date($item->next_run),
            'Last posted' => fn (RecurringTransaction $item) => fmt_date($item->last_run),
            'Problem' => fn (RecurringTransaction $item) => (string) $item->last_error,
        ];
    }

    protected function headerLinks(Request $request): array
    {
        return [
            ['label' => $this->direction() === 'in' ? __('Recurring Money Out') : __('Recurring Money In'), 'url' => route('recurring.index', ['direction' => $this->direction() === 'in' ? 'out' : 'in'])],
        ];
    }

    protected function headerActions(Request $request): array
    {
        return [['label' => __('Post what is due now'), 'url' => route('recurring.run'), 'confirm' => __('Post every recurring transaction that is due?')]];
    }

    /** Post what is due now, without waiting for the hourly job. Safe to press again: a date is posted once. */
    public function run(): RedirectResponse
    {
        $result = Recurring::run(company());

        return back()->with($result['failed'] ? 'warning' : 'success', __(':posted posted. :failed need attention (see the "Problem" column).', $result));
    }
}
