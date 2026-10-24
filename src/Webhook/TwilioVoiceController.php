<?php
/**
 * Twilio inbound voice webhook.
 *
 * Routing is mission-critical. Attribution/logging failures never prevent a
 * resolved tracking number from forwarding to its configured destination.
 */

declare(strict_types=1);
namespace SiteEngine\CallBridge\Webhook;

use SiteEngine\CallBridge\DB\AttributionSessionRepository;
use SiteEngine\CallBridge\DB\CallLogRepository;
use SiteEngine\CallBridge\DB\NumberLeaseRepository;
use SiteEngine\CallBridge\DB\TrackingNumberRepository;
use SiteEngine\CallBridge\Support\Config;
use SiteEngine\CallBridge\Support\Logger;
use SiteEngine\CallBridge\Support\Phone;
use SiteEngine\CallBridge\Support\WebhookEvents;
use SiteEngine\CallBridge\Twilio\SignatureValidator;
use SiteEngine\CallBridge\Twilio\TwiMLBuilder;
use WP_REST_Request;

defined('ABSPATH') || exit;

final class TwilioVoiceController
{
    public function handle(WP_REST_Request $request): void
    {
        WebhookEvents::record(WebhookEvents::VOICE_HIT);
        $trackingRow = null;

        try {
            if (!SignatureValidator::validateCurrentRequest()) {
                WebhookEvents::record(WebhookEvents::VOICE_SIGNATURE_FAIL);
                status_header(403);
                header('Content-Type: text/plain; charset=utf-8');
                echo '';
                exit;
            }

            $callSid       = (string) ($_POST['CallSid'] ?? '');
            $from          = (string) ($_POST['From'] ?? '');
            $to            = (string) ($_POST['To'] ?? '');
            $callerCity    = (string) ($_POST['FromCity'] ?? '');
            $callerState   = (string) ($_POST['FromState'] ?? '');
            $callerCountry = (string) ($_POST['FromCountry'] ?? '');

            if ($callSid === '' || $to === '') {
                Logger::error('voice_missing_required_params', ['call_sid' => $callSid, 'to' => $to]);
                $this->respondHangup();
                return;
            }

            $trackingE164 = Phone::normalize($to) ?? $to;
            $callerE164   = Phone::normalize($from);
            $numbersRepo  = new TrackingNumberRepository();
            $trackingRow  = $numbersRepo->findActiveByPhone($trackingE164);

            if ($trackingRow === null) {
                Logger::warn('voice_unknown_tracking_number', ['to' => $trackingE164]);
                WebhookEvents::record(WebhookEvents::VOICE_UNKNOWN_NUMBER, $trackingE164);
                $this->respondHangup();
                return;
            }

            if (Config::passthroughMode()) {
                $this->respondDial($trackingRow, null);
                return;
            }

            // Deterministic attribution: a Twilio number maps only to its
            // current active lease. No "most recent visitor who saw number X"
            // heuristic is used.
            $session = null;
            try {
                $lease = (new NumberLeaseRepository())->findActiveByTrackingNumber($trackingE164);
                if ($lease !== null) {
                    $session = (new AttributionSessionRepository())->findBySessionId((string) $lease['session_id']);
                }
            } catch (\Throwable $e) {
                Logger::error('voice_session_lookup_failed', ['error' => $e->getMessage()]);
            }

            $isRepeat = false;
            $priorCount = 0;
            $callRepo = new CallLogRepository();
            if ($callerE164 !== null) {
                try {
                    $priorCount = $callRepo->countQualifyingPriorCalls(
                        $callerE164,
                        (int) ($trackingRow['repeat_window_days'] ?? 30),
                        (int) ($trackingRow['min_duration_sec'] ?? 60),
                        $callSid
                    );
                    $isRepeat = $priorCount > 0;
                } catch (\Throwable $e) {
                    Logger::error('voice_repeat_lookup_failed', ['error' => $e->getMessage()]);
                }
            }

            try {
                // Twilio may retry the voice webhook. CallSid uniqueness keeps
                // the call row idempotent; don't create a second row.
                if ($callRepo->findByCallSid($callSid) === null) {
                    $callRepo->insert([
                        'twilio_call_sid'        => $callSid,
                        'transaction_id'         => 'twilio:' . $callSid,
                        'tracking_number'        => $trackingE164,
                        'tracking_number_id'     => (int) $trackingRow['id'],
                        'caller_number'          => $callerE164 ?? $from,
                        'hashed_caller_number'   => $callerE164 !== null ? Phone::hashE164($callerE164) : null,
                        'caller_city'            => $callerCity ?: null,
                        'caller_state'           => $callerState ?: null,
                        'caller_country'         => $callerCountry ?: null,
                        'forwarded_to'           => (string) $trackingRow['forward_to'],
                        'source'                 => (string) ($trackingRow['source'] ?? ''),
                        'call_status'            => 'ringing',
                        'qualification_status'   => 'pending',
                        'matched_session_id'     => $session['session_id'] ?? null,
                        'consent_receipt_id'     => $session['consent_receipt_id'] ?? null,
                        'gclid'                  => $session['gclid'] ?? null,
                        'gbraid'                 => $session['gbraid'] ?? null,
                        'wbraid'                 => $session['wbraid'] ?? null,
                        'fbclid'                 => $session['fbclid'] ?? null,
                        'msclkid'                => $session['msclkid'] ?? null,
                        'utm_source'             => $session['utm_source'] ?? null,
                        'utm_medium'             => $session['utm_medium'] ?? null,
                        'utm_campaign'           => $session['utm_campaign'] ?? null,
                        'utm_term'               => $session['utm_term'] ?? null,
                        'utm_content'            => $session['utm_content'] ?? null,
                        'landing_page'           => $session['landing_page'] ?? null,
                        'is_repeat_caller'       => $isRepeat ? 1 : 0,
                        'prior_qualifying_calls' => $priorCount,
                    ]);
                }
            } catch (\Throwable $e) {
                Logger::error('voice_insert_failed', ['error' => $e->getMessage(), 'call_sid' => $callSid]);
            }

            $this->respondDial($trackingRow, $isRepeat);
        } catch (\Throwable $e) {
            Logger::error('voice_unhandled_throw', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            WebhookEvents::record(WebhookEvents::VOICE_UNHANDLED_THROW, substr($e->getMessage(), 0, 200));
            $this->respondFailsafe($trackingRow);
        }
    }

    private function respondHangup(): void
    {
        $tw = new TwiMLBuilder();
        $tw->hangup();
        $this->emit($tw->render());
    }

    private function respondFailsafe(?array $trackingRow): void
    {
        if (headers_sent()) {
            return;
        }
        status_header(200);
        header('Content-Type: text/xml; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-LiteSpeed-Cache-Control: no-cache');

        if ($trackingRow !== null && !empty($trackingRow['forward_to'])) {
            $forward = preg_replace('/[^\d+]/', '', (string) $trackingRow['forward_to']) ?? '';
            if ($forward !== '') {
                echo '<?xml version="1.0" encoding="UTF-8"?><Response><Dial answerOnBridge="true" timeout="22">'
                    . htmlspecialchars($forward, ENT_QUOTES | ENT_XML1, 'UTF-8')
                    . '</Dial></Response>';
                exit;
            }
        }
        echo '<?xml version="1.0" encoding="UTF-8"?><Response><Hangup/></Response>';
        exit;
    }

    private function respondDial(array $trackingRow, ?bool $isRepeat): void
    {
        $tw = new TwiMLBuilder();
        if (!empty($trackingRow['record_calls'])) {
            $notice = trim((string) ($trackingRow['recording_notice'] ?? ''));
            if ($notice === '') {
                $notice = 'This call may be recorded for quality assurance.';
            }
            $tw->say($notice);
        }

        $base = Config::webhookBaseUrl() . '/wp-json/' . SECB_REST_NAMESPACE;
        $dial = [
            'answerOnBridge' => 'true',
            'timeout'        => '22',
            'action'         => $base . '/twilio/status',
            'method'         => 'POST',
        ];
        if (!empty($trackingRow['record_calls'])) {
            $dial['record']                        = 'record-from-answer-dual';
            $dial['recordingStatusCallback']       = $base . '/twilio/recording';
            $dial['recordingStatusCallbackEvent']  = 'completed';
            $dial['recordingStatusCallbackMethod'] = 'POST';
        }
        $tw->openDial($dial);

        $whisperUrl = null;
        if ($isRepeat !== null) {
            $msg = $isRepeat ? ($trackingRow['whisper_repeat'] ?? '') : ($trackingRow['whisper_new'] ?? '');
            $msg = trim((string) $msg);
            if ($msg !== '') {
                $whisperUrl = $base . '/twilio/whisper?msg=' . rawurlencode($msg);
            }
        }
        $tw->number((string) $trackingRow['forward_to'], $whisperUrl);
        $tw->closeDial();
        $this->emit($tw->render());
    }

    private function emit(string $xml): void
    {
        status_header(200);
        header('Content-Type: text/xml; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-LiteSpeed-Cache-Control: no-cache');
        echo $xml;
        exit;
    }
}
