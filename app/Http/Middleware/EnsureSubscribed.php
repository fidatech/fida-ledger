<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The books open only for a company with an approved, running subscription. Everyone else lands on the payment page. */
class EnsureSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! company()?->isUsable()) {
            if ($request->expectsJson()) {
                abort(402, __('The subscription of this company is not active.'));
            }

            return redirect()->route('billing.show');
        }

        return $next($request);
    }
}
