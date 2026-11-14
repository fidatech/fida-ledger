<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware "module:inventory": the screen exists only while the company has the module switched on. */
class RequireModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(module_on($module), 404);

        return $next($request);
    }
}
