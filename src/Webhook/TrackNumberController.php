<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Webhook;
use SiteEngine\CallBridge\DB\NumberLeaseRepository;
use SiteEngine\CallBridge\Support\TrackingNumberResolver;
use WP_REST_Request; use WP_REST_Response;
defined('ABSPATH') || exit;
final class TrackNumberController
{
    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $sid=(string)$request->get_param('sid');
        if($sid===''||preg_match('/^[a-zA-Z0-9\-]{8,64}$/',$sid)!==1)return new WP_REST_Response(['error'=>'invalid_sid'],400);
        $lease=(new NumberLeaseRepository())->findActiveBySession($sid);
        if(!$lease)return new WP_REST_Response(['session_id'=>$sid,'tracking_number'=>null],200);
        $e164=(string)$lease['tracking_number'];
        return new WP_REST_Response(['session_id'=>$sid,'tracking_number'=>$e164,'display_phone'=>TrackingNumberResolver::formatForDisplay($e164),'expires_at'=>$lease['expires_at']],200);
    }
}
