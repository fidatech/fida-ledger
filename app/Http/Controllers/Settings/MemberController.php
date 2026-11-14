<?php

namespace App\Http\Controllers\Settings;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Settings → Users & Roles: who may work in this company, and as what. */
class MemberController extends Controller
{
    public function index(): View
    {
        $company = company();

        return view('settings.members', [
            'company' => $company,
            'members' => $company->users()->orderBy('users.name')->get(),
            'roles' => $this->roles(),
            'seats' => $company->plan->max_users,
        ]);
    }

    /** Add a person. Someone who already has an account is added by email; a new person gets an account with the password given. */
    public function store(Request $request): RedirectResponse
    {
        $company = company();
        $request->merge(['email' => Str::lower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', Rule::in(array_keys($this->roles()))],
            'password' => ['nullable', Password::min(8)],
        ]);

        DB::transaction(function () use ($company, $data) {
            if ($company->users()->where('users.email', $data['email'])->exists()) {
                throw new UserError(__('This person is already in the company.'));
            }
            $seats = $company->plan->max_users;
            if ($seats !== null && $company->users()->wherePivot('is_active', true)->count() >= $seats) {
                throw new UserError(__('Your version allows :count users.', ['count' => $seats]));
            }

            $user = User::where('email', $data['email'])->first();
            if (! $user) {
                if (empty($data['password'])) {
                    throw new UserError(__('This email has no account yet. Give a password to create one.'));
                }
                $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            }
            $company->users()->attach($user->id, ['role' => $data['role'], 'is_active' => true]);
            AuditLog::record('added user', $user->email, ['role' => $data['role']]);
        });

        return back()->with('success', __('User added.'));
    }

    /** Change a role, or switch a person's access to this company off or on. */
    public function update(Request $request): RedirectResponse
    {
        $company = company();
        $member = $company->users()->where('users.id', $request->route('id'))->firstOrFail();
        if ($member->pivot->role === 'owner') {
            throw new UserError(__('The owner of the company cannot be changed here.'));
        }
        if ($member->id === $request->user()->id) {
            throw new UserError(__('You cannot change your own access.'));
        }

        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys($this->roles()))],
            'is_active' => ['required', 'boolean'],
        ]);
        $company->users()->updateExistingPivot($member->id, ['role' => $data['role'], 'is_active' => (bool) $data['is_active']]);
        AuditLog::record('changed user', $member->email, $data);

        return back()->with('success', __('User saved.'));
    }

    /** Every role except owner: there is one owner, set when the company was opened. */
    private function roles(): array
    {
        return array_map(fn (array $role) => $role['label'], array_diff_key(config('ledger.roles'), ['owner' => true]));
    }
}
