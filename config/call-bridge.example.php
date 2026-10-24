<?php
/**
 * Example external configuration for SiteEngine Call Bridge.
 * Store the real file outside the web root and define SECB_CONFIG_FILE in wp-config.php.
 */

define('SECB_TWILIO_ACCOUNT_SID', 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('SECB_TWILIO_AUTH_TOKEN', 'replace-me');

// Recommended for recording playback. If omitted, Call Bridge falls back to
// Account SID + Auth Token for playback authentication.
define('SECB_TWILIO_API_KEY_SID', 'SKxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('SECB_TWILIO_API_KEY_SECRET', 'replace-me');

// Set when Cloudflare/reverse-proxy reconstruction differs from the public URL
// Twilio signs. Example: https://example.com
define('SECB_WEBHOOK_BASE_URL', 'https://example.com');

define('SECB_LEASE_MINUTES', 30);
define('SECB_SESSION_TTL_DAYS', 90);
define('SECB_DEBUG_LOG', false);
define('SECB_PASSTHROUGH_MODE', false);
