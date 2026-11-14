<?php

namespace App\Http\Controllers;

use App\Accounting\Transfers;
use App\Models\Account;
use App\Models\Transfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Account transfers and currency exchange between the company's own cash and bank accounts. */
class TransferController extends Controller
{
    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), [Transfer::TRANSFER, Transfer::EXCHANGE], true) ? $request->query('type') : null;

        return view('transfers.index', [
            'type' => $type,
            'transfers' => Transfer::with('fromAccount', 'toAccount')
                ->when($type, fn ($query) => $query->where('type', $type))
                ->orderByDesc('date')->orderByDesc('id')
                ->paginate(per_page(25))->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('transfers.form', [
            'accounts' => Account::postable()->cashOrBank()->orderBy('kind')->orderBy('name')->get(),
            'exchange' => $request->query('type') === Transfer::EXCHANGE,
            'once' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'from_account_id' => ['required', 'integer', own('accounts')],
            'to_account_id' => ['required', 'integer', 'different:from_account_id', own('accounts')],
            'from_amount' => ['required', 'numeric', 'gt:0'],
            'to_amount' => ['nullable', 'numeric', 'gt:0'],
            'to_rate' => ['nullable', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'once' => ['required', 'uuid'],
        ]);

        $transfer = Transfers::record(company(), ['idempotency_key' => $data['once']] + $data);

        return redirect()->route('transfers.index')->with('success', __(':code recorded.', ['code' => $transfer->code()]));
    }
}
