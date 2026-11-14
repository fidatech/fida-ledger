<?php

namespace App\Http\Controllers\Settings;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Settings → Currencies: the currencies the company works with. */
class CurrencyController extends Controller
{
    public function index(): View
    {
        return view('settings.currencies', ['currencies' => Currency::orderBy('code')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        $data = $request->validate([
            'code' => ['required', 'alpha', 'size:3', Rule::unique('currencies', 'code')->where('company_id', company_id())],
            'name' => ['required', 'string', 'max:60'],
            'symbol' => ['nullable', 'string', 'max:8'],
        ]);
        Currency::create($data);

        return back()->with('success', __('Currency added.'));
    }

    /** Switch a currency off or on. Off only hides it from new documents; what was posted in it stays. */
    public function toggle(Request $request): RedirectResponse
    {
        $currency = Currency::findOrFail($request->route('id'));
        if ($currency->code === base_currency()) {
            throw new UserError(__('The base currency cannot be switched off.'));
        }
        $currency->update(['is_active' => ! $currency->is_active]);

        return back()->with('success', __('Currency saved.'));
    }
}
