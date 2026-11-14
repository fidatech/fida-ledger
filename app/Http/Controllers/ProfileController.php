<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** My profile and security: name, password and two-factor sign-in. It belongs to the person, not to a company. */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        // a secret that was made but not confirmed yet is shown again so the user can finish the setup
        $pending = ! $user->hasTwoFactor() && $user->two_factor_secret ? (string) $user->two_factor_secret : null;

        return view('profile.edit', [
            'user' => $user,
            'pendingSecret' => $pending,
            'otpUri' => $pending ? Totp::uri($pending, $user->email, config('app.name')) : null,
            'recoveryCodes' => session('recovery_codes'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->user()->update($request->validate(['name' => ['required', 'string', 'max:150']]));

        return back()->with('success', __('Profile saved.'));
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $request->user()->update(['password' => $data['password']]);
        AuditLog::record('changed password', $request->user()->email);

        return back()->with('success', __('Password changed.'));
    }

    /** Step 1 of switching two-factor on: make a secret to put into the authenticator app. */
    public function twoFactorStart(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasTwoFactor()) {
            $user->forceFill(['two_factor_secret' => Totp::secret(), 'two_factor_confirmed_at' => null])->save();
        }

        return back();
    }

    /** Step 2: a first right code proves the app works. Only then is two-factor on, and the recovery codes are shown once. */
    public function twoFactorConfirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);
        if ($user->hasTwoFactor() || ! $user->two_factor_secret) {
            return back();
        }
        if (! Totp::verify((string) $user->two_factor_secret, $data['code'])) {
            return back()->withErrors(['code' => __('This code is not right. Check the time on your phone and try the next code.')]);
        }

        $codes = array_map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)), range(1, 8));
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => array_map(fn (string $code) => Hash::make($code), $codes)])->save();
        AuditLog::record('switched two-factor on', $user->email);

        return back()->with('recovery_codes', $codes)->with('success', __('Two-factor sign-in is on.'));
    }

    public function twoFactorDisable(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:web']]);
        $user = $request->user();
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        AuditLog::record('switched two-factor off', $user->email);

        return back()->with('success', __('Two-factor sign-in is off.'));
    }
}
