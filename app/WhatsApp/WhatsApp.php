<?php

namespace App\WhatsApp;

use App\Exceptions\UserError;
use App\Models\Company;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;

/** Sends a company's WhatsApp messages and keeps the log of them. */
class WhatsApp
{
    /**
     * Hand one message to RelayWA and log the outcome. A message RelayWA refused is logged as
     * failed and returned (not thrown), so the user sees what happened and can try again.
     */
    public static function send(Company $company, string $to, string $body, string $kind = 'custom', ?string $reference = null): WhatsappMessage
    {
        $setting = WhatsappSetting::of($company);
        if (! $setting->isReady()) {
            throw new UserError(__('WhatsApp is not connected yet. Enter the session key under Settings > WhatsApp.'));
        }
        $number = self::number($to, $setting->country_code);
        if ($number === null) {
            throw new UserError(__('This is not a complete phone number. Write it with the country code, for example 93700123456.'));
        }
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 4096) {
            throw new UserError(__('Write a message of at most 4096 characters.'));
        }

        $log = ['company_id' => $company->id, 'user_id' => auth('web')->id(), 'to' => $number, 'kind' => $kind, 'reference' => $reference, 'body' => $body];
        try {
            $answer = (new RelayWa($setting->session_key))->sendText($number, $body);
        } catch (RelayWaError $e) {
            return WhatsappMessage::create($log + ['status' => WhatsappMessage::FAILED, 'error' => mb_substr($e->getMessage(), 0, 250)]);
        }

        return WhatsappMessage::create($log + ['status' => WhatsappMessage::ACCEPTED, 'provider_id' => self::messageId($answer)]);
    }

    /** Fill the {words} of a template. Unknown words are left as they are, so a typing mistake is visible. */
    public static function fill(string $template, array $values): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn (array $m) => array_key_exists($m[1], $values) ? (string) $values[$m[1]] : $m[0], $template);
    }

    /**
     * A phone number as RelayWA wants it: digits with the country code (7 to 15 of them).
     * "+93 700 123 456" and "0093700123456" become 93700123456; "0700123456" gets the
     * company's country code. Null when no complete number can be made.
     */
    public static function number(?string $phone, ?string $countryCode = null): ?string
    {
        $phone = trim((string) $phone);
        $international = str_starts_with($phone, '+') || str_starts_with($phone, '00');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($phone, '00')) {
            $digits = substr($digits, 2);
        }
        $countryCode = preg_replace('/\D+/', '', (string) $countryCode);
        if (! $international && str_starts_with($digits, '0')) {
            if ($countryCode === '') {
                return null;
            }
            $digits = $countryCode.ltrim($digits, '0');
        }

        return strlen($digits) >= 7 && strlen($digits) <= 15 ? $digits : null;
    }

    /** The id RelayWA gave the message, when its answer carries one under a usual name. */
    private static function messageId(array $answer): ?string
    {
        foreach ([$answer, $answer['data'] ?? null, $answer['message'] ?? null] as $part) {
            foreach (['messageId', 'message_id', 'id'] as $key) {
                if (is_array($part) && isset($part[$key]) && is_scalar($part[$key])) {
                    return mb_substr((string) $part[$key], 0, 190);
                }
            }
        }

        return null;
    }
}
