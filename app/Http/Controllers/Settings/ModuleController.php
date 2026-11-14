<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Settings → Modules: the company switches its Business modules on and off. Nothing is deleted by switching off. */
class ModuleController extends Controller
{
    public function edit(): View
    {
        $company = company();

        return view('settings.modules', [
            'company' => $company,
            'modules' => Modules::all(),
            'entitled' => Modules::entitled($company),
            'active' => Modules::activeKeys($company),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(Modules::all()))],
        ]);

        $company = company();
        $before = Modules::activeKeys($company);
        Modules::set($company, $data['modules'] ?? []);
        $after = Modules::activeKeys($company);

        AuditLog::record('changed modules', $company->name, ['on' => array_values(array_diff($after, $before)), 'off' => array_values(array_diff($before, $after))]);

        // a module that another chosen module needs was kept on: say so instead of silently ignoring the click
        $kept = array_diff($after, $data['modules'] ?? []);
        $message = $kept
            ? __('Saved. :modules stayed on because another active module needs it.', ['modules' => implode(', ', array_map(fn ($key) => __(Modules::all()[$key]['name']), $kept))])
            : __('Modules saved.');

        return back()->with('success', $message);
    }
}
