<?php

namespace App\Http\Controllers;

use App\Accounting\Invoices;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Invoices and credit notes: drafts, issuing, the printable document and cancelling. */
class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->query('type') === Invoice::CREDIT_NOTE ? Invoice::CREDIT_NOTE : Invoice::INVOICE;
        $term = trim((string) $request->query('q', ''));
        $state = $request->query('state');
        $today = today_iso();

        $invoices = Invoice::with('customer')
            ->where('type', $type)
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('number', ltrim(preg_replace('/\D/', '', $term), '0') ?: '-1')->orWhere('reference', 'like', '%'.$term.'%')
                ->orWhereHas('customer', fn ($account) => $account->where('name', 'like', '%'.$term.'%'))))
            ->when(in_array($state, ['draft', 'void'], true), fn ($q) => $q->where('status', $state))
            ->when($state === 'unpaid', fn ($q) => $q->where('status', Invoice::ISSUED)->whereColumn('paid', '<', 'total'))
            ->when($state === 'overdue', fn ($q) => $q->where('status', Invoice::ISSUED)->whereColumn('paid', '<', 'total')->where('due_date', '<', $today))
            ->when($state === 'paid', fn ($q) => $q->where('status', Invoice::ISSUED)->whereColumn('paid', '>=', 'total'))
            ->when(query_date('from'), fn ($q, $date) => $q->where('date', '>=', $date))
            ->when(query_date('until'), fn ($q, $date) => $q->where('date', '<=', $date))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(per_page(25))->withQueryString();

        return view('invoices.index', [
            'type' => $type,
            'invoices' => $invoices,
            'term' => $term,
            'state' => $state,
            // what customers still owe on issued invoices, per currency
            'outstanding' => Invoice::where('type', Invoice::INVOICE)->where('status', Invoice::ISSUED)->groupBy('currency')->selectRaw('currency, SUM(total) - SUM(paid) AS due')->pluck('due', 'currency'),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form(null, $request->query('type') === Invoice::CREDIT_NOTE ? Invoice::CREDIT_NOTE : Invoice::INVOICE);
    }

    public function store(Request $request): RedirectResponse
    {
        $invoice = Invoices::saveDraft(company(), $this->validated($request));

        return $this->afterSave($request, $invoice);
    }

    public function edit(Request $request): View
    {
        $invoice = Invoice::with('lines')->where('status', Invoice::DRAFT)->findOrFail($request->route('id'));

        return $this->form($invoice, $invoice->type);
    }

    public function update(Request $request): RedirectResponse
    {
        $invoice = Invoice::where('status', Invoice::DRAFT)->findOrFail($request->route('id'));
        Invoices::saveDraft(company(), $this->validated($request), $invoice);

        return $this->afterSave($request, $invoice);
    }

    public function show(Request $request): View
    {
        return view('invoices.show', [
            'invoice' => Invoice::with('customer', 'lines.incomeAccount', 'payments.movement.cashAccount')->findOrFail($request->route('id')),
        ]);
    }

    public function issue(Request $request): RedirectResponse
    {
        $invoice = Invoices::issue(Invoice::findOrFail($request->route('id')));
        AuditLog::record('issued '.$invoice->code());

        return redirect()->route('invoices.show', ['id' => $invoice->id])->with('success', __(':code issued.', ['code' => $invoice->code()]));
    }

    public function void(Request $request): RedirectResponse
    {
        $invoice = Invoices::void(Invoice::findOrFail($request->route('id')));
        AuditLog::record('cancelled '.$invoice->code());

        return back()->with('success', __(':code cancelled.', ['code' => $invoice->code()]));
    }

    private function form(?Invoice $invoice, string $type): View
    {
        return view('invoices.form', [
            'invoice' => $invoice,
            'type' => $type,
            'customers' => Account::postable()->where('kind', 'customer')->orderBy('name')->get(),
            // every income account except "Exchange gain or loss", which only the system posts to
            'incomeAccounts' => Account::postable()->where('class', 'income')->where(fn ($q) => $q->whereNull('system_key')->orWhere('system_key', '!=', 'exchange_difference'))->orderBy('code')->get(),
            // the catalogue fills a line in one click when the Products module is on
            'products' => module_on('products') ? Product::where('is_active', true)->orderBy('name')->get() : collect(),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['nullable', Rule::in([Invoice::INVOICE, Invoice::CREDIT_NOTE])],
            'customer_account_id' => ['required', 'integer', own('accounts')],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'currency' => ['required', Rule::in(array_keys(currencies()))],
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.income_account_id' => ['required', 'integer', own('accounts')],
            'lines.*.product_id' => ['nullable', 'integer', own('products')],
        ], [], ['lines.*.description' => __('description'), 'lines.*.quantity' => __('quantity'), 'lines.*.unit_price' => __('price')]);
    }

    /** "Save and issue" posts it at once; "Save draft" leaves it open for changes. */
    private function afterSave(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($request->input('action') === 'issue') {
            Invoices::issue($invoice);
            AuditLog::record('issued '.$invoice->code());
        }

        return redirect()->route('invoices.show', ['id' => $invoice->id])->with('success', __(':code saved.', ['code' => $invoice->code()]));
    }
}
