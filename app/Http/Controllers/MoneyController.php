<?php

namespace App\Http\Controllers;

use App\Accounting\Invoices;
use App\Accounting\Money;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\MoneyMovement;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Money In and Money Out: record, history, receipt and cancelling. {direction} in the address is "in" or "out". */
class MoneyController extends Controller
{
    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $direction = $request->route('direction');
        $term = trim((string) $request->query('q', ''));

        $query = MoneyMovement::where('direction', $direction)
            ->when(query_date('from'), fn ($q, $date) => $q->where('date', '>=', $date))
            ->when(query_date('until'), fn ($q, $date) => $q->where('date', '<=', $date))
            ->when($request->query('kind'), fn ($q, $kind) => $q->where('kind', $kind))
            ->when($request->query('currency'), fn ($q, $currency) => $q->where('currency', $currency))
            ->when($request->integer('account'), fn ($q, $id) => $q->where(fn ($inner) => $inner->where('cash_account_id', $id)->orWhere('counter_account_id', $id)))
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('description', 'like', '%'.$term.'%')->orWhere('reference', 'like', '%'.$term.'%')
                ->orWhereHas('counterAccount', fn ($account) => $account->where('name', 'like', '%'.$term.'%'))));

        if ($request->query('export') === 'csv') {
            abort_unless(can_do('reports.export'), 403);

            return Csv::download('money-'.$direction, ['No.', 'Date', 'Kind', 'Account', 'Cash or bank', 'Amount', 'Currency', 'Rate', 'Amount in '.base_currency(), 'Description', 'Reference', 'Status'],
                (function () use ($query) {
                    // read in pieces: a long history is never loaded whole
                    foreach ($query->with('cashAccount', 'counterAccount')->orderBy('date')->orderBy('id')->lazy(500) as $m) {
                        yield [$m->code(), $m->date->toDateString(), $m->kindLabel(), $m->counterAccount?->name, $m->cashAccount?->name, $m->amount, $m->currency, $m->rate, $m->base_amount, $m->description, $m->reference, $m->status];
                    }
                })());
        }

        return view('money.index', [
            'direction' => $direction,
            'totals' => (clone $query)->where('status', MoneyMovement::POSTED)->groupBy('currency')->selectRaw('currency, SUM(amount) AS total')->pluck('total', 'currency'),
            'movements' => $query->with('cashAccount', 'counterAccount')->orderByDesc('date')->orderByDesc('id')->paginate(per_page(25))->withQueryString(),
            'kinds' => config('ledger.money.'.$direction),
            'term' => $term,
        ]);
    }

    public function create(Request $request): View
    {
        $direction = $request->route('direction');
        $kinds = config('ledger.money.'.$direction);
        $kind = array_key_exists($request->query('kind'), $kinds) ? $request->query('kind') : array_key_first($kinds);

        // "Receive payment" on an invoice: the customer and the amount due are filled in
        $invoice = $direction === MoneyMovement::IN && $request->integer('invoice')
            ? Invoice::with('customer')->where('status', Invoice::ISSUED)->where('type', Invoice::INVOICE)->find($request->integer('invoice'))
            : null;
        if ($invoice) {
            $kind = 'customer_receipt';
        }

        // only the accounts that make sense for what was chosen: a supplier payment lists suppliers
        $counter = Account::postable()->whereNotIn('kind', Account::CASH_KINDS)
            ->when($kinds[$kind]['kinds'] !== null, fn ($query) => $query->whereIn('kind', $kinds[$kind]['kinds']))
            ->orderBy('name')->get();

        return view('money.form', [
            'direction' => $direction,
            'kinds' => $kinds,
            'kind' => $kind,
            'cashAccounts' => Account::postable()->cashOrBank()->orderBy('kind')->orderBy('name')->get(),
            'counterAccounts' => $counter,
            'picked' => $invoice?->customer_account_id ?? ($request->integer('account') ?: null),
            'invoice' => $invoice,
            'newKind' => $kinds[$kind]['kinds'][0] ?? null,
            // sent back with the form: the same form posted twice records one movement
            'once' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $direction = $request->route('direction');
        $data = $request->validate([
            'kind' => ['required', 'string'],
            'date' => ['required', 'date'],
            'cash_account_id' => ['required', 'integer', own('accounts')],
            'counter_account_id' => ['required', 'integer', own('accounts')],
            'amount' => ['required', 'numeric', 'gt:0'],
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'once' => ['required', 'uuid'],
            'invoice_id' => ['nullable', 'integer', own('invoices')],
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('attachments/'.company_id(), 'local');
        }

        // the money and its link to the invoice are saved together or not at all
        $movement = DB::transaction(function () use ($direction, $data) {
            $movement = Money::record(company(), ['direction' => $direction, 'idempotency_key' => $data['once']] + $data);
            if (! empty($data['invoice_id']) && $direction === MoneyMovement::IN && $movement->wasRecentlyCreated) {
                $invoice = Invoice::findOrFail($data['invoice_id']);
                // more than the invoice needs stays on the customer's account as credit
                Invoices::allocate($invoice, $movement);
            }

            return $movement;
        });

        return redirect()->route('money.show', ['direction' => $direction, 'id' => $movement->id])
            ->with('success', $direction === MoneyMovement::IN ? __('Money In recorded.') : __('Money Out recorded.'));
    }

    /** The receipt of one movement, with the journal entry it posted. */
    public function show(Request $request): View
    {
        $movement = MoneyMovement::with('cashAccount', 'counterAccount', 'entry.lines.account', 'creator')
            ->where('direction', $request->route('direction'))
            ->findOrFail($request->route('id'));

        return view('money.show', ['movement' => $movement, 'direction' => $movement->direction]);
    }

    /** Cancel: the journal entry is reversed; the movement stays in the history, marked cancelled. */
    public function void(Request $request): RedirectResponse
    {
        $movement = MoneyMovement::where('direction', $request->route('direction'))->findOrFail($request->route('id'));
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:200']])['reason'] ?? null;

        Money::void($movement, $reason);
        AuditLog::record('cancelled '.$movement->code(), $movement->description, ['reason' => $reason]);

        return back()->with('success', __('Cancelled. A reversing entry was posted.'));
    }

    public function attachment(Request $request): StreamedResponse
    {
        $movement = MoneyMovement::findOrFail($request->route('id'));
        abort_unless($movement->attachment_path && Storage::disk('local')->exists($movement->attachment_path), 404);

        return Storage::disk('local')->response($movement->attachment_path);
    }
}
