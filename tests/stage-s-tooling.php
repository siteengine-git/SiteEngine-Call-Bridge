<?php
/** Local pure verdict + real PHP subprocess tests. SQL evidence is SIMULATED. */
declare(strict_types=1);
require __DIR__.'/../tools/acceptance/stage-s-verdict.php';
require __DIR__.'/../tools/acceptance/stage-s-process.php';
require __DIR__.'/../tools/acceptance/stage-s-race.php';
$pass=0;$fail=0;
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
function check_s(bool $ok,string $message):void {global $pass,$fail;if($ok){$pass++;echo 'PASS: '.$message.PHP_EOL;}else{$fail++;echo 'FAIL: '.$message.PHP_EOL;}}
function fixture_e(string $case='last-slot-01',string $winner='a'):array {
    $lock='secb_alloc_'.str_repeat('a',40);$timeout=str_starts_with($case,'lock-timeout');$workers=[];$fixtures=[];
    for($i=1;$i<=5;$i++)$fixtures[]=['id'=>10+$i,'phone_number'=>'+120255501'.sprintf('%02d',$i),'source'=>'cbaccept_20261027_'.($i===1?'alpha':($i===2?'beta':'pool')),'pool_group'=>'cbaccept_'.($i===1?'last':($i===2?'beta':($i===5?'small':'large'))),'forward_to'=>'+12025550190','is_active'=>'1','record_calls'=>'0','notes'=>'cbaccept-20261027-01','display_label'=>'cbaccept-20261027-01-'.$i];
    foreach(['a','b'] as $i=>$label){
        $w=secb_s_expected($case,$label);$ready=$w+['connection'=>101+$i];
        $row=['id'=>'77','tracking_number_id'=>($w['source']==='cbaccept_20261027_beta'?'12':'11'),'tracking_number'=>($w['source']==='cbaccept_20261027_beta'?'+12025550102':'+12025550101'),'session_id'=>$w['session'],'source'=>$w['source'],'status'=>'active','leased_at'=>'2026-10-28 12:00:00','expires_at'=>'2026-10-28 12:30:00'];
        $result=$ready+['connection_after'=>101+$i,'lease'=>!$timeout&&$label===$winner?$row:null,'elapsed'=>$timeout?2.05:0.01,'lock'=>['name'=>$lock,'owner_after'=>$timeout?900:null,'checked_while_open'=>true]];
        $workers[$label]=['ready'=>$ready,'result'=>$result,'exit'=>['closed'=>true,'running'=>false,'exit_code'=>0,'terminated'=>false,'timed_out'=>false,'signaled'=>false]];
    }
    return ['case'=>$case,'complete'=>true,'now'=>'2026-10-28 12:00:01','supervisor_connection'=>900,'lock_name'=>$lock,'workers'=>$workers,'probe'=>['open_connections'=>[101,102],'connection'=>900,'owner_before'=>null,'acquired'=>1,'released'=>1,'owner_after'=>null],'supervisor_lock'=>['acquired'=>$timeout?1:0,'released'=>$timeout?1:0],'final_lock_owner'=>null,'leases'=>$timeout?[]:[$workers[$winner]['result']['lease']],'fixtures'=>$fixtures,'fixture_ids'=>[11,12,13,14,15]];
}
foreach(['last-slot-01','same-session-20','lock-timeout-01'] as $case)foreach(['a','b'] as $winner)check_s(secb_s_verdict(fixture_e($case,$winner))['pass'],'positive '.$case.' winner '.$winner.' (simulated SQL)');
$negative=[];
$e=fixture_e('lock-timeout-01');$e['leases']=fixture_e()['leases'];$negative['historical timeout nulls with unexpected active row']=$e;
$e=fixture_e();$e['leases'][0]['expires_at']=$e['workers']['a']['result']['lease']['expires_at']='2026-10-28 11:59:00';$negative['historical last-slot no active reservation']=$e;
foreach(['exit_code'=>7,'closed'=>false,'running'=>true,'terminated'=>true,'timed_out'=>true,'signaled'=>true] as $key=>$value){$e=fixture_e();$e['workers']['a']['exit'][$key]=$value;$negative['result file followed by '.$key.'='.json_encode($value)]=$e;}
$e=fixture_e();$e['workers']['a']['exit']['exit_code']=null;$negative['unknown exit']=$e;
$e=fixture_e();unset($e['workers']['b']['result']);$negative['missing result']=$e;
$e=fixture_e();$e['workers']['a']['result']='malformed';$negative['malformed result']=$e;
$e=fixture_e();$e['complete']=false;$negative['incomplete evidence']=$e;
$e=fixture_e();$e['workers']['a']['result']['lease']['id']='78';$negative['returned persisted lease ID mismatch']=$e;
foreach(['session_id'=>'foreign','source'=>'cbaccept_20261027_beta','tracking_number_id'=>'12','tracking_number'=>'+12025550199','expires_at'=>'2026-10-28 12:29:00'] as $key=>$value){$e=fixture_e();$e['workers']['a']['result']['lease'][$key]=$value;$negative['returned persisted '.$key.' mismatch']=$e;}
$e=fixture_e();unset($e['workers']['a']['result']['lease']['expires_at']);$negative['missing expiry']=$e;
$e=fixture_e();$e['workers']['a']['result']['connection_after']=666;$negative['connection reconnect']=$e;
$e=fixture_e();$e['workers']['a']['result']['connection']=102;$negative['ready result connection mismatch']=$e;
$e=fixture_e();$e['workers']['b']['ready']['connection']=101;$e['workers']['b']['result']['connection']=101;$e['workers']['b']['result']['connection_after']=101;$negative['shared worker connection']=$e;
$e=fixture_e();$e['workers']['a']['result']['lock']['owner_after']=101;$negative['application lock leaked before close']=$e;
$e=fixture_e();$e['probe']['open_connections']=[102];$negative['release hidden by closed worker connection']=$e;
foreach(['acquired'=>0,'released'=>0,'owner_after'=>101] as $key=>$value){$e=fixture_e();$e['probe'][$key]=$value;$negative['probe '.$key.' failure']=$e;}
$e=fixture_e('lock-timeout-01');$e['supervisor_lock']['released']=0;$negative['supervisor release failed']=$e;
$e=fixture_e('lock-timeout-01');$e['workers']['a']['result']['elapsed']=0.1;$negative['timeout without wait evidence']=$e;
$e=fixture_e();$e['fixtures'][0]['notes']='foreign';$negative['foreign fixture marker']=$e;
$e=fixture_e();$e['fixture_ids'][0]=999;$negative['foreign fixture ID']=$e;
$e=fixture_e();$e['leases'][]=$e['leases'][0];$negative['duplicate persisted ownership']=$e;
$e=fixture_e();$e['workers']['b']['result']['lease']=$e['leases'][0];$negative['two non-null winners']=$e;
$e=fixture_e();$e['workers']['a']['result']['lease']=null;$negative['zero winners with persisted allocation']=$e;
$e=fixture_e();$e['now']='not a time';$negative['malformed authoritative clock']=$e;
$e=fixture_e();$e['final_lock_owner']=900;$negative['final lock retained']=$e;
$e=fixture_e();unset($e['workers']['a']['result']['lock']);$negative['missing live release evidence']=$e;
foreach($negative as $name=>$e)check_s(!secb_s_verdict($e)['pass'],'reject '.$name.' (simulated SQL)');
foreach(['','{bad','null','[]','123','"string"'] as $json){$refused=false;try{secb_s_decode($json);}catch(Throwable $error){$refused=true;}check_s($refused,'reject malformed/missing JSON '.json_encode($json));}
check_s(secb_s_decode('{"connection":101}')===['connection'=>101],'valid JSON object decodes');
// Preserve the exact old false-positive predicate as historical regression input.
$old=static function($kind,$won,$active,$leases){$duplicates=[];$duplicateSessions=[];$identity=true;return $duplicates===[]&&$duplicateSessions===[]&&$identity&&($kind==='lock-timeout'?$won===0:($kind==='same-session'?$active===1&&$won===1&&count($leases)===1:$won===1&&count($leases)===1));};
check_s($old('lock-timeout',0,1,[['status'=>'active']]),'historical timeout counterexample still reproduced by original predicate');
check_s($old('last-slot',1,0,[['status'=>'expired']]),'historical last-slot counterexample still reproduced by original predicate');
$p=['pid'=>42,'running'=>true,'exit_code'=>null,'signaled'=>false,'closed'=>false];
secb_s_process_observe($p,['pid'=>42,'running'=>false,'exitcode'=>0,'signaled'=>false]);secb_s_process_observe($p,['pid'=>42,'running'=>false,'exitcode'=>-1,'signaled'=>false]);secb_s_process_merge_close($p,-1);
check_s($p['exit_code']===0&&$p['closed'],'preserve first exit across legacy unknown subsequent poll/close');
$p=['pid'=>42,'running'=>true,'exit_code'=>null,'signaled'=>false,'closed'=>false];secb_s_process_observe($p,['pid'=>42,'running'=>false,'exitcode'=>-1]);secb_s_process_merge_close($p,-1);check_s($p['exit_code']===null,'unknown status is never normalized to success');
// Execute actual lock-helper control flow against explicitly simulated SQL only.
class SECB_Lock_Review_Double {
    public string $last_error='';public ?int $owner=null;public int $connection=900;public int $releases=0;public bool $deny=false;public bool $releaseFails=false;
    public function prepare($sql,...$args){return str_replace('%s',"'simulated-lock'",$sql);}
    public function get_var($sql){$this->last_error='';if($this->deny){$this->last_error='simulated denial';return null;}if(str_contains($sql,'CONNECTION_ID'))return $this->connection;if(str_contains($sql,'IS_USED_LOCK'))return $this->owner;if(str_contains($sql,'GET_LOCK')){if($this->owner!==null)return 0;$this->owner=$this->connection;return 1;}if(str_contains($sql,'RELEASE_LOCK')){$this->releases++;if($this->releaseFails)return 0;if($this->owner!==$this->connection)return 0;$this->owner=null;return 1;}throw new RuntimeException('unhandled simulated SQL');}
}
function secb_accept_rows(string $sql):array {global $reviewOpenConnections;return array_map(fn($id)=>['ID'=>$id],$reviewOpenConnections);}
$wpdb=new SECB_Lock_Review_Double();$reviewOpenConnections=[101,102];$lock='secb_alloc_'.str_repeat('a',40);
$probe=secb_s_probe($lock,[101,102],900);check_s($probe['acquired']===1&&$probe['released']===1&&$wpdb->owner===null,'actual probe helper positive with SIMULATED SQL');
foreach(['foreign','read-denial','closed-worker','failed-release','wrong-supervisor'] as $scenario){
    $wpdb=new SECB_Lock_Review_Double();$reviewOpenConnections=[101,102];$super=900;
    if($scenario==='foreign')$wpdb->owner=777;if($scenario==='read-denial')$wpdb->deny=true;if($scenario==='closed-worker')$reviewOpenConnections=[102];if($scenario==='failed-release')$wpdb->releaseFails=true;if($scenario==='wrong-supervisor')$super=901;
    $refused=false;try{secb_s_probe($lock,[101,102],$super);}catch(Throwable $error){$refused=true;}check_s($refused,'actual probe refuses '.$scenario.' with SIMULATED SQL');
    if($scenario==='foreign')check_s($wpdb->releases===0&&$wpdb->owner===777,'foreign SQL lock is never released (SIMULATED)');
}
$wpdb=new SECB_Lock_Review_Double();$wpdb->owner=777;$refused=false;try{secb_s_release_owned($lock,900);}catch(Throwable $error){$refused=true;}check_s($refused&&$wpdb->releases===0,'supervisor release refuses foreign owner (SIMULATED)');
// Actual local subprocesses; only temporary local evidence files, no SQL/WP.
$dir=dirname(__DIR__).'/.secrets/stage-s-local-'.bin2hex(random_bytes(6));if(!mkdir($dir,0700,true))throw new RuntimeException('local test allocation failed');
foreach([0,7] as $exit){
    $file=$dir.'/result-'.$exit.'.json';$out=$dir.'/out-'.$exit.'.txt';$err=$dir.'/err-'.$exit.'.txt';
    $code='file_put_contents('.var_export(basename($file),true).',"{}"); exit('.$exit.');';
    $h=proc_open([PHP_BINARY,'-n','-r',$code],[0=>['pipe','r'],1=>['file',$out,'w'],2=>['file',$err,'w']],$pipes,$dir);fclose($pipes[0]);$p=secb_s_process_new($h);$deadline=microtime(true)+5;
    while(secb_s_process_poll($p)){if(microtime(true)>$deadline){$p['timed_out']=true;secb_s_process_abort($p);break;}usleep(10000);}
    for($i=0;$i<3;$i++)secb_s_process_poll($p);$status=secb_s_process_close($p);
    check_s(is_file($file)&&$status['exit_code']===$exit,'actual child result-file then exit '.$exit.' survives repeated polls');
    $e=fixture_e();$e['workers']['a']['exit']=$status;check_s(secb_s_verdict($e)['pass']===($exit===0),'actual exit '.$exit.' gates simulated-SQL verdict');
}
$h=proc_open([PHP_BINARY,'-n','-r','usleep(1000000);'],[0=>['pipe','r'],1=>['file',$dir.'/out-timeout.txt','w'],2=>['file',$dir.'/err-timeout.txt','w']],$pipes);fclose($pipes[0]);$p=secb_s_process_new($h);$deadline=microtime(true)+0.05;while(secb_s_process_poll($p)&&microtime(true)<$deadline)usleep(1000);$p['timed_out']=$p['running'];secb_s_process_abort($p);$e=fixture_e();$e['workers']['a']['exit']=secb_s_process_close($p);check_s(!secb_s_verdict($e)['pass'],'actual timed-out/terminated child cannot pass');
// Retain these small evidence files; no broad filesystem cleanup.
echo 'BOUNDARY: SQL arrays simulated; actual local PHP process tests only; no native concurrency or Alma requests.'.PHP_EOL;
echo "SUMMARY: {$pass} PASS, {$fail} FAIL; PHP ".PHP_VERSION.PHP_EOL;exit($fail?1:0);
