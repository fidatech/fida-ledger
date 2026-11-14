<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware "can.do:money.create": the user's role in the current company must allow it. */
class CanDo
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless(can_do($permission), 403);

        return $next($request);
    }
}
