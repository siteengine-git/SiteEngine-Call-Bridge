<?php
/**
 * Twilio recording status callback controller.
 *
 * Fires once a recording is finalized. We store the RecordingUrl and
 * duration on the matching call_log row.
 *
 * Recording URL stored as-is (Twilio-hosted). For long-term archival or
 * compliance copy, we'd add a separate job to download and re-host —
 * out of scope for now.
 *
 * @package SiteEngine\CallBridge\Webhook
 */

declare(strict_types=1);

namespace SiteEngine\CallBridge\Webhook;

use SiteEngine\CallBridge\DB\CallLogRepository;
use SiteEngine\CallBridge\Support\Logger;
use SiteEngine\CallBridge\Support\WebhookEvents;
use SiteEngine\CallBridge\Twilio\SignatureValidator;
use WP_REST_Request;

defined('ABSPATH') || exit;

final class TwilioRecordingController
{
    public function handle(WP_REST_Request $request): void
    {
        WebhookEvents::record(WebhookEvents::RECORDING_HIT);

        if (!SignatureValidator::validateCurrentRequest()) {
            WebhookEvents::record(WebhookEvents::RECORDING_SIGNATURE_FAIL);
            status_header(403);
            echo '';
            exit;
        }

        // Twilio sends recording callbacks with the parent CallSid in
        // CallSid (the inbound leg), and recording details in RecordingSid,
        // RecordingUrl, RecordingDuration.
        $callSid           = (string) ($_POST['CallSid'] ?? '');
        $recordingSid      = (string) ($_POST['RecordingSid'] ?? '');
        $recordingUrl      = (string) ($_POST['RecordingUrl'] ?? '');
        $recordingDuration = (int)    ($_POST['RecordingDuration'] ?? 0);

        if ($callSid === '' || $recordingSid === '') {
            Logger::warn('recording_missing_params', compact('callSid', 'recordingSid'));
            $this->emitEmpty();
            return;
        }

        try {
            $callRepo = new CallLogRepository();
            $existing = $callRepo->findByCallSid($callSid);
            if ($existing === null) {
                Logger::warn('recording_call_not_found', ['call_sid' => $callSid]);
                WebhookEvents::record(WebhookEvents::RECORDING_UNMATCHED, $callSid);
                $this->emitEmpty();
                return;
            }
            $callRepo->update((int) $existing['id'], [
                'recording_sid'          => $recordingSid,
                'recording_url'          => $recordingUrl,
                'recording_duration_sec' => $recordingDuration,
            ]);
        } catch (\Throwable $e) {
            Logger::error('recording_update_failed', ['error' => $e->getMessage()]);
        }

        $this->emitEmpty();
    }

    private function emitEmpty(): void
    {
        status_header(200);
        header('Content-Type: text/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?><Response/>';
        exit;
    }
}
