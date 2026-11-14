<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Support\Dec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Subscription plans: setup fee, annual fee and user limit of Starter and Business. */
class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans', ['plans' => Plan::orderBy('sort')->orderBy('id')->get()]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->isSuper(), 403);
        $plan = Plan::findOrFail($request->route('id'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'setup_fee' => ['required', 'numeric', 'min:0'],
            'annual_fee' => ['required', 'numeric', 'gt:0'], // there is no free plan
            'max_users' => ['nullable', 'integer', 'min:1'],
        ]);
        $data['setup_fee'] = Dec::round($data['setup_fee'], 2);
        $data['annual_fee'] = Dec::round($data['annual_fee'], 2);

        $before = $plan->only('setup_fee', 'annual_fee', 'max_users');
        $plan->update($data);
        // new prices apply to payments sent from now on; payments already sent keep the amount they were quoted
        AuditLog::record('changed plan', $plan->code, ['before' => $before, 'after' => $plan->only('setup_fee', 'annual_fee', 'max_users')]);

        return back()->with('success', __(':name saved.', ['name' => $plan->name]));
    }
}
