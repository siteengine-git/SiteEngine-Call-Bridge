<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Webhook;

use SiteEngine\CallBridge\DB\AttributionSessionRepository;
use SiteEngine\CallBridge\DB\NumberLeaseRepository;
use SiteEngine\CallBridge\Support\Config;
use SiteEngine\CallBridge\Support\Logger;
use SiteEngine\CallBridge\Support\TrackingNumberResolver;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

final class TrackSessionController
{
    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $body=$request->get_json_params();
        if(!is_array($body)) return new WP_REST_Response(['error'=>'invalid_body'],400);
        $sid=trim((string)($body['session_id']??''));
        if($sid===''||strlen($sid)>64||preg_match('/^[a-zA-Z0-9\-]{8,64}$/',$sid)!==1) return new WP_REST_Response(['error'=>'invalid_session_id'],400);

        $rate='secb_rl_'.md5($sid);
        if(get_transient($rate)!==false) return new WP_REST_Response(['error'=>'rate_limited'],429);
        set_transient($rate,1,3);

        $source=isset($body['source'])?sanitize_key((string)$body['source']):$this->detectSource($body);
        if($source==='') return new WP_REST_Response(['session_id'=>$sid,'tracking_number'=>null],200);

        $expires=gmdate('Y-m-d H:i:s',time()+Config::sessionTtlDays()*86400);
        $data=[
            'session_id'=>$sid,
            'visitor_uid'=>$this->str($body,'visitor_uid',64),
            'gclid'=>$this->str($body,'gclid',255),'gbraid'=>$this->str($body,'gbraid',255),'wbraid'=>$this->str($body,'wbraid',255),
            'fbclid'=>$this->str($body,'fbclid',255),'msclkid'=>$this->str($body,'msclkid',255),
            'utm_source'=>$this->str($body,'utm_source',100),'utm_medium'=>$this->str($body,'utm_medium',100),'utm_campaign'=>$this->str($body,'utm_campaign',255),'utm_term'=>$this->str($body,'utm_term',255),'utm_content'=>$this->str($body,'utm_content',255),
            'landing_page'=>$this->urlish($body,'landing_page'),'referrer'=>$this->urlish($body,'referrer'),
            'user_agent'=>isset($_SERVER['HTTP_USER_AGENT'])?substr(sanitize_text_field((string)$_SERVER['HTTP_USER_AGENT']),0,500):null,
            'client_ip'=>$this->clientIp(),
            'expires_at'=>$expires,
        ];

        try{$lease=(new NumberLeaseRepository())->acquire($source,$sid,static fn(): bool => (new AttributionSessionRepository())->upsert($data)!==false);}catch(\Throwable $e){Logger::error('lease_acquire_failed',['error'=>$e->getMessage(),'source'=>$source]);$lease=null;}
        if(!$lease) return new WP_REST_Response(['session_id'=>$sid,'tracking_number'=>null,'pool_exhausted'=>true,'ttl_days'=>Config::sessionTtlDays()],200);
        $e164=(string)$lease['tracking_number'];
        return new WP_REST_Response(['session_id'=>$sid,'tracking_number'=>$e164,'display_phone'=>TrackingNumberResolver::formatForDisplay($e164),'ttl_days'=>Config::sessionTtlDays(),'lease_minutes'=>Config::leaseMinutes()],200);
    }
    private function str(array $b,string $k,int $max): ?string { if(!isset($b[$k])||$b[$k]==='')return null;return substr(sanitize_text_field((string)$b[$k]),0,$max); }
    private function urlish(array $b,string $k): ?string { if(!isset($b[$k])||$b[$k]==='')return null;return substr(esc_url_raw((string)$b[$k]),0,2048); }
    private function detectSource(array $b): string
    {
        if(!empty($b['gclid'])||!empty($b['gbraid'])||!empty($b['wbraid'])) return 'google_ads';
        if(!empty($b['msclkid'])) return 'bing_ads';
        if(!empty($b['fbclid'])) return 'facebook';
        $s=strtolower(trim((string)($b['utm_source']??'')));$m=strtolower(trim((string)($b['utm_medium']??'')));
        if($s==='google'&&in_array($m,['cpc','paid','ppc'],true))return 'google_ads';
        if(in_array($s,['facebook','fb','meta'],true))return 'facebook';
        if($s==='bing'&&in_array($m,['cpc','paid','ppc'],true))return 'bing_ads';
        if(in_array($s,['lsa','localservices'],true))return 'lsa';
        return $s!==''?sanitize_key($s):'';
    }
    private function clientIp(): ?string
    {
        foreach(['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $h){if(!empty($_SERVER[$h])){$ip=trim(explode(',',(string)$_SERVER[$h])[0]);if(filter_var($ip,FILTER_VALIDATE_IP))return $ip;}}
        return null;
    }
}
