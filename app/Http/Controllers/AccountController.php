<?php

namespace App\Http\Controllers;

use App\Accounting\Balances;
use App\Accounting\Chart;
use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\JournalLine;
use App\Support\Dec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Accounts: customers, suppliers, sarafs, cash, bank, personal, loans and the chart of accounts. */
class AccountController extends Controller
{
    /** How many lines one statement page shows at most; a longer period is narrowed with the dates. */
    private const STATEMENT_LIMIT = 500;

    public function index(Request $request): View
    {
        $kinds = config('ledger.kinds');
        $kind = array_key_exists($request->query('kind'), $kinds) ? $request->query('kind') : null;
        $term = trim((string) $request->query('q', ''));

        $accounts = Account::where('is_group', false)
            ->when($kind, fn ($query) => $query->where('kind', $kind))
            ->when(! $request->boolean('archived'), fn ($query) => $query->where('is_archived', false))
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$term.'%')->orWhere('code', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')))
            ->orderBy('code')
            ->paginate(per_page(25))
            ->withQueryString();

        return view('accounts.index', [
            'accounts' => $accounts,
            'balances' => Balances::forAccounts(company_id(), $accounts->pluck('id')->all()),
            'kinds' => $kinds,
            'kind' => $kind,
            'term' => $term,
        ]);
    }

    /** The whole chart as a tree, with the balance of every account in the base currency. */
    public function chart(): View
    {
        $accounts = Account::orderBy('code')->get();
        $balances = Balances::baseByAccount(company_id(), null, null);

        return view('accounts.chart', [
            'tree' => $accounts->groupBy(fn (Account $account) => (int) $account->parent_id),
            'balances' => $balances,
        ]);
    }

    public function create(Request $request): View
    {
        $kinds = config('ledger.kinds');
        $kind = array_key_exists($request->query('kind'), $kinds) ? $request->query('kind') : 'customer';

        return view('accounts.form', ['account' => null, 'kind' => $kind, 'kinds' => $kinds]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(config('ledger.kinds')))],
            'name' => ['required', 'string', 'max:150'],
            'currency' => ['nullable', Rule::in(array_keys(currencies()))],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // the opening balance is optional
            'opening_amount' => ['nullable', 'numeric', 'min:0'],
            'opening_side' => ['nullable', Rule::in(['debit', 'credit'])],
            'opening_currency' => ['nullable', Rule::in(array_keys(currencies()))],
            'opening_rate' => ['nullable', 'numeric', 'gt:0'],
            'opening_date' => ['nullable', 'date'],
        ]);

        $account = Chart::open(company(), $data);
        AuditLog::record('opened account', $account->name);

        return redirect()->route('accounts.show', ['id' => $account->id])->with('success', __('Account saved.'));
    }

    /** The detail page of an account: balance in each currency and its statement. */
    public function show(Request $request): View
    {
        $account = Account::where('is_group', false)->findOrFail($request->route('id'));
        $today = today_iso();
        $from = query_date('from') ?? substr($today, 0, 8).'01';
        $until = query_date('until') ?? $today;

        // what stood on the account before the period, per currency
        $opening = Balances::byCurrency($account, date('Y-m-d', strtotime($from.' -1 day')));

        $lines = JournalLine::with('entry')
            ->where('account_id', $account->id)
            ->whereBetween('date', [$from, $until])
            ->orderBy('date')->orderBy('id')
            ->limit(self::STATEMENT_LIMIT + 1)
            ->get();
        $cut = $lines->count() > self::STATEMENT_LIMIT;
        $lines = $lines->take(self::STATEMENT_LIMIT);

        // running balance per currency, shown with the sign people expect for this kind of account
        $running = $opening;
        $rows = [];
        foreach ($lines as $line) {
            $running[$line->currency] = Dec::add($running[$line->currency] ?? '0', Dec::sub($line->debit, $line->credit));
            $rows[] = ['line' => $line, 'balance' => Balances::natural($account->class, $running[$line->currency])];
        }

        return view('accounts.show', [
            'account' => $account,
            'balances' => array_map(fn ($balance) => Balances::natural($account->class, $balance), Balances::byCurrency($account)),
            'opening' => array_map(fn ($balance) => Balances::natural($account->class, $balance), $opening),
            'rows' => $rows,
            'cut' => $cut,
            'from' => $from,
            'until' => $until,
        ]);
    }

    public function edit(Request $request): View
    {
        $account = Account::where('is_group', false)->findOrFail($request->route('id'));

        return view('accounts.form', ['account' => $account, 'kind' => $account->kind, 'kinds' => config('ledger.kinds')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $account = Account::where('is_group', false)->findOrFail($request->route('id'));
        // the kind, the class and the currency of an account never change once it exists
        $account->update($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));

        return redirect()->route('accounts.show', ['id' => $account->id])->with('success', __('Account saved.'));
    }

    /** Archive or bring back. An account is never deleted, so its history stays in the books. */
    public function archive(Request $request): RedirectResponse
    {
        $account = Account::where('is_group', false)->findOrFail($request->route('id'));

        if (! $account->is_archived) {
            if ($account->system_key !== null) {
                throw new UserError(__('The system needs this account. It cannot be archived.'));
            }
            if (array_filter(Balances::byCurrency($account), fn ($balance) => ! Dec::isZero($balance))) {
                throw new UserError(__('This account still has a balance. Settle it before archiving.'));
            }
        }

        $account->update(['is_archived' => ! $account->is_archived]);
        AuditLog::record($account->is_archived ? 'archived account' : 'restored account', $account->name);

        return back()->with('success', $account->is_archived ? __('Account archived.') : __('Account restored.'));
    }
}
