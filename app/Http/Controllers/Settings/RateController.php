<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Support\Dec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Exchange rates: what one unit of each currency is worth in the base currency, day by day. */
class RateController extends Controller
{
    public function index(): View
    {
        return view('settings.rates', [
            'rates' => ExchangeRate::orderByDesc('date')->orderBy('currency')->paginate(per_page(30)),
            'foreign' => array_diff(array_keys(currencies()), [base_currency()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', Rule::in(array_diff(array_keys(currencies()), [base_currency()]))],
            'date' => ['required', 'date'],
            'rate' => ['required', 'numeric', 'gt:0'],
        ]);

        // one rate per currency and day: saving again for the same day replaces it (posted entries keep their own rate)
        ExchangeRate::updateOrCreate(
            ['company_id' => company_id(), 'currency' => $data['currency'], 'date' => substr($data['date'], 0, 10)],
            ['rate' => Dec::round($data['rate'], (int) config('ledger.rate_scale'))],
        );

        return back()->with('success', __('Rate saved.'));
    }
}
