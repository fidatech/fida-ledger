<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Settings → Company profile, and the accounting period lock. */
class CompanyController extends Controller
{
    public function edit(): View
    {
        return view('settings.company', ['company' => company(), 'timezones' => DateTimeZone::listIdentifiers()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = company();
        // the base currency is not here: it cannot change once there are books in it
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'locked_until' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $before = $company->locked_until?->toDateString();
        $company->update($data);
        $after = $company->locked_until?->toDateString();
        if ($before !== $after) {
            AuditLog::record('changed period lock', $company->name, ['from' => $before, 'to' => $after]);
        }

        return back()->with('success', __('Settings saved.'));
    }
}
