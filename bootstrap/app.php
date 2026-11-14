<?php

use App\Exceptions\UserError;
use App\Http\Middleware\CanDo;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\RequireModule;
use App\Http\Middleware\SetCompany;
use App\Http\Middleware\StripNumberCommas;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // companies and the system administrators each have their own sign-in page
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.dashboard') : route('dashboard'));

        $middleware->web(append: [StripNumberCommas::class]);
        $middleware->alias([
            'company' => SetCompany::class,
            'subscribed' => EnsureSubscribed::class,
            'module' => RequireModule::class,
            'can.do' => CanDo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A problem the user can fix: go back to the form and show the message.
        $exceptions->render(function (UserError $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('danger', $e->getMessage());
        });
        $exceptions->dontReport(UserError::class);
    })->create();
