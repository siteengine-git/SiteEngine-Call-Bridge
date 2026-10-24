<?php
/**
 * Phone normalization and identity hashing.
 *
 * normalize() converts dirty input ("(555) 123-4567 ext 99") to E.164 ("+15551234567").
 * hashE164() produces the SHA256 hex digest Google expects for identity matching.
 *
 * Hashing uses NO SALT. Google's Enhanced Conversions / Data Manager match
 * works by hashing both sides with the same canonical input and comparing
 * digests byte-for-byte. A salt destroys the match silently.
 *
 * @package SiteEngine\CallBridge\Support
 */

declare(strict_types=1);

namespace SiteEngine\CallBridge\Support;

defined('ABSPATH') || exit;

final class Phone
{
    /**
     * Normalize raw input to E.164.
     *
     * Tuned for NANP (the SiteEngine portfolio). Numbers already in E.164 form
     * are validated and passed through. Extensions are stripped. International
     * numbers without a + are accepted by length as a fallback.
     *
     * Returns null if we cannot confidently produce an E.164 string.
     * Callers should treat null as "do not store" -- never guess.
     */
    public static function normalize(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Strip extension markers and anything after: "x", "ext", "ext.", "extension",
        // "," (Twilio dial pause), ";". Case-insensitive.
        $parts = preg_split('/\s*(?:x|ext\.?|extension|,|;)\s*/i', $raw, 2);
        $head  = is_array($parts) ? $parts[0] : $raw;

        // Already E.164 (7-15 digits after +, first digit non-zero).
        if (preg_match('/^\+([1-9]\d{6,14})$/', $head, $m) === 1) {
            return '+' . $m[1];
        }

        // Strip everything except digits.
        $digits = preg_replace('/\D+/', '', $head);
        if ($digits === null || $digits === '') {
            return null;
        }

        $len = strlen($digits);

        // NANP: 10 digits -> assume US/Canada, prefix +1.
        if ($len === 10) {
            return '+1' . $digits;
        }
        // NANP with country code prefix: 11 digits starting with 1.
        if ($len === 11 && $digits[0] === '1') {
            return '+' . $digits;
        }
        // International fallback: anything between 8 and 15 digits, first digit non-zero.
        if ($len >= 8 && $len <= 15 && $digits[0] !== '0') {
            return '+' . $digits;
        }

        return null;
    }

    /**
     * SHA256 hex digest of an E.164 string. Lowercase, no salt.
     * This is Google's required format for hashed phone identity columns.
     */
    public static function hashE164(string $e164): string
    {
        return hash('sha256', $e164);
    }

    /**
     * Convenience: normalize then hash in one shot.
     *
     * @return array{e164: string, hash: string}|null
     */
    public static function normalizeAndHash(string $raw): ?array
    {
        $e164 = self::normalize($raw);
        if ($e164 === null) {
            return null;
        }
        return [
            'e164' => $e164,
            'hash' => self::hashE164($e164),
        ];
    }
}
