<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Support;
defined('ABSPATH') || exit;
/** Reference facts only. Never synchronize these values into routing. */
final class SharedBusinessContext
{
    public static function read(): array
    {
        $out=[];
        foreach (['business_name','phone','forwarding_phone','privacy_policy_url'] as $key) {
            $value=function_exists('siteengine_get') ? siteengine_get($key,'') : '';
            $out[$key]=['value'=>is_string($value)?$value:'',
                'stored'=>function_exists('siteengine_has') ? (bool)siteengine_has($key) : false];
        }
        return $out;
    }
}
