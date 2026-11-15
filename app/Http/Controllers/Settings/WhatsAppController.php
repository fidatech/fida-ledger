<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use App\WhatsApp\RelayWa;
use App\WhatsApp\RelayWaError;
use App\WhatsApp\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Settings > WhatsApp: the company's RelayWA connection, its message texts and the log of what was sent. */
class WhatsAppController extends Controller
{
    public function index(): View
    {
        return view('settings.whatsapp', [
            'setting' => WhatsappSetting::of(company()),
            'messages' => WhatsappMessage::with('user')->orderByDesc('id')->paginate(per_page(20)),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $keys = array_keys(config('whatsapp.templates'));
        $data = $request->validate([
            'session_key' => ['nullable', 'string', 'max:500'],
            'is_enabled' => ['nullable', 'boolean'],
            'country_code' => ['nullable', 'regex:/^\+?\d{1,4}$/'],
            'templates' => ['nullable', 'array:'.implode(',', $keys)],
            'templates.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $setting = WhatsappSetting::of(company());
        // an empty key box keeps the saved key: the key is never sent back to the page
        if (filled($data['session_key'] ?? null)) {
            $setting->session_key = trim($data['session_key']);
        }
        $setting->is_enabled = (bool) ($data['is_enabled'] ?? false);
        $setting->country_code = ltrim((string) ($data['country_code'] ?? ''), '+') ?: null;
        // keep only the texts that differ from the standard ones
        $templates = [];
        foreach ($keys as $key) {
            $text = trim((string) ($data['templates'][$key] ?? ''));
            if ($text !== '' && $text !== config('whatsapp.templates.'.$key.'.body')) {
                $templates[$key] = $text;
            }
        }
        $setting->templates = $templates ?: null;
        $setting->save();
        AuditLog::record('changed WhatsApp settings');

        return back()->with('success', __('WhatsApp settings saved.'));
    }

    /** Remove the saved key: nothing can be sent until a new one is entered. */
    public function forget(): RedirectResponse
    {
        $setting = WhatsappSetting::of(company());
        if ($setting->exists) {
            $setting->forceFill(['session_key' => null, 'is_enabled' => false])->save();
            AuditLog::record('removed WhatsApp session key');
        }

        return back()->with('success', __('The session key was removed.'));
    }

    /** Ask RelayWA for the sessions of this key: proves the key works and shows what RelayWA reports. */
    public function check(): RedirectResponse
    {
        try {
            $answer = $this->client()->sessions();
        } catch (RelayWaError $e) {
            return back()->with('danger', $e->getMessage());
        }

        return back()->with('success', __('RelayWA accepted the session key.'))->with('wa_sessions', $this->sessionRows($answer));
    }

    /** Connect or disconnect a session, or fetch its QR code. */
    public function session(Request $request): RedirectResponse
    {
        $data = $request->validate(['session_id' => ['required', 'string', 'max:190'], 'action' => ['required', 'in:connect,disconnect,qr']]);

        try {
            $client = $this->client();
            if ($data['action'] === 'qr') {
                $image = $this->qrImage($client->qrCode($data['session_id']));

                return $image
                    ? back()->with('wa_qr', $image)
                    : back()->with('warning', __('RelayWA answered, but not with a QR picture this page can show. Link the number in your RelayWA dashboard instead.'));
            }
            $data['action'] === 'connect' ? $client->connect($data['session_id']) : $client->disconnect($data['session_id']);
        } catch (RelayWaError $e) {
            return back()->with('danger', $e->getMessage());
        }

        return back()->with('success', __('RelayWA accepted the request. Press "Check connection" to see the new state.'));
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'string', 'max:40']]);
        $message = WhatsApp::send(company(), $data['to'], __('Test message from :company (Fida Ledger).', ['company' => company()->name]), 'test');

        return $this->sent($message);
    }

    /** The message about a document, ready to read and correct before it goes out. */
    public function compose(Request $request): View
    {
        return view('settings.whatsapp-compose', \App\WhatsApp\Drafts::for($request) + ['setting' => WhatsappSetting::of(company())]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:40'],
            'body' => ['required', 'string', 'max:4096'],
            'kind' => ['required', 'in:invoice,receipt,statement,custom'],
            'reference' => ['nullable', 'string', 'max:100'],
            'back' => ['nullable', 'string', 'max:500'],
        ]);
        $message = WhatsApp::send(company(), $data['to'], $data['body'], $data['kind'], $data['reference'] ?? null);

        // return to the document the message was about (a path inside this system only)
        $back = (string) ($data['back'] ?? '');
        $target = str_starts_with($back, '/') && ! str_starts_with($back, '//') ? redirect($back) : redirect()->route('whatsapp.index');

        return $this->flash($target, $message);
    }

    private function sent(WhatsappMessage $message): RedirectResponse
    {
        return $this->flash(back(), $message);
    }

    private function flash(RedirectResponse $to, WhatsappMessage $message): RedirectResponse
    {
        return $message->status === WhatsappMessage::ACCEPTED
            ? $to->with('success', __('RelayWA accepted the message for :number.', ['number' => $message->to]))
            : $to->with('danger', __('The message was not sent: :error', ['error' => $message->error]));
    }

    private function client(): RelayWa
    {
        $key = WhatsappSetting::of(company())->session_key;
        if (blank($key)) {
            throw new RelayWaError(__('Enter and save the session key first.'));
        }

        return new RelayWa($key);
    }

    /**
     * RelayWA's list of sessions as rows for the page. The field names are not documented in
     * its SDK, so the usual ones are tried and anything missing is simply left empty.
     */
    private function sessionRows(array $answer): array
    {
        $list = array_is_list($answer) ? $answer : ($answer['data'] ?? $answer['sessions'] ?? $answer['items'] ?? []);
        $rows = [];
        foreach (is_array($list) ? $list : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $pick = function (array $names) use ($item): string {
                foreach ($names as $name) {
                    if (isset($item[$name]) && is_scalar($item[$name])) {
                        return mb_substr((string) $item[$name], 0, 190);
                    }
                }

                return '';
            };
            $rows[] = ['id' => $pick(['id', 'sessionId', 'session_id']), 'name' => $pick(['name', 'label']), 'status' => $pick(['status', 'state']), 'phone' => $pick(['phone', 'phoneNumber', 'phoneHint', 'number'])];
        }

        return array_slice($rows, 0, 20);
    }

    /** A QR picture from RelayWA's answer, only when it is a real embedded PNG/JPEG/SVG image. */
    private function qrImage(array $answer): ?string
    {
        foreach ([$answer, $answer['data'] ?? null] as $part) {
            foreach (['qr', 'qrCode', 'qrcode', 'qr_code', 'image', 'dataUrl'] as $key) {
                $value = is_array($part) ? ($part[$key] ?? null) : null;
                if (is_string($value) && preg_match('#^data:image/(png|jpeg|gif|webp);base64,[A-Za-z0-9+/=]+$#', $value) && strlen($value) < 200000) {
                    return $value;
                }
            }
        }

        return null;
    }
}
