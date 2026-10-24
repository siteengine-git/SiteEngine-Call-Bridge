<?php
/**
 * Twilio request signature validator.
 *
 * Twilio signs every webhook POST with HMAC-SHA1 over:
 *   base_url + concat(sorted_form_params_as_key=value)
 *
 * For GET requests it's just the full URL with query string. We only
 * register POST endpoints, but the validator handles both for completeness.
 *
 * The base URL must match what Twilio used when computing the signature.
 * Behind Cloudflare or a load balancer, $_SERVER['HTTPS'] and HTTP_HOST can
 * lie. The SECB_WEBHOOK_BASE_URL constant pins it. If unset, we fall
 * back to home_url() which is fine on most LiteSpeed+cPanel installs.
 *
 * Reference: https://www.twilio.com/docs/usage/webhooks/webhooks-security
 *
 * @package SiteEngine\CallBridge\Twilio
 */

declare(strict_types=1);

namespace SiteEngine\CallBridge\Twilio;

use SiteEngine\CallBridge\Support\Config;
use SiteEngine\CallBridge\Support\Logger;

defined('ABSPATH') || exit;

final class SignatureValidator
{
    /**
     * Validate a Twilio webhook request.
     *
     * @param string $signature   Value of X-Twilio-Signature header.
     * @param string $url         Full URL Twilio hit, exactly as Twilio saw it (no fragment).
     * @param array  $params      POST body params (form-encoded), or [] for GET.
     * @param string $authToken   Twilio account auth token.
     * @return bool               True if signature is valid.
     */
    public static function validate(
        string $signature,
        string $url,
        array $params,
        string $authToken
    ): bool {
        if ($signature === '' || $authToken === '' || $url === '') {
            return false;
        }

        // Per Twilio: sort POST params by key, concat as key+value (no separators),
        // append to URL, HMAC-SHA1 with auth token, base64-encode.
        ksort($params, SORT_STRING);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k . (is_array($v) ? implode('', $v) : (string) $v);
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        // Timing-safe comparison.
        return hash_equals($expected, $signature);
    }

    /**
     * Validate the current REST request. Resolves URL, signature, and auth
     * token from the environment. Returns true/false; logs the reason on
     * failure (without leaking the auth token or signature).
     */
    public static function validateCurrentRequest(): bool
    {
        $token = Config::twilioAuthToken();
        if ($token === null) {
            Logger::error('twilio_sig_missing_auth_token');
            return false;
        }

        $signature = $_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '';
        if ($signature === '') {
            Logger::warn('twilio_sig_missing_header');
            return false;
        }

        $url    = self::canonicalUrl();
        $params = self::postParams();

        $ok = self::validate($signature, $url, $params, $token);
        if (!$ok) {
            Logger::warn('twilio_sig_mismatch', [
                'url'         => $url,
                'param_count' => count($params),
            ]);
        }
        return $ok;
    }

    /**
     * Reconstruct the URL Twilio signed. Uses SECB_WEBHOOK_BASE_URL if
     * defined; otherwise home_url(). Adds REQUEST_URI verbatim so query
     * strings (if any) are preserved.
     */
    private static function canonicalUrl(): string
    {
        $base = Config::webhookBaseUrl();
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        return $base . $path;
    }

    /**
     * Pull POST form params from $_POST. Twilio sends application/x-www-form-urlencoded
     * for webhooks, so $_POST is populated by PHP. We exclude any params
     * that came from the query string (those are part of the URL already).
     */
    private static function postParams(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return [];
        }
        $params = $_POST;
        // Strip query-string params from the body set; Twilio signs body separately
        // from URL, and any query-string keys are already encoded in the URL.
        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str((string) $_SERVER['QUERY_STRING'], $query);
            foreach (array_keys($query) as $k) {
                unset($params[$k]);
            }
        }
        return $params;
    }
}
