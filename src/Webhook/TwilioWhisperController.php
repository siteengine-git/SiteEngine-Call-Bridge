<?php
/**
 * Whisper controller.
 *
 * Twilio fetches this URL when the agent answers a forwarded call. The
 * returned TwiML <Say> is played to the agent only, before the caller
 * is bridged. Used to deliver "new caller" vs "repeat caller" context.
 *
 * The message is passed as a query-string parameter from TwiMLBuilder so
 * this handler is stateless and doesn't need a DB round-trip during the
 * narrow window between answer and bridge.
 *
 * Signature validation: Twilio signs the whisper URL fetch. We validate
 * the same way as other endpoints.
 *
 * @package SiteEngine\CallBridge\Webhook
 */

declare(strict_types=1);

namespace SiteEngine\CallBridge\Webhook;

use SiteEngine\CallBridge\Twilio\SignatureValidator;
use SiteEngine\CallBridge\Twilio\TwiMLBuilder;
use WP_REST_Request;

defined('ABSPATH') || exit;

final class TwilioWhisperController
{
    public function handle(WP_REST_Request $request): void
    {
        if (!SignatureValidator::validateCurrentRequest()) {
            status_header(403);
            echo '';
            exit;
        }

        $msg = (string) ($request->get_param('msg') ?? '');
        $msg = trim($msg);
        if ($msg === '') {
            // No message: return empty Response so Twilio just bridges silently.
            $this->emit('<?xml version="1.0" encoding="UTF-8"?><Response/>');
            return;
        }

        // Cap message length defensively — Twilio's Say has its own caps, but
        // we don't want a 5000-character whisper from a malformed URL.
        if (strlen($msg) > 250) {
            $msg = substr($msg, 0, 250);
        }

        $tw = new TwiMLBuilder();
        $tw->say($msg);
        $this->emit($tw->render());
    }

    private function emit(string $xml): void
    {
        status_header(200);
        header('Content-Type: text/xml; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $xml;
        exit;
    }
}
