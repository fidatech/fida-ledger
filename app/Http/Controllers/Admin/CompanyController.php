<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Plan;
use App\Support\Modules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Companies on the platform: status and subscription only. An administrator never sees inside a company's books. */
class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $today = now()->toDateString();

        return view('admin.companies', [
            'term' => $term,
            'status' => $status,
            'companies' => Company::with('plan')->withCount('users')
                ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))
                ->when($status === 'pending', fn ($q) => $q->where('status', Company::PENDING))
                ->when($status === 'suspended', fn ($q) => $q->where('status', Company::SUSPENDED))
                ->when($status === 'active', fn ($q) => $q->where('status', Company::ACTIVE)->where('subscription_ends_at', '>=', $today))
                ->when($status === 'ended', fn ($q) => $q->where('status', Company::ACTIVE)->where('subscription_ends_at', '<', $today))
                ->latest('id')->paginate(per_page(25))->withQueryString(),
        ]);
    }

    /** Suspend a company, or restore a suspended one to where its subscription stands. */
    public function suspend(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->canDecide(), 403);
        $company = Company::findOrFail($request->route('id'));

        if ($company->status === Company::SUSPENDED) {
            // back to "active" only if it ever paid; otherwise it is still waiting for its first payment
            $company->update(['status' => $company->setup_fee_paid ? Company::ACTIVE : Company::PENDING]);
            AuditLog::record('restored company', $company->name, [], $company->id);

            return back()->with('success', __(':name restored.', ['name' => $company->name]));
        }
        if ($company->status === Company::PENDING) {
            throw new UserError(__('This company has not paid yet. There is nothing to suspend.'));
        }

        $company->update(['status' => Company::SUSPENDED]);
        AuditLog::record('suspended company', $company->name, [], $company->id);

        return back()->with('success', __(':name suspended.', ['name' => $company->name]));
    }

    /** One company: its plan, subscription, users and payments. Never its books. */
    public function show(Request $request): View
    {
        $company = Company::with('plan')->findOrFail($request->route('id'));

        return view('admin.company', [
            'company' => $company,
            'plans' => Plan::orderBy('sort')->get(),
            'members' => $company->users()->orderBy('users.name')->get(),
            'payments' => $company->payments()->with('plan')->latest('id')->limit(20)->get(),
            'modules' => array_intersect_key(Modules::all(), array_flip(Modules::activeKeys($company))),
            'logs' => AuditLog::where('company_id', $company->id)->whereNotNull('admin_id')->latest('id')->limit(15)->get(),
        ]);
    }

    /**
     * Change a company's version or the day its subscription ends. A change of version switches
     * the company's modules to what the new version includes; switching modules off deletes nothing.
     * No money is charged here: an upgrade's price is agreed and collected outside this action.
     */
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->isSuper(), 403);
        $company = Company::with('plan')->findOrFail($request->route('id'));
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')],
            'subscription_ends_at' => ['nullable', 'date'],
            'note' => ['required', 'string', 'max:255'], // why: it goes into the audit log
        ]);

        DB::transaction(function () use ($company, $data) {
            $before = ['plan' => $company->plan?->code, 'ends' => $company->subscription_ends_at?->toDateString()];
            $company->update(['plan_id' => $data['plan_id'], 'subscription_ends_at' => $data['subscription_ends_at'] ?? $company->subscription_ends_at]);
            $company->load('plan');
            Modules::set($company, Modules::entitled($company));
            AuditLog::record('changed company subscription', $company->name, ['before' => $before, 'after' => ['plan' => $company->plan->code, 'ends' => $company->subscription_ends_at?->toDateString()], 'note' => $data['note']], $company->id);
        });

        return back()->with('success', __(':name saved.', ['name' => $company->name]));
    }
}
