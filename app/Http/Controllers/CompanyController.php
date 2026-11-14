<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The company switcher in the header. */
class CompanyController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $user = $request->user();
        // only a company the user is an active member of can be chosen
        $company = $user->companies()->where('companies.id', (int) $request->input('company_id'))->firstOrFail();

        $request->session()->put('company_id', $company->id);
        $user->update(['last_company_id' => $company->id]);

        return redirect()->route('dashboard');
    }
}
