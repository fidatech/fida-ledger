<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
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
}
