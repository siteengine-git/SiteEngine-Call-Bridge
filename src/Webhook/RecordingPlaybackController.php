<?php
/** Administrator-only, nonce-authenticated, bounded recording proxy. */
declare(strict_types=1);
namespace SiteEngine\CallBridge\Webhook;
use SiteEngine\CallBridge\DB\CallLogRepository;
use SiteEngine\CallBridge\Support\Config;
use WP_REST_Request;
defined('ABSPATH') || exit;
final class RecordingPlaybackController
{
    public const MAX_BYTES = 20971520;

    /** Never send credentials to a callback-supplied URL or follow redirects. */
    public static function mediaUrl(array $row): ?string
    {
        $account = Config::twilioAccountSid();
        $sid = (string) ($row['recording_sid'] ?? '');
        if (!is_string($account) || !preg_match('/\AAC[0-9a-f]{32}\z/i', $account)
            || !preg_match('/\ARE[0-9a-f]{32}\z/i', $sid)) {
            return null;
        }
        // Explicitly request both original tracks rather than relying on defaults.
        return 'https://api.twilio.com/2010-04-01/Accounts/' . $account
            . '/Recordings/' . $sid . '.wav?RequestedChannels=2';
    }

    public function handle(WP_REST_Request $request): void
    {
        // REST cookie authentication also validates the nonce before this handler.
        if (!is_user_logged_in() || !current_user_can('manage_options')
            || !wp_verify_nonce((string) $request->get_param('_wpnonce'), 'wp_rest')) {
            $this->deny(403, 'Forbidden');
        }
        $id = (int) $request->get_param('id');
        if ($id <= 0) { $this->deny(400, 'Invalid id'); }
        global $wpdb;
        $row = (new CallLogRepository())->findById($id);
        if ($wpdb->last_error !== '') { $this->deny(503, 'Recording lookup unavailable'); }
        if ($row === null || empty($row['recording_url'])) { $this->deny(404, 'Not found'); }
        $url = self::mediaUrl($row);
        $credentials = Config::recordingCredentials();
        if ($url === null || $credentials === null) { $this->deny(503, 'Recording playback unavailable'); }
        $response = wp_remote_get($url, [
            'timeout' => 30, 'redirection' => 0, 'sslverify' => true,
            'limit_response_size' => self::MAX_BYTES + 1,
            'headers' => ['Authorization' => 'Basic ' . base64_encode($credentials['username'] . ':' . $credentials['password'])],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->deny(502, 'Recording provider unavailable');
        }
        $body = wp_remote_retrieve_body($response);
        if (strlen($body) < 12 || strlen($body) > self::MAX_BYTES
            || substr($body, 0, 4) !== 'RIFF' || substr($body, 8, 4) !== 'WAVE') {
            $this->deny(502, 'Recording response unavailable');
        }
        status_header(200);
        nocache_headers();
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Content-Type: audio/wav');
        header('Content-Length: ' . strlen($body));
        header('Content-Disposition: inline; filename="call-' . $id . '.wav"');
        echo $body;
        exit;
    }

    private function deny(int $status, string $message): never
    {
        status_header($status);
        nocache_headers();
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    }
}
