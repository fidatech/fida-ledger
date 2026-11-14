<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Wrong passwords allowed per email and IP address before a one-minute pause. */
    private const MAX_ATTEMPTS = 5;

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials['email'] = Str::lower($credentials['email']);

        $key = Str::transliterate($credentials['email'].'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $user = User::where('email', $credentials['email'])->where('is_active', true)->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => __('The email or the password is not right.')]);
        }
        RateLimiter::clear($key);

        // two-factor: the password alone signs nobody in. The user is remembered for ten minutes while he types his code.
        if ($user->hasTwoFactor()) {
            $request->session()->put('two_factor', ['id' => $user->id, 'remember' => $request->boolean('remember'), 'until' => now()->addMinutes(10)->timestamp]);

            return redirect()->route('two-factor.challenge');
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // straight to the dashboard of the company worked in last: there is no "choose a company" page.
        // A company whose subscription is not active is sent on to its payment page by the middleware.
        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        // only this sign-in ends: an administrator signed in at /admin in the same browser stays
        $request->session()->forget('company_id');
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
