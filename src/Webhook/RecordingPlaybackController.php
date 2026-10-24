<?php
/**
 * Recording playback proxy.
 *
 * Twilio recording URLs require HTTP Basic Auth using the account SID and
 * auth token. Pasting them directly into a browser triggers a login prompt
 * and exposes credentials. Instead, this endpoint:
 *
 *   1. Requires the user to be logged into WordPress with manage_options
 *      capability (i.e. an admin).
 *   2. Looks up the call log row by ID.
 *   3. Fetches the recording from Twilio server-side using credentials
 *      from wp-config constants.
 *   4. Streams the audio back to the browser with the right Content-Type.
 *
 * Recordings stay private to the admin team. Twilio credentials never
 * touch the browser.
 *
 * @package SiteEngine\CallBridge\Webhook
 */

declare(strict_types=1);

namespace SiteEngine\CallBridge\Webhook;

use SiteEngine\CallBridge\DB\CallLogRepository;
use SiteEngine\CallBridge\Support\Config;
use SiteEngine\CallBridge\Support\Logger;
use WP_REST_Request;

defined('ABSPATH') || exit;

final class RecordingPlaybackController
{
    public function handle(WP_REST_Request $request): void
    {
        // Auth: must be a logged-in admin. We check capability rather than
        // a nonce because <audio>/<a target=_blank> requests don't carry
        // nonces, and we want play to work from the admin call-log page.
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            status_header(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Forbidden';
            exit;
        }

        $callId = (int) $request->get_param('id');
        if ($callId <= 0) {
            status_header(400);
            echo 'Invalid id';
            exit;
        }

        $callRepo = new CallLogRepository();
        // Fetch by primary key. Repository doesn't have a findById yet; do it inline.
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$callRepo->table()} WHERE id = %d LIMIT 1", $callId),
            ARRAY_A
        );

        if ($row === null || empty($row['recording_url'])) {
            status_header(404);
            echo 'Not found';
            exit;
        }

        $credentials = Config::recordingCredentials();
        if ($credentials === null) {
            status_header(500);
            echo 'Twilio recording credentials not configured';
            exit;
        }

        // Twilio recording URLs come without an extension. Append .mp3 for
        // efficient transcoded playback. .wav also available if you want
        // the raw recording — bigger but lossless.
        $url = rtrim((string) $row['recording_url'], '.') . '.mp3';

        // Fetch from Twilio with Basic Auth. wp_remote_get is fine here —
        // recordings are small (a few MB at most for typical calls).
        $resp = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($credentials['username'] . ':' . $credentials['password']),
            ],
        ]);

        if (is_wp_error($resp)) {
            Logger::error('recording_fetch_failed', ['error' => $resp->get_error_message(), 'call_id' => $callId]);
            status_header(502);
            echo 'Upstream fetch failed';
            exit;
        }

        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            Logger::warn('recording_upstream_status', ['status' => $code, 'call_id' => $callId]);
            status_header(502);
            echo 'Upstream returned ' . (int) $code;
            exit;
        }

        $body = wp_remote_retrieve_body($resp);

        // Stream back with audio content type. inline disposition so it
        // plays in-browser; attachment if you want download instead.
        status_header(200);
        header('Content-Type: audio/mpeg');
        header('Content-Length: ' . strlen($body));
        header('Content-Disposition: inline; filename="call-' . $callId . '.mp3"');
        header('Cache-Control: private, max-age=3600');
        echo $body;
        exit;
    }
}
