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
 * Opening a company: choose the version (its modules come with it) and the company details.
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
            // which plans include each Business module (set on the platform side)
            'includes' => \App\Models\Module::where('is_available', true)->pluck('plans', 'key')->all(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'first' => $request->user()->companies()->count() === 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::exists('plans', 'code')->where('is_active', true)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(config('ledger.company_types')))],
            'base_currency' => ['required', Rule::in(array_keys(config('ledger.currencies')))],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $plan = Plan::where('code', $data['plan'])->firstOrFail();
        // the modules come with the version: Companies::open() switches on everything the plan includes
        $company = Companies::open($request->user(), ['plan' => $plan] + $data);

        $request->session()->put('company_id', $company->id);
        AuditLog::record('opened company', $company->name, ['plan' => $plan->code], $company->id);

        return redirect()->route('billing.show')->with('success', __('Your company is created. Pay the subscription to start using it.'));
    }
}
