<?php

namespace App\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The RelayWA HTTP API, as far as this system uses it.
 *
 * Paths and the "Authorization: Bearer <session key>" header are taken from RelayWA's own PHP
 * SDK (relaywa/relaywa-php, src/Client.php). The SDK returns the decoded answer without
 * describing its fields, so nothing here depends on the shape of an answer: an HTTP 2xx means
 * "RelayWA accepted the request" and nothing more.
 */
class RelayWa
{
    public function __construct(private readonly string $sessionKey)
    {
    }

    /** Hand a text message to RelayWA. $to is digits only, with the country code. */
    public function sendText(string $to, string $text): array
    {
        return $this->request('POST', '/send-message', ['to' => $to, 'text' => $text]);
    }

    public function sessions(): array
    {
        return $this->request('GET', '/whatsapp-sessions');
    }

    public function qrCode(string $sessionId): array
    {
        return $this->request('GET', '/whatsapp-sessions/'.rawurlencode($sessionId).'/qrcode');
    }

    public function connect(string $sessionId): array
    {
        return $this->request('POST', '/whatsapp-sessions/'.rawurlencode($sessionId).'/connect');
    }

    public function disconnect(string $sessionId): array
    {
        return $this->request('POST', '/whatsapp-sessions/'.rawurlencode($sessionId).'/disconnect');
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        try {
            $response = Http::withToken($this->sessionKey)
                ->acceptJson()
                ->timeout((int) config('whatsapp.timeout'))
                ->connectTimeout(10)
                ->withoutRedirecting()
                ->send($method, config('whatsapp.base_url').$path, $payload === null ? [] : ['json' => $payload]);
        } catch (ConnectionException) {
            throw new RelayWaError(__('RelayWA could not be reached. Check the internet connection and try again.'));
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new RelayWaError(__('RelayWA did not accept the session key. Check the key under Settings > WhatsApp.'));
        }
        if (! $response->successful()) {
            throw new RelayWaError(__('RelayWA answered with error :status.', ['status' => $response->status()]));
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }
}
