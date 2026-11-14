<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Support\Billing;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Subscription & Billing of the current company: what it has, what is due, and sending a payment for approval. */
class BillingController extends Controller
{
    public function show(): View
    {
        $company = company();

        return view('billing.show', [
            'company' => $company,
            'quote' => Billing::quote($company),
            'waiting' => Billing::waiting($company),
            'canPay' => Billing::canPay($company) && can_do('billing.manage'),
            'payments' => $company->payments()->with('plan')->latest('id')->limit(20)->get(),
            'modules' => array_intersect_key(Modules::all(), array_flip(Modules::activeKeys($company))),
        ]);
    }

    public function pay(Request $request): RedirectResponse
    {
        abort_unless(can_do('billing.manage'), 403);

        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(config('billing.methods')))],
            'reference' => ['nullable', 'string', 'max:100'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        // receipts are kept on the private disk and only served through receipt() below
        $data['receipt_path'] = $request->file('receipt')->store('receipts/'.company_id(), 'local');
        Billing::submit(company(), $request->user(), $data);

        return redirect()->route('billing.show')->with('success', __('Thank you. Your payment is waiting for approval.'));
    }

    public function receipt(Request $request): StreamedResponse
    {
        $payment = SubscriptionPayment::where('company_id', company_id())->findOrFail($request->route('id'));
        abort_unless($payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path), 404);

        return Storage::disk('local')->response($payment->receipt_path);
    }
}
