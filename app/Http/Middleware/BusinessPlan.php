<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware "business": screens that belong to the Business version only. */
class BusinessPlan
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(company()?->plan?->code === 'business', 404);

        return $next($request);
    }
}
