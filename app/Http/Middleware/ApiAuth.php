<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs an API request in with "Authorization: Bearer <key>".
 *
 * A key belongs to one company and one user. On every request the user must still be switched
 * on and still be a member of that company, and the company's subscription must be running:
 * the same rules as in the browser. From here on company(), can_do() and the company scope of
 * every model work exactly as they do for a signed-in person, so the API cannot see more.
 */
class ApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // answers are always JSON, also for errors
        $request->headers->set('Accept', 'application/json');

        $key = (string) $request->bearerToken();
        $token = $key !== '' ? ApiToken::findByKey($key) : null;
        $user = $token?->user;
        if (! $token || ! $user || ! $user->is_active) {
            abort(401, 'The API key is missing, wrong or revoked.');
        }

        // only an active membership opens the company
        $company = $user->companies()->with('plan')->where('companies.id', $token->company_id)->first();
        if (! $company) {
            abort(403, 'The user of this key is no longer a member of the company.');
        }
        if (! $company->isUsable()) {
            abort(402, 'The subscription of this company is not active.');
        }
        if (! $request->isMethodSafe() && ! $token->can_write) {
            abort(403, 'This key can only read.');
        }

        app()->instance('current.company', $company);
        Auth::guard('web')->setUser($user);

        // "last used" is for the people managing keys; once a minute is exact enough
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
