<?php

namespace App\Http\Controllers;

use App\Accounting\Money;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\MoneyMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Money In and Money Out: record, history, receipt and cancelling. {direction} in the address is "in" or "out". */
class MoneyController extends Controller
{
    public function index(Request $request): View
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
            'picked' => $request->integer('account') ?: null,
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
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('attachments/'.company_id(), 'local');
        }

        $movement = Money::record(company(), ['direction' => $direction, 'idempotency_key' => $data['once']] + $data);

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
