<?php
/** Pure link-boundary checks; native Base UI tested separately on Alma. */
define('ABSPATH', __DIR__.'/fixture/');
class WP_Error {}
function is_wp_error($v) { return $v instanceof WP_Error; }
function siteengine_credentials_contract_version() { return 'credentials-1'; }
function siteengine_register_credentials_v1(...$args) { return true; }
function siteengine_credentials_runtime_read_v1(...$args) { return null; }
function siteengine_credentials_metadata_v1(...$args) { return null; }
$mode='ok'; $calls=[];
function siteengine_credentials_admin_url_v1(...$args) {
    $GLOBALS['calls'][]=$args;
    if($GLOBALS['mode']==='error')return new WP_Error;
    if($GLOBALS['mode']==='throw')throw new RuntimeException('private body');
    return 'https://fixture.invalid/wp-admin/admin.php?page=siteengine-credentials&module='.$args[0].'&credential='.$args[1].'&resource='.$args[2];
}
require dirname(__DIR__).'/src/Integration/BaseCredentials.php';
$class='SiteEngine\\CallBridge\\Integration\\BaseCredentials';$count=0;
function check($value) { if(!$value)throw new RuntimeException('link assertion failed'); $GLOBALS['count']++; }
check(str_contains($class::adminUrl(),'credential=twilio-webhook'));
check($calls[0]===['call-bridge','twilio-webhook','default']);
check(str_contains($class::adminUrl('playback'),'credential=twilio-playback'));
$mode='error';check($class::adminUrl()===null);
$mode='throw';check($class::adminUrl()===null);
check($class::adminUrl('unknown')===null);
echo "$count PASS; mocked helper boundary, no web/SQL execution\n";
