<?php
/**
 * Call Bridge configuration.
 *
 * Secrets are read from wp-config.php constants. No secret falls back to
 * wp_options. Non-secret timing defaults are constants with safe defaults.
 */

declare(strict_types=1);

namespace SiteEngine\CallBridge\Support;

defined('ABSPATH') || exit;

final class Config
{
    public static function twilioAccountSid(): ?string
    {
        return defined('SECB_TWILIO_ACCOUNT_SID') && SECB_TWILIO_ACCOUNT_SID !== ''
            ? (string) SECB_TWILIO_ACCOUNT_SID
            : null;
    }

    public static function twilioAuthToken(): ?string
    {
        return defined('SECB_TWILIO_AUTH_TOKEN') && SECB_TWILIO_AUTH_TOKEN !== ''
            ? (string) SECB_TWILIO_AUTH_TOKEN
            : null;
    }

    public static function twilioApiKeySid(): ?string
    {
        return defined('SECB_TWILIO_API_KEY_SID') && SECB_TWILIO_API_KEY_SID !== ''
            ? (string) SECB_TWILIO_API_KEY_SID
            : null;
    }

    public static function twilioApiKeySecret(): ?string
    {
        return defined('SECB_TWILIO_API_KEY_SECRET') && SECB_TWILIO_API_KEY_SECRET !== ''
            ? (string) SECB_TWILIO_API_KEY_SECRET
            : null;
    }

    /** @return array{username:string,password:string}|null */
    public static function recordingCredentials(): ?array
    {
        $keySid = self::twilioApiKeySid();
        $keySecret = self::twilioApiKeySecret();
        if ($keySid !== null && $keySecret !== null) {
            return ['username' => $keySid, 'password' => $keySecret];
        }

        $accountSid = self::twilioAccountSid();
        $authToken = self::twilioAuthToken();
        if ($accountSid !== null && $authToken !== null) {
            return ['username' => $accountSid, 'password' => $authToken];
        }
        return null;
    }

    public static function webhookBaseUrl(): string
    {
        if (defined('SECB_WEBHOOK_BASE_URL') && SECB_WEBHOOK_BASE_URL !== '') {
            return rtrim((string) SECB_WEBHOOK_BASE_URL, '/');
        }
        return rtrim(home_url(), '/');
    }

    public static function debugLog(): bool
    {
        return defined('SECB_DEBUG_LOG') && SECB_DEBUG_LOG === true;
    }

    public static function passthroughMode(): bool
    {
        return defined('SECB_PASSTHROUGH_MODE') && SECB_PASSTHROUGH_MODE === true;
    }

    public static function leaseMinutes(): int
    {
        $value = defined('SECB_LEASE_MINUTES') ? (int) SECB_LEASE_MINUTES : 30;
        return max(5, min(240, $value));
    }

    public static function sessionTtlDays(): int
    {
        $value = defined('SECB_SESSION_TTL_DAYS') ? (int) SECB_SESSION_TTL_DAYS : 90;
        return max(1, min(365, $value));
    }
}
