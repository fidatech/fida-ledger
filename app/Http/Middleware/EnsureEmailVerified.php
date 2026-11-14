<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Where email verification is switched on, a user who has not confirmed his address sees the "check your email" page. */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('web')?->needsVerification()) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
