<?php
/** Local template checks; no WordPress bootstrap, database, provider or real data. */
namespace SiteEngine\CallBridge\Admin { final class Menu { public const CAP='manage_options'; } }
namespace {
error_reporting(E_ALL);
set_error_handler(static function($n,$s,$file,$line){throw new \ErrorException($s,0,$n,$file,$line);});
define('ABSPATH',__DIR__.'/fixture/');
define('SECB_REST_NAMESPACE','siteengine-call/v1');
define('SECB_WEBHOOK_BASE_URL','https://fixture.invalid/a-long-installation-path');
$GLOBALS['layout_admin']=true;
function current_user_can($cap){return $GLOBALS['layout_admin'];}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return esc_html($s);}
function esc_url($s){return esc_html($s);}
function admin_url($s){return 'https://fixture.invalid/wp-admin/'.$s;}
require dirname(__DIR__).'/src/Support/Config.php';
require dirname(__DIR__).'/src/Support/Readiness.php';
$hooks=\SiteEngine\CallBridge\Support\Readiness::webhookUrls();
function render_layout($path,$webhooks){
    $readiness=['label'=>'Configuration needed','acceptance'=>'Pending: live calling acceptance.',
        'rows'=>[['name'=>'Account SID','state'=>'Missing','detail'=>'Values are never displayed']],
        'facts'=>['lease_minutes'=>30,'session_days'=>90]];
    $business=array_fill_keys(['business_name','phone','forwarding_phone','privacy_policy_url'],['value'=>'']);
    $helpUrl=admin_url('admin.php?page=read-me-file&tab=call-bridge');
    ob_start();try{include $path;return ob_get_contents();}finally{ob_end_clean();}
}
function dom($html){$prior=libxml_use_internal_errors(true);try{$d=new \DOMDocument();$d->loadHTML('<?xml encoding="UTF-8">'.$html);return new \DOMXPath($d);}finally{libxml_clear_errors();libxml_use_internal_errors($prior);}}
$passed=0;
function check($ok,$message){global $passed;if(!$ok)throw new \RuntimeException('FAIL '.$message);++$passed;echo 'PASS '.$message."\n";}
$path=dirname(__DIR__).'/templates/admin/setup-status.php';$html=render_layout($path,$hooks);$xp=dom($html);
$cards=$xp->query('//section[@class="secb-webhook"]');
check($cards->length===4,'four endpoints from the unchanged URL generator');
foreach($cards as $i=>$card){
    check($xp->query('.//div[@class="secb-webhook-url"]/code',$card)->item(0)->textContent===$hooks[$i]['url'],'exact selectable URL text '.$i);
    check($xp->query('.//code[@class="secb-webhook-method"]',$card)->item(0)->textContent===$hooks[$i]['method'],'exact method '.$i);
    $region=$xp->query('.//div[@role="region"]',$card)->item(0);
    check($region->getAttribute('tabindex')==='0'&&$region->getAttribute('aria-label')===$hooks[$i]['label'].' URL','named keyboard-focusable URL region '.$i);
}
check($xp->query('//form|//input|//button|//script')->length===0,'no editors, action controls or scripts introduced');
if(isset($argv[1])&&$argv[1]!=='-'){
    $old=dom(render_layout($argv[1],$hooks));
    $oldCodes=$old->query('//table[last()]//td/code');
    check($oldCodes->length===4,'previous candidate URL table available for comparison');
    foreach($oldCodes as $i=>$code)check($code->textContent===$xp->query('//div[@class="secb-webhook-url"]/code')->item($i)->textContent,'previous candidate displayed URL unchanged '.$i);
    $links=static function($x){$out=[];foreach($x->query('//a') as $a)$out[]=[$a->getAttribute('href'),$a->textContent];return $out;};
    check($links($old)===$links($xp),'all navigation labels and destinations unchanged');
}
$payload='\"><script>fixture & payload</script>';
$escaped=dom(render_layout($path,[['label'=>$payload,'method'=>$payload,'url'=>$payload,'use'=>$payload]]));
check($escaped->query('//script|//img')->length===0,'endpoint fields remain escaped text');
check($escaped->query('//div[@class="secb-webhook-url"]/code')->item(0)->textContent===$payload,'URL escaping preserves literal displayed text');
check($escaped->query('//div[@role="region"]')->item(0)->getAttribute('aria-label')===$payload.' URL','accessible label escapes attribute payload');
$empty=render_layout($path,[]);
check(str_contains($empty,'The base is not a usable public HTTPS URL.')&&!str_contains($empty,'class="secb-webhooks"'),'unusable URL fallback unchanged');
$GLOBALS['layout_admin']=false;
check(render_layout($path,$hooks)==='','template still denies non-administrator rendering in isolation');
$GLOBALS['layout_admin']=true;
if(isset($argv[2])){
    $preview='<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CB local layout fixture</title><style>body{font:14px/1.5 Arial,sans-serif;margin:20px;background:#f0f0f1;color:#1d2327}.wrap{min-width:0}code{font:13px/1.5 monospace;background:#eee;overflow-wrap:anywhere;word-break:break-all;white-space:normal;user-select:none}table{width:100%}a{color:#2271b1}</style><body><p><strong>LOCAL FIXTURE — NOT DEPLOYED</strong></p>'.$html.'</body></html>';
    file_put_contents($argv[2],$preview);
}
echo "RESULT: {$passed} PASS; local rendering only, not installed acceptance.\n";
}
