<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The language switch in the header. The choice is kept in the browser and, when signed in, on the user. */
class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(array_keys(config('app.locales')))]]);

        $request->session()->put('locale', $data['locale']);
        $request->user('web')?->forceFill(['locale' => $data['locale']])->save();

        return back();
    }
}
