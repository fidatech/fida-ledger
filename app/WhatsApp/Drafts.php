<?php

namespace App\WhatsApp;

use App\Accounting\Balances;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\MoneyMovement;
use App\Models\WhatsappSetting;
use Illuminate\Http\Request;

/** Writes the first version of a message about an invoice, a receipt or an account. The user reads it before it is sent. */
class Drafts
{
    /** @return array{to: string, body: string, kind: string, reference: ?string, back: string, about: string} */
    public static function for(Request $request): array
    {
        $company = company();
        $setting = WhatsappSetting::of($company);
        $today = fmt_date(today_iso());

        if ($id = $request->query('invoice')) {
            abort_unless(can_do('invoices.view'), 403);
            $invoice = Invoice::with('customer')->findOrFail($id);

            return [
                'to' => (string) $invoice->customer->phone,
                'body' => WhatsApp::fill($setting->template('invoice'), [
                    'customer' => $invoice->customer->name, 'company' => $company->name, 'code' => $invoice->code(),
                    'date' => fmt_date($invoice->date), 'due_date' => fmt_date($invoice->due_date),
                    'total' => money($invoice->total, $invoice->currency), 'due' => money($invoice->due(), $invoice->currency),
                ]),
                'kind' => 'invoice', 'reference' => $invoice->code(), 'about' => $invoice->code().' · '.$invoice->customer->name,
                'back' => route('invoices.show', ['id' => $invoice->id], false),
            ];
        }

        if ($id = $request->query('money')) {
            abort_unless(can_do('money.view'), 403);
            $movement = MoneyMovement::with('counterAccount')->where('direction', MoneyMovement::IN)->findOrFail($id);

            return [
                'to' => (string) $movement->counterAccount->phone,
                'body' => WhatsApp::fill($setting->template('receipt'), [
                    'customer' => $movement->counterAccount->name, 'company' => $company->name, 'code' => $movement->code(),
                    'date' => fmt_date($movement->date), 'amount' => money($movement->amount, $movement->currency),
                ]),
                'kind' => 'receipt', 'reference' => $movement->code(), 'about' => $movement->code().' · '.$movement->counterAccount->name,
                'back' => route('money.show', ['direction' => 'in', 'id' => $movement->id], false),
            ];
        }

        if ($id = $request->query('account')) {
            abort_unless(can_do('accounts.view'), 403);
            $account = Account::where('is_group', false)->findOrFail($id);
            $balances = array_map(fn ($balance) => Balances::natural($account->class, $balance), Balances::byCurrency($account));

            return [
                'to' => (string) $account->phone,
                'body' => WhatsApp::fill($setting->template('statement'), [
                    'customer' => $account->name, 'company' => $company->name, 'date' => $today, 'balance' => sums($balances),
                ]),
                'kind' => 'statement', 'reference' => $account->code, 'about' => $account->name,
                'back' => route('accounts.show', ['id' => $account->id], false),
            ];
        }

        return ['to' => '', 'body' => '', 'kind' => 'custom', 'reference' => null, 'about' => '', 'back' => route('whatsapp.index', [], false)];
    }
}
