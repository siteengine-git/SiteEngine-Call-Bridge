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
            $row = $calls->findByCallSid($callSid, true);
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
                $calls->updateIfCurrent((int) $row['id'], ['call_status'=>$status], $expected, true);
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
            $data = [
                'call_status' => $status,
                'call_duration_sec' => $duration,
                'qualification_status' => $quality,
                // The threshold actually used by this terminal decision, not
                // a later reconstruction or a value observed only at Voice.
                'qualification_threshold_sec' => $min,
            ];
            $id = (int) $row['id'];
            // At most three CAS writes. Re-read after every conflict, including
            // the last, so a persisted terminal winner is an acknowledged duplicate.
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $expected = array_intersect_key($row, array_flip(['call_status','call_duration_sec','qualification_status']));
                if ($calls->updateIfCurrent($id, $data, $expected, true)) {
                    $this->emitEmpty();
                    return;
                }
                $row = $calls->findByCallSid($callSid, true);
                if ($row === null || (int) $row['id'] !== $id) {
                    throw new \RuntimeException('callback_row_changed');
                }
                if (in_array((string) $row['call_status'], $terminal, true)) {
                    $this->emitEmpty();
                    return;
                }
            }
            throw new \RuntimeException('callback_contention_unresolved');
        } catch (\Throwable $e) {
            Logger::error('status_update_failed', ['error' => $e->getMessage(), 'call_sid' => $callSid]);
            $this->emitEmpty(503);
        }
        $this->emitEmpty();
    }

    private function emitEmpty(int $httpStatus = 200): void
    {
        status_header($httpStatus);
        header('Content-Type: text/xml; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo '<?xml version="1.0" encoding="UTF-8"?><Response/>';
        exit;
    }
}
