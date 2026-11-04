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

        $post = wp_unslash($_POST);
        $callSid = (string) ($post['CallSid'] ?? '');
        $status = (string) ($post['DialCallStatus'] ?? ($post['CallStatus'] ?? ''));
        $duration = max(0, (int) ($post['DialCallDuration'] ?? ($post['CallDuration'] ?? 0)));
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

            // This one-row model retains its first final outcome. A late progress,
            // duplicate or conflicting final event cannot undo accepted qualification.
            $terminal = ['completed','busy','no-answer','failed','canceled'];
            $known = array_merge($terminal, ['queued','initiated','ringing','in-progress']);
            if (!in_array($status, $known, true) || in_array((string) $row['call_status'], $terminal, true)) {
                $this->emitEmpty();
                return;
            }
            $expected = array_intersect_key($row, array_flip(['call_status','call_duration_sec','qualification_status']));
            if (!in_array($status, $terminal, true)) {
                $rank = ['queued'=>0,'initiated'=>1,'ringing'=>2,'in-progress'=>3];
                if (($rank[$status] ?? -1) <= ($rank[(string) $row['call_status']] ?? -1)) {
                    $this->emitEmpty();
                    return;
                }
                $calls->updateIfCurrent((int) $row['id'], ['call_status'=>$status], $expected);
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
            $calls->updateIfCurrent((int) $row['id'], [
                'call_status' => $status !== '' ? $status : (string) $row['call_status'],
                'call_duration_sec' => $duration,
                'qualification_status' => $quality,
            ], $expected);
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
