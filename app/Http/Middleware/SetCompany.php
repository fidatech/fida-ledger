<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides which company the request works in, and proves the user belongs to it.
 * The company comes from the session (the switcher in the header), else the one the user
 * worked in last, else his first. It is never taken from the address or from form input,
 * so changing an id in a URL cannot open another company's books.
 */
class SetCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');
        if (! $user->is_active) {
            Auth::guard('web')->logout();

            return redirect()->route('login')->with('danger', __('Your account is switched off.'));
        }

        // only companies with an active membership are ever loaded
        $companies = $user->companies()->with('plan')->orderBy('companies.name')->get();
        if ($companies->isEmpty()) {
            return redirect()->route('onboarding.create');
        }

        $wanted = $request->session()->get('company_id') ?? $user->last_company_id;
        $company = $companies->firstWhere('id', $wanted) ?? $companies->first();

        app()->instance('current.company', $company);
        $request->session()->put('company_id', $company->id);
        View::share('myCompanies', $companies);

        return $next($request);
    }
}
