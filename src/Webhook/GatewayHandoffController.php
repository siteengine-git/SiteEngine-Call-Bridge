<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Webhook;
use SiteEngine\CallBridge\Gateway\Context;
use SiteEngine\CallBridge\Gateway\Receiver;
use SiteEngine\CallBridge\Gateway\Rejection;
use SiteEngine\CallBridge\Support\Config;
use WP_REST_Request;
defined('ABSPATH') || exit;
final class GatewayHandoffController
{
    public function handle(WP_REST_Request $request): void
    {
        try {
            $v = Receiver::verify($request->get_body(), (string) $request->get_header('x-secb-handoff'), Config::gatewayClients(), Config::gatewayNonceDirectory(), ABSPATH);
        } catch (\Throwable $e) {
            status_header($e instanceof Rejection ? $e->status : 503);
            header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: no-store');
            echo 'Gateway handoff rejected'; exit;
        }
        $old = [$_SERVER, $_GET, $_POST]; $restored = false;
        $restore = static function () use ($old, &$restored): void {
            if ($restored) { return; } $restored = true;
            [$_SERVER, $_GET, $_POST] = $old; Context::clear();
        };
        // Existing emitters exit. Restore before other WordPress shutdown actions,
        // with a PHP fallback for exceptions/non-WordPress isolated execution.
        add_action('shutdown', $restore, -PHP_INT_MAX); register_shutdown_function($restore);
        try {
            Context::enter($v);
            $_SERVER['REQUEST_METHOD'] = $v['method']; $_GET = wp_slash($v['query']); $_POST = wp_slash($v['form']);
            $forward = new WP_REST_Request($v['method']);
            $forward->set_query_params($v['query']); $forward->set_body_params($v['form']);
            $class = match ($v['event']) {
                'voice' => TwilioVoiceController::class, 'whisper' => TwilioWhisperController::class,
                'status' => TwilioStatusController::class, 'recording' => TwilioRecordingController::class,
            };
            (new $class())->handle($forward);
        } finally { $restore(); }
    }
}
