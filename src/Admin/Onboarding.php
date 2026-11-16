<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Admin;
use SiteEngine\CallBridge\Integration\BaseCredentials;
use SiteEngine\CallBridge\Support\Config;
defined('ABSPATH') || exit;
final class Onboarding
{
    public static function mode(): string
    { return function_exists('get_option')&&get_option('se_cb_onboarding_mode','direct')==='central'?'central':'direct'; }
    public static function saveMode(): void
    {
        if(!current_user_can(Menu::CAP))wp_die('Insufficient permissions.','',['response'=>403]);
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST')wp_die('POST required.','',['response'=>405]);
        check_admin_referer('secb_onboarding_mode');
        $mode=$_POST['routing_mode']??null;
        if(!is_string($mode)||!in_array($mode,['direct','central'],true))wp_die('Invalid routing mode.','',['response'=>400]);
        update_option('se_cb_onboarding_mode',$mode,false);
        wp_safe_redirect(Menu::url('setup',['notice'=>'mode_saved']));exit;
    }
    public static function facts(): array
    {
        $required=BaseCredentials::status('webhook');$optional=BaseCredentials::status('playback');
        return ['credentials'=>$required,'playback'=>$optional,'base_available'=>BaseCredentials::available(),'mode'=>self::mode(),'routes'=>self::routes()];
    }
    public static function routes(): array
    {
        $routes=[];
        if(Config::gatewayClients()===[])return $routes;
        try { \SiteEngine\CallBridge\Gateway\Protocol::directory(Config::gatewayNonceDirectory(),ABSPATH); }
        catch(\Throwable $e){return $routes;}
        foreach(Config::gatewayClients() as$id=>$c){
            if(!is_string($id)||!is_array($c)||!isset($c['gateway_base'],$c['account_sid'],$c['numbers'],$c['destination'],$c['secret']))continue;
            if(!preg_match('/\A[a-z0-9][a-z0-9-]{0,39}\z/',$id)||!\SiteEngine\CallBridge\Gateway\Protocol::httpsUrl($c['gateway_base'])||!is_string($c['secret'])||strlen($c['secret'])<32)continue;
            if(!\SiteEngine\CallBridge\Gateway\Protocol::identifier($c['destination'])||!\SiteEngine\CallBridge\Gateway\Protocol::accountSid($c['account_sid'])||$c['account_sid']!==Config::twilioAccountSid()||!is_array($c['numbers'])||$c['numbers']===[])continue;
            foreach($c['numbers'] as$n)if(!is_string($n)||!preg_match('/\A\+[1-9][0-9]{7,14}\z/',$n))continue 2;
            $routes[]=['client'=>$id,'voice'=>$c['gateway_base'].'?'.http_build_query(['client'=>$id,'event'=>'voice'],'','&',PHP_QUERY_RFC3986),
                'status'=>$c['gateway_base'].'?'.http_build_query(['client'=>$id,'event'=>'status'],'','&',PHP_QUERY_RFC3986)];
        }
        return $routes;
    }
}
