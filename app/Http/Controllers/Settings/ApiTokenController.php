<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Settings > API Access: keys for other programs. A key acts as the person who made it, in this company only. */
class ApiTokenController extends Controller
{
    public function index(): View
    {
        return view('settings.api', [
            'tokens' => ApiToken::with('user')->orderByRaw('revoked_at IS NOT NULL')->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'access' => ['required', 'in:read,write'],
        ]);

        [$token, $plain] = ApiToken::issue(company(), $request->user('web'), $data['name'], $data['access'] === 'write');
        AuditLog::record('created API key', $token->name);

        // the key is shown on the next page only; it cannot be read again
        return back()->with('api_key', $plain)->with('success', __('Key created. Copy it now: it is shown only once.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $token = ApiToken::findOrFail($request->route('id'));
        if ($token->revoked_at === null) {
            $token->forceFill(['revoked_at' => now()])->save();
            AuditLog::record('revoked API key', $token->name);
        }

        return back()->with('success', __('Key revoked. Programs using it can no longer sign in.'));
    }
}
