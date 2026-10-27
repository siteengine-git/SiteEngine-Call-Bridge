<?php
/** Prepared browser fixture ownership/cleanup. No automatic execution. */
declare(strict_types=1);
defined('ABSPATH') || exit;
require_once __DIR__.'/lease-sql.php';
function secb_accept_browser(string $mode,string $run,bool $write=false):array {
    if(!$write)return secb_accept_browser_operation($mode,$run,false);secb_accept_plan($run);$h=secb_accept_operation_lock($run);try{return secb_accept_browser_operation($mode,$run,true);}finally{flock($h,LOCK_UN);fclose($h);}
}
function secb_accept_browser_operation(string $mode,string $run,bool $write=false):array {
    global $wpdb;$plan=secb_accept_plan($run);$prefix=$plan['root_prefix'];
    $wpdb->hide_errors();
    $rows=[];foreach([6,7,8] as $n)$rows[]=['phone_number'=>'+120255501'.sprintf('%02d',$n),'forward_to'=>'+12025550190','source'=>'cbaccept_20261027_browser'.($n===7?'_alt':''),'pool_group'=>'cbaccept_browser'.($n===7?'_alt':''),'display_label'=>'cbaccept-20261027-01-browser-'.$n,'record_calls'=>0,'is_active'=>$n===8?0:1,'notes'=>'cbaccept-20261027-01-browser'];
    $sessions=['a'=>'cbaccept-20261027-01-browser-a','b'=>'cbaccept-20261027-01-browser-b'];
    $file=$run.'/browser-state.json';
    if($mode==='plan')return ['rows'=>$rows,'sessions'=>$sessions,'run_expires_after_seconds'=>3600,'fixture_page_relative'=>'secb-acceptance-20261027-01/index.php'];
    if(!$write)throw new RuntimeException('No browser fixture writes before explicit review authorization');
    secb_accept_query('SET SESSION max_statement_time=5');
    if($mode==='seed'){
        if(file_exists($file))throw new RuntimeException('Preserve existing browser run state');
        $before=secb_accept_snapshot($prefix);foreach(['tracking_numbers','attribution_sessions','number_leases','calls'] as $t)if((int)$before[$t]['rows']!==0)throw new RuntimeException('Browser run requires empty CB tables; review any existing data without deleting it');
        foreach($sessions as $sid){$key='secb_rl_'.md5($sid);if(get_transient($key)!==false)throw new RuntimeException('Rate-key collision');foreach(['_transient_'.$key,'_transient_timeout_'.$key] as $name)if(secb_accept_rows($wpdb->prepare("SELECT option_name FROM `{$prefix}options` WHERE option_name=%s",$name)))throw new RuntimeException('Transient ownership collision');}
        $state=['run'=>'cb-lease-dni-20261027-01','prefix'=>$prefix,'rows'=>$rows,'sessions'=>$sessions,'ids'=>[],'before'=>$before,'expires'=>time()+3600,'phase'=>'seeding'];secb_accept_json($file,$state,true);
        secb_accept_query('START TRANSACTION');
        try{foreach($rows as $row){$id=(new \SiteEngine\CallBridge\DB\TrackingNumberRepository())->insert($row);if(!$id)throw new RuntimeException('Browser fixture insert failed');$state['ids'][]=$id;}secb_accept_json($file,$state);secb_accept_query('COMMIT');}catch(\Throwable $e){secb_accept_query('ROLLBACK');throw $e;}
        $state['phase']='ready';secb_accept_json($file,$state);return ['fixture_ids'=>$state['ids'],'sessions'=>$sessions,'expires'=>$state['expires']];
    }
    if(is_link($file)||!is_file($file)||(fileperms($file)&0777)!==0600||fileowner($file)!==posix_geteuid())throw new RuntimeException('Restricted owned browser state required');
    $state=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
    if($state['run']!==$plan['run']||$state['prefix']!==$prefix||$state['rows']!==$rows||$state['sessions']!==$sessions||count($state['ids'])!==count($rows))throw new RuntimeException('Browser run ownership mismatch');
    foreach($state['ids'] as $i=>$id){$r=secb_accept_rows($wpdb->prepare("SELECT * FROM `{$prefix}se_cb_tracking_numbers` WHERE id=%d",$id));if(count($r)!==1)throw new RuntimeException('Fixture row missing; preserve evidence');foreach(['phone_number','forward_to','source','pool_group','notes','display_label'] as $k)if($r[0][$k]!==$rows[$i][$k])throw new RuntimeException('Fixture ownership conflict');}
    if($mode==='inspect')return ['current'=>secb_accept_snapshot($prefix),'before'=>$state['before'],'fixture_ids'=>$state['ids']];
    if($mode!=='cleanup')throw new RuntimeException('Unknown browser mode');
    // Stop/unload both browser contexts before cleanup. Never delete calls or unrelated rows.
    $sidList=implode(',',array_map(fn($sid)=>$wpdb->prepare('%s',$sid),array_values($sessions)));$idList=implode(',',array_map('intval',$state['ids']));
    if(secb_accept_rows("SELECT id FROM `{$prefix}se_cb_number_leases` WHERE tracking_number_id IN ($idList) AND session_id NOT IN ($sidList)"))throw new RuntimeException('Foreign session references fixture number; manual scoped review required');
    foreach(secb_accept_rows("SELECT session_id,utm_source FROM `{$prefix}se_cb_attribution_sessions` WHERE session_id IN ($sidList)") as $r)if(!in_array($r['utm_source'],array_column($rows,'source'),true))throw new RuntimeException('Session ownership conflict');
    secb_accept_query('START TRANSACTION');
    try{
        secb_accept_query("DELETE FROM `{$prefix}se_cb_number_leases` WHERE session_id IN ($sidList) AND tracking_number_id IN ($idList)");
        secb_accept_query("DELETE FROM `{$prefix}se_cb_attribution_sessions` WHERE session_id IN ($sidList)");
        foreach($state['ids'] as $i=>$id){$ok=$wpdb->delete($prefix.'se_cb_tracking_numbers',['id'=>(int)$id,'phone_number'=>$rows[$i]['phone_number'],'notes'=>$rows[$i]['notes']],['%d','%s','%s']);if($ok!==1)throw new RuntimeException('Exact fixture cleanup failed');}
        secb_accept_query('COMMIT');
    }catch(\Throwable $e){secb_accept_query('ROLLBACK');throw $e;}
    foreach($sessions as $sid)delete_transient('secb_rl_'.md5($sid));
    $after=secb_accept_snapshot($prefix);secb_accept_json($run.'/browser-after.json',$after,true);$state['phase']='cleaned';secb_accept_json($file,$state);
    return ['before_after_equal'=>$after===$state['before'],'evidence_retained'=>true];
}
