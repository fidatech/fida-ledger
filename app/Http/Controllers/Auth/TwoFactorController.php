<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** The second step of sign-in for a user with two-factor on: the code from his authenticator app, or a recovery code. */
class TwoFactorController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        return $this->pending($request) ? view('auth.two-factor') : redirect()->route('login');
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return redirect()->route('login')->with('danger', __('Please sign in again.'));
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = User::where('is_active', true)->findOrFail($pending['id']);

        // five wrong codes and the password has to be given again
        $key = 'two-factor|'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $request->session()->forget('two_factor');

            return redirect()->route('login')->with('danger', __('Too many wrong codes. Please sign in again.'));
        }

        $code = trim($data['code']);
        $ok = Totp::verify((string) $user->two_factor_secret, $code) || (strlen(preg_replace('/\W/', '', $code)) > 6 && $user->useRecoveryCode($code));
        if (! $ok) {
            RateLimiter::hit($key, 600);

            throw ValidationException::withMessages(['code' => __('This code is not right.')]);
        }

        RateLimiter::clear($key);
        $request->session()->forget('two_factor');
        Auth::guard('web')->login($user, (bool) $pending['remember']);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /** The user who gave a right password a moment ago, or null. */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get('two_factor');

        return is_array($pending) && ($pending['until'] ?? 0) >= now()->timestamp ? $pending : null;
    }
}
