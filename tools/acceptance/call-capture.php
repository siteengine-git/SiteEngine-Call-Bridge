<?php
/** CB-owned isolated native fixture. AB's existing executor owns the installation slot. */
declare(strict_types=1);

use SiteEngine\CallBridge\DB\AttributionSessionRepository;
use SiteEngine\CallBridge\DB\CallLogRepository;
use SiteEngine\CallBridge\DB\NumberLeaseRepository;
use SiteEngine\CallBridge\DB\TrackingNumberRepository;
use SiteEngine\CallBridge\Integration\CallEvidence;
use SiteEngine\CallBridge\Support\Config;

if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('cli_only');
global $wpdb;
[$mode,$run,$expectedRoot,$manifestHash,$expectedPrefix] = $args;
$run = realpath($run); $root = realpath(ABSPATH);
$home = parse_url((string) get_option('home'));
if (!$run || $root !== realpath($expectedRoot) || $root === '/home/alma.siteengine.space/public_html'
    || str_starts_with($run, $root.'/') || ($home['host'] ?? '') !== '127.0.0.1'
    || ($home['port'] ?? 0) < 1024 || $wpdb->prefix !== $expectedPrefix
    || !preg_match('/^[A-Za-z0-9_]+$/D', $expectedPrefix)
    || SECB_VERSION !== '0.1.7' || SECB_DB_VERSION !== '1.1.0' || get_option('se_cb_db_version') !== '1.1.0') throw new RuntimeException('isolated_target_gate');
$plugin = realpath(WP_PLUGIN_DIR.'/siteengine-call-bridge');
if (!preg_match('/^[a-f0-9]{64}$/D',$manifestHash) || hash_file('sha256',$plugin.'/RELEASE-MANIFEST.sha256') !== $manifestHash) throw new RuntimeException('manifest_gate');
foreach ([CallEvidence::class, SiteEngine\CallBridge\Support\CallCapture::class, SiteEngine\CallBridge\Webhook\TwilioVoiceController::class, SiteEngine\CallBridge\Webhook\TwilioStatusController::class] as $class) {
    $file = (new ReflectionClass($class))->getFileName();
    if (!str_starts_with($file,$plugin.'/') || !str_contains(file_get_contents($plugin.'/RELEASE-MANIFEST.sha256'),hash_file('sha256',$file).'  ./'.substr($file,strlen($plugin)+1)."\n")) throw new RuntimeException('class_origin_gate');
}
if ((fileperms($run)&0777)!==0700) throw new RuntimeException('private_run_required');
$marker='cbcap-'.substr(hash('sha256',$run),0,12);
$sid='CA'.substr(hash('sha256',$marker),0,32); $sessionId=$marker.'-session';
$phone='+10000000071';$forward='+10000000079';
$ledgerFile=$run.'/fixture.json';
$write=static function(string $name,array $data)use($run):void {
    $path=$run.'/'.$name;if(file_exists($path))throw new RuntimeException('preserve_evidence');
    file_put_contents($path,json_encode($data,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));chmod($path,0600);
};
$norm=static fn(array $r)=>array_map(static fn($v)=>$v===null?null:(string)$v,$r);
$snapshot=static function()use($wpdb,$norm):array {
    $out=[];foreach(['tracking_numbers','attribution_sessions','number_leases','calls'] as $n){
        $rows=$wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'se_cb_'.$n.' ORDER BY id',ARRAY_A);
        if($wpdb->last_error)throw new RuntimeException('snapshot_read_failed');
        $out[$n]=[];foreach($rows as $r)$out[$n][$r['id']]=hash('sha256',json_encode($norm($r),JSON_THROW_ON_ERROR));
    }return $out;
};
$eventSnapshot=static function()use($wpdb):array {
    $rows=$wpdb->get_results("SELECT option_name,option_value,autoload FROM {$wpdb->options} WHERE option_name IN ('se_cb_event_voice_hit','se_cb_event_status_hit') ORDER BY option_name",ARRAY_A);
    if($wpdb->last_error)throw new RuntimeException('event_read_failed');return $rows;
};
if($mode==='prepare'){
    if(file_exists($ledgerFile)||Config::twilioAuthToken()!==null||Config::twilioAccountSid()!==null)throw new RuntimeException('existing_run_or_real_credentials');
    if($wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$wpdb->prefix.'se_cb_tracking_numbers WHERE phone_number=%s',$phone))!=0
        || (new CallLogRepository())->findByCallSid($sid,true)!==null
        || (new AttributionSessionRepository())->findBySessionId($sessionId)!==null
        || $wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->prefix."se_cb_number_leases WHERE status='active' AND expires_at<=UTC_TIMESTAMP()")!=0)throw new RuntimeException('fixture_or_gc_collision');
    $before=$snapshot();$events=$eventSnapshot();$wpdb->query('START TRANSACTION');
    try{
        $numberId=(new TrackingNumberRepository())->insert(['phone_number'=>$phone,'forward_to'=>$forward,'display_label'=>$marker,'source'=>$marker,'pool_group'=>$marker,'record_calls'=>0,'min_duration_sec'=>60,'repeat_window_days'=>30,'is_active'=>1,'notes'=>'SYNTHETIC '.$marker]);
        if(!$numberId)throw new RuntimeException('number_seed');
        $data=['session_id'=>$sessionId,'utm_source'=>$marker,'utm_medium'=>'synthetic_test','utm_campaign'=>$marker,'landing_page'=>'https://fixture.invalid/'.$marker,'expires_at'=>gmdate('Y-m-d H:i:s',time()+3600)];
        $lease=(new NumberLeaseRepository())->acquire($marker,$sessionId,static fn()=>(new AttributionSessionRepository())->upsert($data)!==false);
        if(!$lease||$lease['pool_group']!==$marker)throw new RuntimeException('allocation_capture_failed');
        $session=(new AttributionSessionRepository())->findBySessionId($sessionId);
        $write('fixture.json',['marker'=>$marker,'sid'=>$sid,'session_id'=>$sessionId,'number_id'=>$numberId,'session_row_id'=>$session['id'],'lease_id'=>$lease['id'],'lease'=>$lease,'before'=>$before,'events_before'=>$events]);
        if($wpdb->query('COMMIT')===false)throw new RuntimeException('seed_commit_uncertain');
    }catch(Throwable $e){$wpdb->query('ROLLBACK');throw $e;}
    echo json_encode(['status'=>'PASS','phase'=>'prepare','new_rows'=>3,'recording_off'=>true]);return;
}
if(!is_file($ledgerFile))throw new RuntimeException('owned_fixture_required');
$l=json_decode(file_get_contents($ledgerFile),true,512,JSON_THROW_ON_ERROR);
if($l['marker']!==$marker||$l['sid']!==$sid)throw new RuntimeException('ledger_identity_conflict');
$repo=new CallLogRepository();
if($mode==='change'){
    $call=$repo->findByCallSid($sid,true);if(!$call||$call['source']!==$marker)throw new RuntimeException('call_ownership');
    if($wpdb->update($wpdb->prefix.'se_cb_tracking_numbers',['pool_group'=>$marker.'-later','min_duration_sec'=>120],['id'=>$l['number_id'],'notes'=>'SYNTHETIC '.$marker])!==1
        || $wpdb->update($wpdb->prefix.'se_cb_attribution_sessions',['utm_campaign'=>$marker.'-later'],['id'=>$l['session_row_id'],'session_id'=>$sessionId])!==1)throw new RuntimeException('owned_context_update');
    if(CallEvidence::read('twilio:'.$sid)!==json_decode(file_get_contents($run.'/voice.json'),true)['evidence'])throw new RuntimeException('context_recaptured');
    $write('change.json',['status'=>'PASS','original_capture_unchanged'=>true]);
    echo json_encode(['status'=>'PASS','phase'=>'change']);return;
}
if($mode==='after-terminal-change'){
    if($wpdb->update($wpdb->prefix.'se_cb_tracking_numbers',['min_duration_sec'=>5],['id'=>$l['number_id'],'notes'=>'SYNTHETIC '.$marker])!==1)throw new RuntimeException('owned_threshold_update');
    $write('after-terminal-change.json',['status'=>'PASS']);echo json_encode(['status'=>'PASS','phase'=>$mode]);return;
}
if($mode==='cleanup'||$mode==='retain'){
    $call=$repo->findByCallSid($sid,true);$number=(new TrackingNumberRepository())->findById((int)$l['number_id']);
    $session=(new AttributionSessionRepository())->findBySessionId($sessionId);
    $lease=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.$wpdb->prefix.'se_cb_number_leases WHERE id=%d',$l['lease_id']),ARRAY_A);
    if(!$number||$number['notes']!=='SYNTHETIC '.$marker||$number['phone_number']!==$phone||!$session||(int)$session['id']!==(int)$l['session_row_id']
        || !$lease||$lease['session_id']!==$sessionId||($call&&($call['source']!==$marker||(int)$call['tracking_number_id']!==(int)$l['number_id'])))throw new RuntimeException('cleanup_ownership');
    if($mode==='cleanup'&&$call&&(!isset($GLOBALS['secb_capture_can_delete'])||!is_callable($GLOBALS['secb_capture_can_delete'])||($GLOBALS['secb_capture_can_delete'])('twilio:'.$sid,(int)$call['id'])!==true))throw new RuntimeException('AB_owner_reference_check_required');
    $currentEvents=$eventSnapshot();$latest=null;
    foreach(['late-terminal','late-progress','terminal-replay','terminal','voice-replay','voice'] as $phase)if(file_exists($run.'/'.$phase.'.json')){$latest=json_decode(file_get_contents($run.'/'.$phase.'.json'),true)['events'];break;}
    if(file_exists($run.'/retained.json'))$latest=$l['events_before'];
    if($latest!==null&&$latest!==$currentEvents)throw new RuntimeException('concurrent_event_change');
    $wpdb->query('START TRANSACTION');try{
        if($mode==='retain'){
            if($wpdb->update($wpdb->prefix.'se_cb_tracking_numbers',['is_active'=>0],['id'=>$l['number_id'],'notes'=>'SYNTHETIC '.$marker])===false||$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}se_cb_number_leases SET status='released',released_at=COALESCE(released_at,UTC_TIMESTAMP()) WHERE id=%d AND session_id=%s AND status='active'",$l['lease_id'],$sessionId))===false)throw new RuntimeException('retain_release_failed');
        }else{
        if($call&&$wpdb->delete($repo->table(),['id'=>$call['id'],'twilio_call_sid'=>$sid])!==1)throw new RuntimeException('owned_call_delete');
        foreach(['number_leases'=>['id'=>$l['lease_id'],'session_id'=>$sessionId],'attribution_sessions'=>['id'=>$l['session_row_id'],'session_id'=>$sessionId],'tracking_numbers'=>['id'=>$l['number_id'],'notes'=>'SYNTHETIC '.$marker]] as $table=>$where)
            if($wpdb->delete($wpdb->prefix.'se_cb_'.$table,$where)!==1)throw new RuntimeException('owned_fixture_delete');
        }
        foreach($currentEvents as $e){$old=null;foreach($l['events_before'] as $v)if($v['option_name']===$e['option_name'])$old=$v;
            if($old)$ok=$wpdb->update($wpdb->options,['option_value'=>$old['option_value'],'autoload'=>$old['autoload']],['option_name'=>$e['option_name'],'option_value'=>$e['option_value']]);
            else $ok=$wpdb->delete($wpdb->options,['option_name'=>$e['option_name'],'option_value'=>$e['option_value']]);
            if($ok===false)throw new RuntimeException('event_restore');wp_cache_delete($e['option_name'],'options');
        }
        $wpdb->query('COMMIT');
    }catch(Throwable $e){$wpdb->query('ROLLBACK');throw $e;}
    wp_cache_delete('alloptions','options');
    $now=$snapshot();
    if($mode==='retain'){foreach($l['before'] as $table=>$rows)foreach($rows as $id=>$hash)if(($now[$table][$id]??null)!==$hash)throw new RuntimeException('old_row_changed');if(CallEvidence::read('twilio:'.$sid)!==json_decode(file_get_contents($run.'/terminal.json'),true)['evidence'])throw new RuntimeException('retention_changed_call');}
    elseif($now!==$l['before'])throw new RuntimeException('baseline_not_preserved');
    if($eventSnapshot()!==$l['events_before'])throw new RuntimeException('events_not_preserved');
    if($mode==='retain'){$write('retained.json',['status'=>'PASS','call_identity'=>'twilio:'.$sid,'call_id'=>$call['id'],'session_id'=>$l['session_row_id'],'lease_id'=>$l['lease_id'],'number_id'=>$l['number_id'],'inactive_number_released_lease'=>true,'retention_owner'=>'AB Codex installation executor / CB-owned evidence','reference_check_required_for_delete'=>true]);echo json_encode(['status'=>'PASS','phase'=>'retain']);return;}
    $write('cleanup.json',['status'=>'PASS','owned_rows_removed'=>$call?4:3,'original_rows_and_events_preserved'=>true]);echo json_encode(['status'=>'PASS','phase'=>'cleanup']);return;
}
if(in_array($mode,['read-a','read-b'],true)){
    $value=CallEvidence::read('twilio:'.$sid);$expected=json_decode(file_get_contents($run.'/terminal.json'),true)['evidence'];
    if($value!==$expected)throw new RuntimeException('fresh_read_changed');$write($mode.'.json',['status'=>'PASS','evidence'=>$value,'read_only'=>true]);echo json_encode(['status'=>'PASS','phase'=>'read']);return;
}
$voice=in_array($mode,['voice','voice-replay'],true);
if(!$voice&&!in_array($mode,['terminal','terminal-replay','late-progress','late-terminal'],true))throw new RuntimeException('unsupported_phase');
if(Config::twilioAuthToken()!==null||Config::twilioAccountSid()!==null||file_exists($run.'/'.$mode.'.json'))throw new RuntimeException('credential_or_evidence_conflict');
define('SECB_TWILIO_AUTH_TOKEN','synthetic-process-only-signing-token');
$post=$voice?['CallSid'=>$sid,'From'=>'+10000000072','To'=>$phone]:['CallSid'=>$sid,'DialCallStatus'=>$mode==='late-progress'?'ringing':($mode==='late-terminal'?'busy':'completed'),'DialCallDuration'=>$mode==='late-progress'||$mode==='late-terminal'?'0':'90'];
$uri='/wp-json/siteengine-call/v1/twilio/'.($voice?'voice':'status');$text=Config::webhookBaseUrl().$uri;ksort($post,SORT_STRING);foreach($post as $k=>$v)$text.=$k.$v;
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['REQUEST_URI']=$uri;$_SERVER['HTTP_X_TWILIO_SIGNATURE']=base64_encode(hash_hmac('sha1',$text,'synthetic-process-only-signing-token',true));$_POST=wp_slash($post);$http=0;
add_filter('status_header',static function($header,$code)use(&$http){$http=(int)$code;return $header;},10,2);
register_shutdown_function(static function()use($repo,$sid,$mode,$run,$l,$marker,$write,$eventSnapshot,&$http){
    try{
        $row=$repo->findByCallSid($sid,true);$e=CallEvidence::read('twilio:'.$sid);$c=$e['call'];
        if($http!==200||$e['status']!=='found'||$c['identity']!=='twilio:'.$sid||$c['number_assignment']['lease_id']!==(int)$l['lease_id']||$c['number_assignment']['pool_group']!==$marker||$c['acquisition']['utm_campaign']!==$marker||$c['consent_receipt_id']!==null||$c['authoritative_consent_linkage']!==null)throw new RuntimeException('native_capture_assertion');
        if($mode==='terminal'&&($c['outcome']['qualification_threshold_sec']!==120||$c['outcome']['duration_sec']!==90||$c['outcome']['technical_qualification']!=='unqualified_short'))throw new RuntimeException('native_terminal_assertion');
        $baseline=$mode==='voice-replay'?'voice':(in_array($mode,['terminal-replay','late-progress','late-terminal'],true)?'terminal':null);
        if($baseline!==null&&$e!==json_decode(file_get_contents($run.'/'.$baseline.'.json'),true)['evidence'])throw new RuntimeException('native_replay_changed');
        $write($mode.'.json',['status'=>'PASS','execution'=>'native WP controller; process-local independent signer; no HTTP/provider','http'=>$http,'evidence'=>$e,'api_sha256'=>hash_file('sha256',(new ReflectionClass(CallEvidence::class))->getFileName()),'events'=>$eventSnapshot()]);
    }catch(Throwable $error){$write($mode.'-failure.json',['status'=>'FAIL','reason'=>$error->getMessage()]);exit(1);}
});
$controller=$voice?new SiteEngine\CallBridge\Webhook\TwilioVoiceController():new SiteEngine\CallBridge\Webhook\TwilioStatusController();$controller->handle(new WP_REST_Request());
