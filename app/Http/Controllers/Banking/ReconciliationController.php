<?php

namespace App\Http\Controllers\Banking;

use App\Accounting\Balances;
use App\Accounting\Reconciliation;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankStatementLine;
use App\Models\JournalLine;
use App\Support\Dec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Bank reconciliation of one cash or bank account: import, match, unmatch, and what is left to explain. */
class ReconciliationController extends Controller
{
    public function show(Request $request): View
    {
        $accounts = Account::cashOrBank()->where('is_archived', false)->orderBy('kind')->orderBy('name')->get();
        $account = $accounts->firstWhere('id', $request->integer('account')) ?? $accounts->firstWhere('kind', 'bank') ?? $accounts->first();

        $statement = BankStatementLine::where('account_id', $account->id);
        $matchedIds = (clone $statement)->whereNotNull('journal_line_id')->select('journal_line_id');
        $unmatchedStatement = (clone $statement)->whereNull('journal_line_id')->orderBy('date')->orderBy('id')->limit(200)->get();
        // since the first statement line: older entries were reconciled on paper before the import began
        $since = (clone $statement)->min('date');
        $unmatchedLedger = $since === null ? collect() : JournalLine::with('entry')->where('account_id', $account->id)->where('date', '>=', $since)->whereNotIn('id', $matchedIds)->orderBy('date')->orderBy('id')->limit(200)->get();

        // for each open statement line: the free entries of the same amount (any date), offered in a pick list
        $candidates = [];
        foreach ($unmatchedStatement as $line) {
            $candidates[$line->id] = Reconciliation::candidates($account, $line, null)->with('entry')->limit(8)->get();
        }

        $inBankNotBooks = Dec::sum($unmatchedStatement->pluck('amount'));
        $inBooksNotBank = Dec::sum($unmatchedLedger->map(fn ($line) => Dec::sub($line->debit, $line->credit)));

        return view('banking.reconciliation', [
            'accounts' => $accounts,
            'account' => $account,
            'unmatchedStatement' => $unmatchedStatement,
            'unmatchedLedger' => $unmatchedLedger,
            'candidates' => $candidates,
            'matched' => (clone $statement)->whereNotNull('journal_line_id')->with('journalLine.entry')->orderByDesc('matched_at')->limit(30)->get(),
            'matchedCount' => (clone $statement)->whereNotNull('journal_line_id')->count(),
            'bookBalance' => Balances::byCurrency($account)[$account->currency] ?? '0.0000',
            'inBankNotBooks' => $inBankNotBooks,
            'inBooksNotBank' => $inBooksNotBank,
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', own('accounts')],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);
        $account = Account::cashOrBank()->findOrFail($data['account_id']);
        $result = Reconciliation::import($account, $request->file('file')->getRealPath());
        $matched = Reconciliation::autoMatch($account);

        return redirect()->route('banking.reconciliation', ['account' => $account->id])
            ->with('success', __(':added lines imported (:skipped were already there). :matched matched by themselves.', ['added' => $result['added'], 'skipped' => $result['skipped'], 'matched' => $matched]));
    }

    public function match(Request $request): RedirectResponse
    {
        $line = BankStatementLine::findOrFail($request->route('id'));
        $data = $request->validate(['journal_line_id' => ['required', 'integer', own('journal_lines')]]);
        Reconciliation::match($line, JournalLine::findOrFail($data['journal_line_id']));

        return back()->with('success', __('Matched.'));
    }

    public function unmatch(Request $request): RedirectResponse
    {
        Reconciliation::unmatch(BankStatementLine::findOrFail($request->route('id')));

        return back()->with('success', __('Unmatched.'));
    }

    public function auto(Request $request): RedirectResponse
    {
        $account = Account::cashOrBank()->findOrFail($request->integer('account_id'));

        return back()->with('success', __(':count lines matched.', ['count' => Reconciliation::autoMatch($account)]));
    }

    /** Remove a statement line that should not be there (a wrong file). A matched line must be unmatched first. */
    public function destroy(Request $request): RedirectResponse
    {
        BankStatementLine::whereNull('journal_line_id')->findOrFail($request->route('id'))->delete();

        return back()->with('success', __('Statement line removed.'));
    }
}
