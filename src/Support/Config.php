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
        if(class_exists(\SiteEngine\CallBridge\Integration\BaseCredentials::class))return \SiteEngine\CallBridge\Integration\BaseCredentials::pair('webhook')['account_sid']??null;
        return defined('SECB_TWILIO_ACCOUNT_SID')&&SECB_TWILIO_ACCOUNT_SID!==''?(string)SECB_TWILIO_ACCOUNT_SID:null;
    }

    public static function twilioAuthToken(): ?string
    {
        if(class_exists(\SiteEngine\CallBridge\Integration\BaseCredentials::class))return \SiteEngine\CallBridge\Integration\BaseCredentials::pair('webhook')['auth_token']??null;
        return defined('SECB_TWILIO_AUTH_TOKEN')&&SECB_TWILIO_AUTH_TOKEN!==''?(string)SECB_TWILIO_AUTH_TOKEN:null;
    }

    public static function twilioApiKeySid(): ?string
    {
        if(class_exists(\SiteEngine\CallBridge\Integration\BaseCredentials::class))return \SiteEngine\CallBridge\Integration\BaseCredentials::pair('playback')['api_key_sid']??null;
        return defined('SECB_TWILIO_API_KEY_SID')&&SECB_TWILIO_API_KEY_SID!==''?(string)SECB_TWILIO_API_KEY_SID:null;
    }

    public static function twilioApiKeySecret(): ?string
    {
        if(class_exists(\SiteEngine\CallBridge\Integration\BaseCredentials::class))return \SiteEngine\CallBridge\Integration\BaseCredentials::pair('playback')['api_key_secret']??null;
        return defined('SECB_TWILIO_API_KEY_SECRET')&&SECB_TWILIO_API_KEY_SECRET!==''?(string)SECB_TWILIO_API_KEY_SECRET:null;
    }

    /** @return array{username:string,password:string}|null */
    public static function recordingCredentials(): ?array
    {
        $keySid = self::twilioApiKeySid();
        $keySecret = self::twilioApiKeySecret();
        if ($keySid !== null && $keySecret !== null) {
            if(class_exists(\SiteEngine\CallBridge\Integration\BaseCredentials::class)
                && !\SiteEngine\CallBridge\Integration\BaseCredentials::validate(['api_key_sid'=>$keySid,'api_key_secret'=>$keySecret],'playback'))return null;
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

    /** Optional receiver configuration lives in the existing external config file. */
    public static function gatewayClients(): array
    { return defined('SECB_GATEWAY_CLIENTS') && is_array(SECB_GATEWAY_CLIENTS) ? SECB_GATEWAY_CLIENTS : []; }
    public static function gatewayNonceDirectory(): string
    { return defined('SECB_GATEWAY_NONCE_DIR') && is_string(SECB_GATEWAY_NONCE_DIR) ? SECB_GATEWAY_NONCE_DIR : ''; }
    public static function callbackUrl(string $event, array $query = []): string
    {
        if (class_exists(\SiteEngine\CallBridge\Gateway\Context::class, false) && \SiteEngine\CallBridge\Gateway\Context::active()) {
            return \SiteEngine\CallBridge\Gateway\Context::callbackUrl($event, $query);
        }
        $url = self::webhookBaseUrl() . '/wp-json/' . SECB_REST_NAMESPACE . '/twilio/' . $event;
        return $query === [] ? $url : $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
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
