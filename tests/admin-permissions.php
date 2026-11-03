<?php
/** Isolated capability/status regression; no WordPress, SQL, HTTP or writes. */
declare(strict_types=1);
define('ABSPATH', __DIR__.'/fixture/');
require dirname(__DIR__).'/src/Admin/Menu.php';
require dirname(__DIR__).'/src/Admin/TrackingNumbersPage.php';
final class PermissionResponse extends RuntimeException {
    public function __construct(public int $status, public string $body) { parent::__construct($body); }
}
final class NonceBoundary extends RuntimeException {}
$allowed=false;$nonceCalls=[];$pass=0;$fail=0;
function current_user_can(string $cap):bool {
    if($cap!=='manage_options')throw new RuntimeException('Unexpected capability');
    return $GLOBALS['allowed'];
}
function wp_die(string $message, string $title='', array $args=[]):never {
    throw new PermissionResponse((int)($args['response']??500), $message);
}
function check_admin_referer(string $action):never {
    $GLOBALS['nonceCalls'][]=$action;
    throw new NonceBoundary('Stop before any repository operation');
}
function verify(bool $ok,string $name):void {
    $GLOBALS[$ok?'pass':'fail']++;echo ($ok?'PASS ':'FAIL ').$name."\n";
}
use SiteEngine\CallBridge\Admin\TrackingNumbersPage;
foreach(['anonymous','subscriber'] as $role)foreach(['render','handleSave','handleDelete'] as $method){
    $nonceCalls=[];$response=null;
    try{TrackingNumbersPage::$method();}catch(PermissionResponse $e){$response=$e;}
    verify($response!==null&&$response->status===403,"$role $method returns HTTP 403");
    verify($response!==null&&$response->body==='Insufficient permissions.',"$role $method returns only the fixed denial message");
    verify($nonceCalls===[],"$role $method denies before nonce/repository access");
}
$allowed=true;
foreach(['handleSave'=>'secb_save_number','handleDelete'=>'secb_delete_number'] as $method=>$nonce){
    $nonceCalls=[];$reached=false;
    try{TrackingNumbersPage::$method();}catch(NonceBoundary $e){$reached=true;}
    verify($reached&&$nonceCalls===[$nonce],"administrator $method retains the nonce boundary before repository access");
}
echo "$pass PASS / $fail FAIL; isolated PHP stubs, not installed acceptance\n";
exit($fail?1:0);
