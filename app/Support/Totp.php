<?php

namespace App\Support;

/**
 * Time-based one-time passwords (RFC 6238, the kind Google Authenticator, Microsoft
 * Authenticator and Authy show): 6 digits, 30 seconds, HMAC-SHA1. No package is needed.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A new secret, as the base32 text the user types into (or scans with) the authenticator app. */
    public static function secret(): string
    {
        $bytes = random_bytes(20);
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $secret = '';
        foreach (str_split($bits, 5) as $chunk) {
            $secret .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $secret;
    }

    /** The code for a moment in time (now, unless a timestamp is given). */
    public static function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), 30);
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), self::decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $number = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** True when the code is right for now, or for the 30 seconds before or after (clocks differ a little). */
    public static function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $timestamp ??= time();
        foreach ([0, -30, 30] as $drift) {
            if (hash_equals(self::code($secret, $timestamp + $drift), $code)) {
                return true;
            }
        }

        return false;
    }

    /** What an authenticator app reads from a QR code or a link. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&digits=6&period=30';
    }

    private static function decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $secret))) as $char) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
