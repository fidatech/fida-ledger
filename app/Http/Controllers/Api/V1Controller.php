<?php

namespace App\Http\Controllers\Api;

use App\Accounting\Balances;
use App\Accounting\Money;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\MoneyMovement;
use App\Models\Product;
use App\Support\Modules;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Version 1 of the API (/api/v1). Every query runs inside the company of the key (the company
 * scope of the models), with the role of the key's user. Amounts are strings, never floats.
 */
class V1Controller extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $company = company();
        $user = $request->user('web');

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->roleIn($company)],
            'company' => [
                'id' => $company->id, 'name' => $company->name, 'base_currency' => $company->base_currency,
                'plan' => $company->plan?->code, 'modules' => Modules::activeKeys($company), 'today' => today_iso(),
            ],
        ]);
    }

    /** Cash and bank per currency, what customers owe and what is owed to suppliers. */
    public function summary(): JsonResponse
    {
        $company = company();
        $side = fn (array $kinds, string $class) => array_map(fn ($balance) => Balances::natural($class, $balance), Balances::ofKinds($company, $kinds)['currencies']);

        return response()->json([
            'date' => today_iso(),
            'cash_and_bank' => $side(Account::CASH_KINDS, 'asset'),
            'customers_owe' => can_do('accounts.view') ? $side(['customer'], 'asset') : null,
            'owed_to_suppliers' => can_do('accounts.view') ? $side(['supplier'], 'liability') : null,
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $page = Account::where('is_group', false)
            ->when($request->query('kind'), fn ($q, $kind) => $q->where('kind', $kind))
            ->when(! $request->boolean('archived'), fn ($q) => $q->where('is_archived', false))
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('name', 'like', '%'.$term.'%')->orWhere('code', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')))
            ->orderBy('code')
            ->paginate($this->size($request));

        return $this->page($page, fn (Account $account) => $this->accountRow($account));
    }

    public function account(Request $request): JsonResponse
    {
        $account = Account::where('is_group', false)->findOrFail($request->route('id'));
        $balances = array_map(fn ($balance) => Balances::natural($account->class, $balance), Balances::byCurrency($account));

        return response()->json(['data' => $this->accountRow($account) + ['balances' => $balances]]);
    }

    public function money(Request $request): JsonResponse
    {
        $page = MoneyMovement::with('cashAccount', 'counterAccount')
            ->when(in_array($request->query('direction'), ['in', 'out'], true), fn ($q) => $q->where('direction', $request->query('direction')))
            ->when(query_date('from'), fn ($q, $date) => $q->where('date', '>=', $date))
            ->when(query_date('until'), fn ($q, $date) => $q->where('date', '<=', $date))
            ->when($request->integer('account'), fn ($q, $id) => $q->where(fn ($inner) => $inner->where('cash_account_id', $id)->orWhere('counter_account_id', $id)))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate($this->size($request));

        return $this->page($page, fn (MoneyMovement $movement) => $this->movement($movement));
    }

    /**
     * Record Money In or Money Out: the same rules and the same ledger posting as the screen.
     * "idempotency_key" is required, so a request repeated after a lost answer records once.
     */
    public function storeMoney(Request $request): JsonResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'kind' => ['required', 'string'],
            'date' => ['required', 'date_format:Y-m-d'],
            'cash_account_id' => ['required', 'integer', own('accounts')],
            'counter_account_id' => ['required', 'integer', own('accounts')],
            'amount' => ['required', 'numeric', 'gt:0'],
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:80'],
        ]);

        $movement = Money::record(company(), $data);

        return response()->json(['data' => $this->movement($movement->load('cashAccount', 'counterAccount'))], $movement->wasRecentlyCreated ? 201 : 200);
    }

    public function invoices(Request $request): JsonResponse
    {
        $page = Invoice::with('customer')
            ->when(in_array($request->query('type'), [Invoice::INVOICE, Invoice::CREDIT_NOTE], true), fn ($q) => $q->where('type', $request->query('type')))
            ->when(in_array($request->query('status'), [Invoice::DRAFT, Invoice::ISSUED, Invoice::VOID], true), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->integer('customer'), fn ($q, $id) => $q->where('customer_account_id', $id))
            ->when(query_date('from'), fn ($q, $date) => $q->where('date', '>=', $date))
            ->when(query_date('until'), fn ($q, $date) => $q->where('date', '<=', $date))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate($this->size($request));

        return $this->page($page, fn (Invoice $invoice) => $this->invoiceRow($invoice));
    }

    public function invoice(Request $request): JsonResponse
    {
        $invoice = Invoice::with('customer', 'lines')->findOrFail($request->route('id'));

        return response()->json(['data' => $this->invoiceRow($invoice) + [
            'notes' => $invoice->notes,
            'lines' => $invoice->lines->map(fn ($line) => [
                'description' => $line->description, 'product_id' => $line->product_id, 'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price, 'discount' => (string) $line->discount, 'tax_rate' => (string) $line->tax_rate, 'total' => (string) $line->total,
            ])->all(),
        ]]);
    }

    public function products(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $page = Product::query()
            ->when(! $request->boolean('inactive'), fn ($q) => $q->where('is_active', true))
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('name', 'like', '%'.$term.'%')->orWhere('sku', 'like', '%'.$term.'%')->orWhere('barcode', $term)))
            ->orderBy('name')
            ->paginate($this->size($request));

        return $this->page($page, fn (Product $product) => [
            'id' => $product->id, 'type' => $product->type, 'name' => $product->name, 'sku' => $product->sku, 'barcode' => $product->barcode,
            'sale_price' => (string) $product->sale_price, 'tax_rate' => (string) $product->tax_rate, 'track_stock' => (bool) $product->track_stock, 'is_active' => (bool) $product->is_active,
        ]);
    }

    private function accountRow(Account $account): array
    {
        return [
            'id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'class' => $account->class, 'kind' => $account->kind,
            'currency' => $account->currency, 'phone' => $account->phone, 'email' => $account->email, 'is_archived' => (bool) $account->is_archived,
        ];
    }

    private function movement(MoneyMovement $movement): array
    {
        return [
            'id' => $movement->id, 'code' => $movement->code(), 'direction' => $movement->direction, 'kind' => $movement->kind, 'date' => input_date($movement->date),
            'amount' => (string) $movement->amount, 'currency' => $movement->currency, 'rate' => (string) $movement->rate, 'base_amount' => (string) $movement->base_amount,
            'cash_account' => ['id' => $movement->cash_account_id, 'name' => $movement->cashAccount?->name],
            'counter_account' => ['id' => $movement->counter_account_id, 'name' => $movement->counterAccount?->name],
            'description' => $movement->description, 'reference' => $movement->reference, 'status' => $movement->status,
        ];
    }

    private function invoiceRow(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id, 'code' => $invoice->code(), 'type' => $invoice->type, 'status' => $invoice->status, 'state' => $invoice->state(),
            'customer' => ['id' => $invoice->customer_account_id, 'name' => $invoice->customer?->name],
            'date' => input_date($invoice->date), 'due_date' => input_date($invoice->due_date), 'currency' => $invoice->currency,
            'subtotal' => (string) $invoice->subtotal, 'tax' => (string) $invoice->tax, 'total' => (string) $invoice->total, 'paid' => (string) $invoice->paid, 'due' => $invoice->due(),
            'reference' => $invoice->reference,
        ];
    }

    private function size(Request $request): int
    {
        return max(1, min(100, $request->integer('per_page') ?: 25));
    }

    private function page(LengthAwarePaginator $page, callable $row): JsonResponse
    {
        return response()->json([
            'data' => array_map($row, $page->items()),
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }
}
