<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows the system in the language the person chose: the one saved on his user, else the one
 * chosen in this browser (sign-in pages, the admin panel), else the language of the installation.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $wanted = $request->user('web')?->locale ?? ($request->hasSession() ? $request->session()->get('locale') : null);
        if (is_string($wanted) && array_key_exists($wanted, config('app.locales'))) {
            app()->setLocale($wanted);
        }

        return $next($request);
    }
}
