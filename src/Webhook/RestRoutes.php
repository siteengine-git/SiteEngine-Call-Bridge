<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Webhook;
use SiteEngine\CallBridge\DB\CallLogRepository;
use SiteEngine\CallBridge\DB\NumberLeaseRepository;
use SiteEngine\CallBridge\Support\Config;
use SiteEngine\CallBridge\Support\WebhookEvents;
use WP_REST_Request; use WP_REST_Response;
defined('ABSPATH') || exit;
final class RestRoutes
{
    public function register(): void
    {
        $ns=SECB_REST_NAMESPACE;
        register_rest_route($ns,'/twilio/voice',['methods'=>'POST','callback'=>[new TwilioVoiceController(),'handle'],'permission_callback'=>'__return_true']);
        register_rest_route($ns,'/twilio/status',['methods'=>'POST','callback'=>[new TwilioStatusController(),'handle'],'permission_callback'=>'__return_true']);
        register_rest_route($ns,'/twilio/recording',['methods'=>'POST','callback'=>[new TwilioRecordingController(),'handle'],'permission_callback'=>'__return_true']);
        register_rest_route($ns,'/twilio/whisper',['methods'=>'GET','callback'=>[new TwilioWhisperController(),'handle'],'permission_callback'=>'__return_true']);
        register_rest_route($ns,'/track/session',['methods'=>'POST','callback'=>[new TrackSessionController(),'handle'],'permission_callback'=>'__return_true']);
        register_rest_route($ns,'/track/number',['methods'=>'GET','callback'=>[new TrackNumberController(),'handle'],'permission_callback'=>'__return_true','args'=>['sid'=>['required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_text_field']]]);
        register_rest_route($ns,'/recording/(?P<id>\d+)',['methods'=>'GET','callback'=>[new RecordingPlaybackController(),'handle'],'permission_callback'=>'__return_true']);
        register_rest_route($ns,'/health',['methods'=>'GET','callback'=>[$this,'health'],'permission_callback'=>'__return_true']);
    }
    public function health(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $tables=[];
        foreach(['tracking_numbers'=>'se_cb_tracking_numbers','attribution_sessions'=>'se_cb_attribution_sessions','number_leases'=>'se_cb_number_leases','calls'=>'se_cb_calls'] as $k=>$suffix){$name=$wpdb->prefix.$suffix;$tables[$k]=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$name))===$name;}
        $calls=new CallLogRepository();
        $quality=[];
        if($tables['calls']){$rows=$wpdb->get_results("SELECT qualification_status,COUNT(*) c FROM {$calls->table()} GROUP BY qualification_status",ARRAY_A)?:[];foreach($rows as $r)$quality[(string)$r['qualification_status']]=(int)$r['c'];}
        $activeLeases=0; if($tables['number_leases']){$activeLeases=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}se_cb_number_leases WHERE status='active' AND expires_at>UTC_TIMESTAMP()");}
        return new WP_REST_Response([
            'ok'=>true,'plugin'=>'siteengine-call-bridge','version'=>SECB_VERSION,'db_version'=>get_option('se_cb_db_version'),'tables'=>$tables,
            'config'=>['twilio_account_sid'=>Config::twilioAccountSid()!==null,'twilio_auth_token'=>Config::twilioAuthToken()!==null,'recording_credentials'=>Config::recordingCredentials()!==null,'webhook_base_url'=>Config::webhookBaseUrl(),'passthrough_mode'=>Config::passthroughMode(),'lease_minutes'=>Config::leaseMinutes(),'session_ttl_days'=>Config::sessionTtlDays()],
            'extensions'=>['openssl'=>extension_loaded('openssl'),'json'=>extension_loaded('json')],
            'active_leases'=>$activeLeases,'call_quality'=>$quality,'webhook_events'=>WebhookEvents::all(),'time'=>gmdate('c')
        ],200);
    }
}
