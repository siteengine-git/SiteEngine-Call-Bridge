<?php
/** SQL/process orchestration only; included by lease-sql.php. No autorun. */
declare(strict_types=1);
function secb_s_read(string $path):array {
    if(is_link($path)||!is_file($path)||fileowner($path)!==posix_geteuid()||(fileperms($path)&0777)!==0600||filesize($path)>65536)throw new RuntimeException('invalid_evidence_file');
    return secb_s_decode(file_get_contents($path));
}
function secb_s_publish(string $path,array $data):void {
    // Publish only complete ready/result JSON; consumers must never see an empty x-created file.
    $tmp=$path.'.writing';if(file_exists($path)||is_link($path)||file_exists($tmp)||is_link($tmp))throw new RuntimeException('evidence_publication_collision');
    secb_accept_json($tmp,$data,true);
    if(file_exists($path)||is_link($path)||!rename($tmp,$path))throw new RuntimeException('evidence_publication_failed');
}
function secb_s_scalar(string $sql,bool $nullable=false):mixed {
    global $wpdb;$v=$wpdb->get_var($sql);$error=$wpdb->last_error;
    if($error!==''||$v===false||($v===null&&!$nullable))throw new RuntimeException('lock_or_identity_query_failed');return $v;
}
function secb_s_connection():int {return secb_s_id(secb_s_scalar('SELECT CONNECTION_ID()'));}
function secb_s_owner(string $lock):?int {
    global $wpdb;$v=secb_s_scalar($wpdb->prepare('SELECT IS_USED_LOCK(%s)',$lock),true);return $v===null?null:secb_s_id($v);
}
function secb_s_release_owned(string $lock,int $connection):int {
    global $wpdb;
    if(secb_s_connection()!==$connection||secb_s_owner($lock)!==$connection)throw new RuntimeException('cannot_release_unowned_lock');
    $v=secb_s_scalar($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
    if((string)$v!=='1')throw new RuntimeException('owned_lock_release_failed');return 1;
}
function secb_s_probe(string $lock,array $workers,int $connection):array {
    global $wpdb;
    if(secb_s_connection()!==$connection||in_array($connection,$workers,true)||secb_s_owner($lock)!==null)throw new RuntimeException('probe_lock_not_free');
    $rows=secb_accept_rows('SELECT ID FROM information_schema.PROCESSLIST WHERE ID IN ('.implode(',',array_map('secb_s_id',$workers)).')');
    $open=array_map(fn($r)=>secb_s_id($r['ID']??null),$rows);sort($open);$expected=$workers;sort($expected);
    if($open!==$expected)throw new RuntimeException('worker_connections_not_open_for_probe');
    $got=secb_s_scalar($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock));if((string)$got!=='1')throw new RuntimeException('independent_probe_acquire_failed');
    $released=secb_s_release_owned($lock,$connection);$owner=secb_s_owner($lock);if($owner!==null)throw new RuntimeException('independent_probe_release_unconfirmed');
    return ['open_connections'=>$open,'connection'=>$connection,'owner_before'=>null,'acquired'=>1,'released'=>$released,'owner_after'=>$owner];
}
function secb_s_touch(string $path):void {
    $h=fopen($path,'x');if(!$h)throw new RuntimeException('barrier_collision');chmod($path,0600);fclose($h);
}
function secb_s_outputs(string $gate):void {
    foreach(['a','b'] as $label)foreach(['stdout','stderr'] as $stream){$f=$gate.'/'.$stream.'-'.$label.'.txt';clearstatcache(true,$f);if(is_file($f)&&filesize($f)>1048576)throw new RuntimeException('worker_output_limit');}
}
function secb_s_wait(array $paths,array &$children,float $deadline,string $gate):void {
    do {
        foreach($children as &$p)if(!secb_s_process_poll($p))throw new RuntimeException('worker_exited_before_open_connection_verification');unset($p);
        secb_s_outputs($gate);
        if(microtime(true)>$deadline)throw new RuntimeException('barrier_or_result_timeout');
        $all=true;foreach($paths as $path){clearstatcache(true,$path);$all=$all&&is_file($path);}if($all)return;usleep(10000);
    }while(true);
}
function secb_s_race(array $state,string $run,string $case):array {
    global $wpdb;secb_s_expected($case,'a');$kind=secb_accept_case($case);
    if((int)secb_accept_rows('SELECT COUNT(*) n FROM `'.$state['tables'][1].'`')[0]['n']!==0)throw new RuntimeException('reset_owned_clone_leases_first');
    $gate=$run.'/'.$case;if(file_exists($gate)||!mkdir($gate,0700))throw new RuntimeException('preserve_existing_case_evidence');
    $lock=secb_accept_in_fixture($state,fn()=>(new \SiteEngine\CallBridge\DB\NumberLeaseRepository())->allocationLockName());
    $super=secb_s_connection();$held=false;$children=[];$summary=['case'=>$case,'pass'=>false,'reason'=>'incomplete'];$e=[];
    try {
        if(secb_s_owner($lock)!==null)throw new RuntimeException('fixture_lock_collision');
        $sup=['acquired'=>0,'released'=>0];
        if($kind==='lock-timeout'){
            if((string)secb_s_scalar($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!=='1')throw new RuntimeException('supervisor_lock_acquire_failed');
            $held=true;$sup['acquired']=1;if(secb_s_owner($lock)!==$super)throw new RuntimeException('supervisor_lock_ownership_failed');
        }
        foreach(['a','b'] as $label){
            $out=$gate.'/stdout-'.$label.'.txt';$err=$gate.'/stderr-'.$label.'.txt';foreach([$out,$err] as $f){$h=fopen($f,'x');if(!$h)throw new RuntimeException('evidence_collision');chmod($f,0600);fclose($h);}
            $ctx=$GLOBALS['secb_standalone_context'];
            $p=proc_open([PHP_BINARY,'-d','auto_prepend_file=','-d','auto_append_file=','-d','opcache.enable_cli=0',__DIR__.'/runner.php','worker',$run,$ctx['site'],$ctx['tooling_hash'],$case,$label,'--execute','--audit-pin='.$ctx['audit_pin']],[0=>['file','/dev/null','r'],1=>['file',$out,'a'],2=>['file',$err,'a']],$pipes,$run);
            $children[$label]=secb_s_process_new($p);
        }
        secb_s_wait([$gate.'/ready-a.json',$gate.'/ready-b.json'],$children,microtime(true)+10,$gate);
        $ready=[];foreach(['a','b'] as $label)$ready[$label]=secb_s_read($gate.'/ready-'.$label.'.json');
        $connections=[];foreach(['a','b'] as $label){foreach(secb_s_expected($case,$label) as $k=>$v)if(($ready[$label][$k]??null)!==$v)throw new RuntimeException('ready_identity_mismatch');$connections[$label]=secb_s_id($ready[$label]['connection']??null);}
        if($connections['a']===$connections['b']||in_array($super,$connections,true))throw new RuntimeException('connections_not_independent');
        $deadline=microtime(true)+8;secb_s_touch($gate.'/go');
        secb_s_wait([$gate.'/result-a.json',$gate.'/result-b.json'],$children,$deadline,$gate);
        $results=[];foreach(['a','b'] as $label)$results[$label]=secb_s_read($gate.'/result-'.$label.'.json');
        $origins=secb_standalone_check_workers($ready,$results,$GLOBALS['secb_standalone_context']['root']);
        // Check for leaked application locks BEFORE allowing either SQL connection to close.
        if($held){$sup['released']=secb_s_release_owned($lock,$super);$held=false;}
        $probe=secb_s_probe($lock,array_values($connections),$super);
        secb_s_touch($gate.'/finish');
        do {
            secb_s_outputs($gate);$running=false;foreach($children as &$p)$running=secb_s_process_poll($p)||$running;unset($p);
            if(!$running)break;if(microtime(true)>$deadline){foreach($children as &$p)$p['timed_out']=true;unset($p);throw new RuntimeException('worker_completion_timeout');}usleep(10000);
        }while(true);
        secb_s_outputs($gate);$workers=[];foreach(['a','b'] as $label)$workers[$label]=['ready'=>$ready[$label],'result'=>$results[$label],'exit'=>secb_s_process_close($children[$label])];
        $leases=secb_accept_rows('SELECT * FROM `'.$state['tables'][1].'` ORDER BY id');
        $fixtures=secb_accept_rows('SELECT * FROM `'.$state['tables'][0].'` ORDER BY id');
        $clock=secb_s_scalar('SELECT UTC_TIMESTAMP()');if(secb_s_connection()!==$super)throw new RuntimeException('supervisor_connection_changed');
        $e=['candidate_origins'=>$origins,'complete'=>true,'case'=>$case,'now'=>$clock,'supervisor_connection'=>$super,'lock_name'=>$lock,'workers'=>$workers,'probe'=>$probe,'supervisor_lock'=>$sup,'final_lock_owner'=>secb_s_owner($lock),'leases'=>$leases,'fixtures'=>$fixtures,'fixture_ids'=>$state['ids']];
        if(microtime(true)>$deadline)throw new RuntimeException('race_finalization_timeout');
        $summary=secb_s_verdict($e);
    }catch(Throwable $error){$summary=['case'=>$case,'pass'=>false,'reason'=>$error->getMessage()];}
    finally {
        foreach($children as &$p)try{secb_s_process_abort($p);}catch(Throwable $error){$summary=['case'=>$case,'pass'=>false,'reason'=>'worker_cleanup_failed:'.$error->getMessage()];}unset($p);
        if($held)try{secb_s_release_owned($lock,$super);$held=false;}catch(Throwable $error){$summary=['case'=>$case,'pass'=>false,'reason'=>'supervisor_release_unconfirmed'];}
        try{if(secb_s_connection()===$super&&secb_s_owner($lock)===$super){$summary=['case'=>$case,'pass'=>false,'reason'=>'supervisor_lock_retained_before_cleanup'];secb_s_release_owned($lock,$super);}}catch(Throwable $error){$summary=['case'=>$case,'pass'=>false,'reason'=>'supervisor_final_release_unconfirmed'];}
    }
    // No successful verdict is published before process/release/cleanup checks.
    $records=[];foreach($children as $label=>$p)$records[$label]=array_diff_key($p,['handle'=>true]);
    secb_accept_json($gate.'/evidence.json',['envelope'=>$e,'processes'=>$records,'summary'=>$summary],true);
    secb_accept_json($gate.'/summary.json',$summary,true);return $summary;
}

function secb_s_no_inflight_workers(string $run):void {
    $dirs=glob($run.'/*',GLOB_ONLYDIR);if($dirs===false||count($dirs)>64)throw new RuntimeException('case_inventory_incomplete');
    foreach($dirs as $dir){if(!preg_match('/^(last-slot|same-session|lock-timeout)-[0-9]{2}$/D',basename($dir)))continue;
        $record=secb_s_read($dir.'/evidence.json');$processes=$record['processes']??null;
        if(!is_array($processes)||array_keys($processes)!==['a','b'])throw new RuntimeException('worker_cessation_evidence_missing');
        foreach($processes as $p)if(($p['closed']??null)!==true||($p['running']??null)!==false)throw new RuntimeException('worker_cessation_unconfirmed');
    }
}
