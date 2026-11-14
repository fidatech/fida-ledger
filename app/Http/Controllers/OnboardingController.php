<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Support\Companies;
use App\Support\Modules;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Opening a company: choose the version, the modules (Business) and the company details.
 * Used right after registration and by "Add a company" in the company switcher.
 * The company starts pending and goes to its payment page.
 */
class OnboardingController extends Controller
{
    public function create(Request $request): View
    {
        return view('onboarding.create', [
            'plans' => Plan::onOffer(),
            'modules' => Modules::all(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'first' => $request->user()->companies()->count() === 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::exists('plans', 'code')->where('is_active', true)],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(Modules::all()))],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(config('ledger.company_types')))],
            'base_currency' => ['required', Rule::in(array_keys(config('ledger.currencies')))],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $plan = Plan::where('code', $data['plan'])->firstOrFail();
        $company = Companies::open($request->user(), [
            'plan' => $plan,
            // the checkboxes of the form: an empty list means the user unticked every module
            'modules' => $plan->code === Plan::BUSINESS ? ($data['modules'] ?? []) : [],
        ] + $data);

        $request->session()->put('company_id', $company->id);
        AuditLog::record('opened company', $company->name, ['plan' => $plan->code], $company->id);

        return redirect()->route('billing.show')->with('success', __('Your company is created. Pay the subscription to start using it.'));
    }
}
