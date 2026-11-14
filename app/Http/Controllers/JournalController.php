<?php

namespace App\Http\Controllers;

use App\Accounting\Ledger;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Support\Dec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The general journal: every entry any module posted, plus manual entries an accountant writes and reverses here. */
class JournalController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        return view('journals.index', [
            'term' => $term,
            'entries' => JournalEntry::withSum('lines as total', 'base_debit')
                ->when(query_date('from'), fn ($q, $date) => $q->where('date', '>=', $date))
                ->when(query_date('until'), fn ($q, $date) => $q->where('date', '<=', $date))
                ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('description', 'like', '%'.$term.'%')->orWhere('reference', 'like', '%'.$term.'%')->orWhere('number', $term)))
                ->orderByDesc('date')->orderByDesc('id')
                ->paginate(per_page(25))->withQueryString(),
        ]);
    }

    public function show(Request $request): View
    {
        return view('journals.show', [
            'entry' => JournalEntry::with('lines.account', 'creator', 'reversalOf', 'reversedBy')->findOrFail($request->route('id')),
        ]);
    }

    /** Accounts a manual entry may not touch: the stock modules keep them equal to the stock records. */
    private const GUARDED = ['inventory', 'grni'];

    public function create(): View
    {
        return view('journals.form', [
            'accounts' => Account::postable()->where(fn ($q) => $q->whereNull('system_key')->orWhereNotIn('system_key', self::GUARDED))->orderBy('code')->get(['id', 'code', 'name', 'currency']),
        ]);
    }

    /** A manual journal entry in one currency. It must balance; the ledger refuses it otherwise. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', Rule::in(array_keys(currencies()))],
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'once' => ['required', 'uuid'],
            'lines' => ['required', 'array', 'min:2', 'max:200'],
            // the form shows spare rows: a row left empty is skipped, a row with an amount needs its account
            'lines.*.account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('company_id', company_id())->where(fn ($q) => $q->whereNull('system_key')->orWhereNotIn('system_key', self::GUARDED))],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $lines = [];
        $debits = '0';
        $credits = '0';
        foreach ($data['lines'] as $line) {
            $debit = Dec::round($line['debit'] ?? '0');
            $credit = Dec::round($line['credit'] ?? '0');
            if (Dec::isZero($debit) && Dec::isZero($credit)) {
                continue; // an empty row
            }
            if (empty($line['account_id'])) {
                throw new \App\Exceptions\UserError(__('Choose the account of every line that has an amount.'));
            }
            if (! Dec::isZero($debit) && ! Dec::isZero($credit)) {
                throw new \App\Exceptions\UserError(__('A line is a debit or a credit, not both.'));
            }
            $lines[] = ['account' => (int) $line['account_id'], 'debit' => $debit, 'credit' => $credit, 'currency' => $data['currency'], 'rate' => $data['rate'] ?? null, 'description' => $line['description'] ?? null];
            $debits = Dec::add($debits, $debit);
            $credits = Dec::add($credits, $credit);
        }
        if (count($lines) < 2 || Dec::cmp($debits, $credits) !== 0) {
            throw new \App\Exceptions\UserError(__('Debits (:debits) and credits (:credits) must be equal.', ['debits' => money($debits), 'credits' => money($credits)]));
        }

        $entry = Ledger::post(company(), ['date' => $data['date'], 'description' => $data['description'], 'reference' => $data['reference'] ?? null, 'idempotency_key' => 'manual-'.$data['once'], 'lines' => $lines]);
        AuditLog::record('posted manual journal', '#'.$entry->number);

        return redirect()->route('journals.show', ['id' => $entry->id])->with('success', __('Entry #:number posted.', ['number' => $entry->number]));
    }

    /** Reverse a manual entry. Entries made by a document are cancelled from that document, so the two stay together. */
    public function reverse(Request $request): RedirectResponse
    {
        $entry = JournalEntry::findOrFail($request->route('id'));
        if ($entry->source_type !== null) {
            throw new \App\Exceptions\UserError(__('This entry belongs to a document. Cancel the document instead.'));
        }
        $reversal = Ledger::reverse($entry, today_iso(), $request->validate(['reason' => ['nullable', 'string', 'max:200']])['reason'] ?? null);
        AuditLog::record('reversed manual journal', '#'.$entry->number);

        return redirect()->route('journals.show', ['id' => $reversal->id])->with('success', __('Entry #:number reversed.', ['number' => $entry->number]));
    }
}
