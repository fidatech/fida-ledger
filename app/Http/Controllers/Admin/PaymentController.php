<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Support\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Payment approvals: the only way a subscription becomes active. */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), [SubscriptionPayment::PENDING, SubscriptionPayment::APPROVED, SubscriptionPayment::REJECTED], true)
            ? $request->query('status') : SubscriptionPayment::PENDING;

        return view('admin.payments', [
            'status' => $status,
            'payments' => SubscriptionPayment::with('company', 'plan', 'submitter')->where('status', $status)->latest('id')->paginate(per_page(25))->withQueryString(),
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        $this->reviewer($request);
        Billing::approve(SubscriptionPayment::findOrFail($request->route('id')), $request->user('admin'));

        return back()->with('success', __('Payment approved. The company is active.'));
    }

    public function reject(Request $request): RedirectResponse
    {
        $this->reviewer($request);
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(config('billing.reject_reasons')))]]);
        Billing::reject(SubscriptionPayment::findOrFail($request->route('id')), $request->user('admin'), $data['reason']);

        return back()->with('success', __('Payment rejected.'));
    }

    public function receipt(Request $request): StreamedResponse
    {
        $payment = SubscriptionPayment::findOrFail($request->route('id'));
        abort_unless($payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path), 404);

        return Storage::disk('local')->response($payment->receipt_path);
    }

    /** A viewer may look; only a super administrator or a manager decides. */
    private function reviewer(Request $request): void
    {
        abort_unless($request->user('admin')->canDecide(), 403);
    }
}
