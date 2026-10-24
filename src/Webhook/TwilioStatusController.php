<?php
/**
 * Twilio Dial status callback. Finalizes duration/status and call quality.
 * Repeat-caller state remains metadata; it never suppresses qualification.
 */

declare(strict_types=1);
namespace SiteEngine\CallBridge\Webhook;

use SiteEngine\CallBridge\DB\CallLogRepository;
use SiteEngine\CallBridge\DB\TrackingNumberRepository;
use SiteEngine\CallBridge\Support\Logger;
use SiteEngine\CallBridge\Support\WebhookEvents;
use SiteEngine\CallBridge\Twilio\SignatureValidator;
use WP_REST_Request;

defined('ABSPATH') || exit;

final class TwilioStatusController
{
    public function handle(WP_REST_Request $request): void
    {
        WebhookEvents::record(WebhookEvents::STATUS_HIT);
        if (!SignatureValidator::validateCurrentRequest()) {
            WebhookEvents::record(WebhookEvents::STATUS_SIGNATURE_FAIL);
            status_header(403);
            echo '';
            exit;
        }

        $callSid = (string) ($_POST['CallSid'] ?? '');
        $status = (string) ($_POST['DialCallStatus'] ?? ($_POST['CallStatus'] ?? ''));
        $duration = (int) ($_POST['DialCallDuration'] ?? ($_POST['CallDuration'] ?? 0));
        if ($callSid === '') {
            Logger::warn('status_missing_call_sid');
            $this->emitEmpty();
            return;
        }

        try {
            $calls = new CallLogRepository();
            $row = $calls->findByCallSid($callSid);
            if ($row === null) {
                Logger::warn('status_call_not_found', ['call_sid' => $callSid]);
                WebhookEvents::record(WebhookEvents::STATUS_UNMATCHED, $callSid);
                $this->emitEmpty();
                return;
            }

            $min = 60;
            $number = (new TrackingNumberRepository())->findActiveByPhone((string) $row['tracking_number']);
            if ($number !== null) {
                $min = (int) ($number['min_duration_sec'] ?? 60);
            }

            $quality = 'unqualified_status';
            if ($status === 'completed') {
                $quality = $duration >= $min ? 'qualified' : 'unqualified_short';
            }
            $calls->update((int) $row['id'], [
                'call_status' => $status !== '' ? $status : (string) $row['call_status'],
                'call_duration_sec' => $duration,
                'qualification_status' => $quality,
            ]);
        } catch (\Throwable $e) {
            Logger::error('status_update_failed', ['error' => $e->getMessage(), 'call_sid' => $callSid]);
        }
        $this->emitEmpty();
    }

    private function emitEmpty(): void
    {
        status_header(200);
        header('Content-Type: text/xml; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo '<?xml version="1.0" encoding="UTF-8"?><Response/>';
        exit;
    }
}
