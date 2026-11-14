<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\Modules;
use Illuminate\View\View;

/** Settings → Modules: what the company's version includes. The version decides the modules; a company does not pick them. */
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
}
