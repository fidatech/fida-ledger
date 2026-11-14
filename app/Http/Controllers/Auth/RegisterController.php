<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Step 1 of registration: the person. The company, its plan and its modules follow in the onboarding screen. */
class RegisterController extends Controller
{
    public function show(): View
    {
        abort_unless(config('billing.registration'), 404);

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('billing.registration'), 404);

        $request->merge(['email' => Str::lower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($data);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        // where verification is on, the link goes out now and the user confirms before setting up a company
        if ($user->needsVerification()) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice');
        }

        return redirect()->route('onboarding.create');
    }
}
