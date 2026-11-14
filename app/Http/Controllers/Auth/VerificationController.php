<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Email verification, used where config('billing.verify_email') is on. */
class VerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->needsVerification() ? view('auth.verify') : redirect()->route('dashboard');
    }

    /** The link in the email: signed, and for the signed-in user only. */
    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless((string) $request->route('id') === (string) $user->getKey() && hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash')), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->route('dashboard')->with('success', __('Your email address is confirmed.'));
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->needsVerification()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('success', __('We sent the link again. Please check your inbox.'));
    }
}
