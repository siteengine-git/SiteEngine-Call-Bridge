<?php
/** PREPARED ONLY. Include through the shared WP runner; no automatic execution. */
declare(strict_types=1);
defined('ABSPATH') || exit;

function secb_accept_json(string $path,array $data,bool $new=false):void {
    if(file_exists($path)&&(is_link($path)||!is_file($path)||(fileperms($path)&0777)!==0600||fileowner($path)!==posix_geteuid()))throw new RuntimeException('Refuse existing unowned/unrestricted evidence path');
    $handle=fopen($path,$new?'x':'c+');if(!$handle)throw new RuntimeException('Evidence file unavailable');
    try{if(!flock($handle,LOCK_EX))throw new RuntimeException('Evidence lock unavailable');$json=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";if(!ftruncate($handle,0)||fwrite($handle,$json)!==strlen($json)||!fflush($handle))throw new RuntimeException('Evidence write incomplete; stop and preserve owned state');chmod($path,0600);}finally{fclose($handle);}
}
function secb_accept_query(string $sql):int {
    global $wpdb;$r=$wpdb->query($sql);if($r===false)throw new RuntimeException('Acceptance SQL failed; preserve private evidence');return (int)$r;
}
function secb_accept_rows(string $sql):array {
    global $wpdb;$r=$wpdb->get_results($sql,ARRAY_A);if($wpdb->last_error!==''||!is_array($r))throw new RuntimeException('Acceptance read failed');return $r;
}
function secb_accept_snapshot(string $prefix):array {
    global $wpdb;$out=[];
    foreach(['tracking_numbers','attribution_sessions','number_leases','calls'] as $suffix){$table=$prefix.'se_cb_'.$suffix;$sum=secb_accept_rows("CHECKSUM TABLE `$table`")[0]['Checksum']??null;if($sum===null)throw new RuntimeException('Table checksum unavailable; do not claim unchanged data');$out[$suffix]=['rows'=>secb_accept_rows("SELECT COUNT(*) AS rows_count FROM `$table`")[0]['rows_count'],'checksum'=>$sum];}
    $out['option_hashes']=[];foreach(secb_accept_rows("SELECT option_name,option_value FROM `{$prefix}options` WHERE option_name LIKE 'se\\_cb\\_%'") as $r)$out['option_hashes'][$r['option_name']]=hash('sha256',$r['option_value']);
    $out['wp_config_hash']=hash_file('sha256',ABSPATH.'wp-config.php');
    $out['external_config_hash']=defined('SECB_CONFIG_FILE')&&is_readable(SECB_CONFIG_FILE)?hash_file('sha256',SECB_CONFIG_FILE):null;
    $event=wp_get_scheduled_event('se_cb_gc');$out['gc']=$event?['timestamp'=>$event->timestamp,'schedule'=>$event->schedule,'interval'=>$event->interval??null,'args_count'=>count($event->args)]:null;
    return $out;
}
function secb_accept_case(string $case):string {if(!preg_match('/^(last-slot|same-session|lock-timeout)(?:-[0-9]{2})?$/',$case,$m))throw new RuntimeException('Unknown case');return $m[1];}
function secb_accept_operation_lock(string $run){
    $path=$run.'/operation.lock';if(file_exists($path)&&(is_link($path)||(fileperms($path)&0777)!==0600||fileowner($path)!==posix_geteuid()))throw new RuntimeException('Operation-lock ownership conflict');
    $h=fopen($path,'c+');if(!$h)throw new RuntimeException('Operation lock unavailable');chmod($path,0600);if(!flock($h,LOCK_EX|LOCK_NB)){fclose($h);throw new RuntimeException('Another acceptance operation is running');}return $h;
}
function secb_accept_plan(string $run):array {
    global $wpdb;
    $expected=dirname(rtrim(ABSPATH,'/')).'/.secb-acceptance/cb-lease-dni-20261027-01';
    if($run!==$expected||realpath($run)!==$run||is_link($run)||!is_dir($run)||(fileperms($run)&0777)!==0700||fileowner($run)!==posix_geteuid())throw new RuntimeException('Require exact owner-created restricted run directory');
    if(!preg_match('/^[A-Za-z0-9_]+$/',$wpdb->prefix))throw new RuntimeException('Unsafe database prefix');
    if(hash_file('sha256',SECB_PATH.'RELEASE-MANIFEST.sha256')!=='4729dc2cd7cc15e32462817d4d71b53033bdff16824a60d640ad9e72b6e83b37')throw new RuntimeException('Pinned f763b3c package manifest required');
    foreach(file(SECB_PATH.'RELEASE-MANIFEST.sha256',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){if(!preg_match('~^([a-f0-9]{64})  \./([A-Za-z0-9_./-]+)$~',$line,$m)||str_contains($m[2],'..')||hash_file('sha256',SECB_PATH.$m[2])!==$m[1])throw new RuntimeException('Pinned package payload differs');}
    $prefix=$wpdb->prefix.'secb_accept_20261027_01_';if(strlen($prefix.'se_cb_tracking_numbers')>64)throw new RuntimeException('Fixture table name too long');
    $rows=[];foreach([[1,'alpha','last'],[2,'beta','beta'],[3,'pool','large'],[4,'pool','large'],[5,'pool','small']] as [$n,$source,$pool])$rows[]=['phone_number'=>'+120255501'.sprintf('%02d',$n),'forward_to'=>'+12025550190','source'=>'cbaccept_20261027_'.$source,'pool_group'=>'cbaccept_'.$pool,'display_label'=>'cbaccept-20261027-01-'.$n,'record_calls'=>0,'is_active'=>1,'notes'=>'cbaccept-20261027-01'];
    return ['run'=>'cb-lease-dni-20261027-01','root_prefix'=>$wpdb->prefix,'fixture_prefix'=>$prefix,'tables'=>[$prefix.'se_cb_tracking_numbers',$prefix.'se_cb_number_leases'],'rows'=>$rows,'marker'=>'cbaccept:20261027:01','source_files'=>__FILE__];
}
function secb_accept_own_tables(array $state):void {
    global $wpdb;
    foreach($state['tables'] as $table){$r=secb_accept_rows($wpdb->prepare('SELECT TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table));if(count($r)!==1||$r[0]['TABLE_COMMENT']!==$state['marker'])throw new RuntimeException('Fixture table ownership marker missing; do not delete or overwrite');}
}
function secb_accept_load(string $run,array $plan):array {
    $file=$run.'/sql-state.json';if(is_link($file)||!is_file($file)||(fileperms($file)&0777)!==0600||fileowner($file)!==posix_geteuid())throw new RuntimeException('Restricted owned SQL state required');
    $state=json_decode(file_get_contents($run.'/sql-state.json'),true,512,JSON_THROW_ON_ERROR);
    foreach(['run','root_prefix','fixture_prefix','tables','rows','marker','source_files'] as $key)if(($state[$key]??null)!==$plan[$key])throw new RuntimeException('Run ownership/state mismatch');return $state;
}
function secb_accept_in_fixture(array $state,callable $action){global $wpdb;$prefix=$wpdb->prefix;$wpdb->prefix=$state['fixture_prefix'];try{return $action();}finally{$wpdb->prefix=$prefix;}}

/** Worker is a separate WP-CLI process/SQL connection. Call only from supervisor. */
function secb_accept_worker(string $run,string $case,string $label,bool $write=false):void {
    global $wpdb;if(!$write)throw new RuntimeException('Worker writes require explicit reviewed authorization');
    secb_accept_query('SET SESSION max_statement_time=5'); // Alma MariaDB only; no global setting.
    $plan=secb_accept_plan($run);$state=secb_accept_load($run,$plan);secb_accept_own_tables($state);if($state['phase']!=='ready')throw new RuntimeException('SQL fixtures are not ready');
    $kind=secb_accept_case($case);if(!in_array($label,['a','b'],true))throw new RuntimeException('Unknown worker');
    $gate=$run.'/'.$case;$sid='cbaccept-20261027-01-'.$case.($kind==='same-session'?'':'-'.$label);$source='cbaccept_20261027_'.($kind==='same-session'&&$label==='b'?'beta':'alpha');
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()');if($connection<=0||$wpdb->last_error!=='')throw new RuntimeException('Cannot establish native SQL connection ID');secb_accept_json($gate.'/ready-'.$label.'.json',['connection'=>$connection],true);
    $deadline=microtime(true)+10;while(!file_exists($gate.'/go')){if(microtime(true)>$deadline)throw new RuntimeException('Barrier timeout');usleep(10000);}
    $start=microtime(true);$result=secb_accept_in_fixture($state,fn()=>(new \SiteEngine\CallBridge\DB\NumberLeaseRepository())->acquire($source,$sid));
    secb_accept_json($gate.'/result-'.$label.'.json',['connection'=>$connection,'session'=>$sid,'source'=>$source,'started'=>$start,'elapsed'=>microtime(true)-$start,'lease'=>$result],true);
}

/** All mutation modes default denied. This file alone never runs a test. */
function secb_accept_sql(string $mode,string $run,bool $write=false,?string $case=null):array {
    if(!$write)return secb_accept_sql_operation($mode,$run,false,$case);secb_accept_plan($run);$h=secb_accept_operation_lock($run);try{return secb_accept_sql_operation($mode,$run,true,$case);}finally{flock($h,LOCK_UN);fclose($h);}
}
function secb_accept_sql_operation(string $mode,string $run,bool $write=false,?string $case=null):array {
    global $wpdb;$plan=secb_accept_plan($run);$wpdb->hide_errors();
    if($mode==='plan')return $plan;
    if(!$write)throw new RuntimeException('No fixture writes: explicit post-review write=true required');
    secb_accept_query('SET SESSION max_statement_time=5');
    if($mode==='seed-clones'){
        if(file_exists($run.'/sql-state.json'))throw new RuntimeException('Existing run contents preserved; do not seed twice');
        foreach($plan['tables'] as $table)if(secb_accept_rows($wpdb->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table)))throw new RuntimeException('Fixture table collision');
        foreach($plan['rows'] as $row)if(secb_accept_rows($wpdb->prepare("SELECT id FROM `{$plan['root_prefix']}se_cb_tracking_numbers` WHERE phone_number=%s OR source=%s",$row['phone_number'],$row['source'])))throw new RuntimeException('Phone/source collision in real tables');
        foreach(array_unique(array_column($plan['rows'],'source')) as $source){$lock='secb_lease_'.substr(sha1($source),0,24);if((int)$wpdb->get_var($wpdb->prepare('SELECT IS_FREE_LOCK(%s)',$lock))!==1)throw new RuntimeException('Server-wide fixture source lock collision');}
        $state=$plan+['before'=>secb_accept_snapshot($plan['root_prefix']),'ids'=>[],'phase'=>'creating'];secb_accept_json($run.'/sql-state.json',$state,true);
        foreach(['tracking_numbers','number_leases'] as $suffix){$target=$plan['fixture_prefix'].'se_cb_'.$suffix;$original=$plan['root_prefix'].'se_cb_'.$suffix;secb_accept_query("CREATE TABLE `$target` LIKE `$original`");secb_accept_query($wpdb->prepare("ALTER TABLE `$target` COMMENT=%s",$plan['marker']));}
        secb_accept_own_tables($state);
        secb_accept_in_fixture($state,function()use(&$state){foreach($state['rows'] as $row){$id=(new \SiteEngine\CallBridge\DB\TrackingNumberRepository())->insert($row);if(!$id)throw new RuntimeException('Fixture insert failed');$state['ids'][]=$id;}});
        $state['phase']='ready';secb_accept_json($run.'/sql-state.json',$state);return ['phase'=>'ready','number_rows'=>count($state['ids']),'tables'=>$state['tables']];
    }
    $state=secb_accept_load($run,$plan);secb_accept_own_tables($state);
    if($mode==='reset-clone-leases'){secb_accept_query('DELETE FROM `'.$state['tables'][1].'`');return ['reset'=>'owned clone leases only; sequence unchanged'];}
    if($mode==='race'){
        $kind=secb_accept_case((string)$case);
        if((int)secb_accept_rows('SELECT COUNT(*) n FROM `'.$state['tables'][1].'`')[0]['n']!==0)throw new RuntimeException('Reset owned clone leases explicitly before race');
        $gate=$run.'/'.$case;if(file_exists($gate)||!mkdir($gate,0700))throw new RuntimeException('Preserve existing case evidence');
        $lock='secb_lease_'.substr(sha1('cbaccept_20261027_alpha'),0,24);$held=false;$children=[];
        try{
            if((int)$wpdb->get_var($wpdb->prepare('SELECT IS_FREE_LOCK(%s)',$lock))!==1)throw new RuntimeException('Source lock collision');
            if($kind==='lock-timeout'){$held=(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))===1;if(!$held)throw new RuntimeException('Cannot hold test lock');}
            foreach(['a','b'] as $label){
                $out=$gate.'/stdout-'.$label.'.txt';$err=$gate.'/stderr-'.$label.'.txt';foreach([$out,$err] as $f){$h=fopen($f,'x');if(!$h)throw new RuntimeException('Evidence collision');fclose($h);chmod($f,0600);}
                $code='require '.var_export(__FILE__,true).'; secb_accept_worker('.var_export($run,true).','.var_export($case,true).','.var_export($label,true).',true);';
                $p=proc_open(['/usr/local/lsws/lsphp83/bin/php','/usr/bin/wp','--path='.rtrim(ABSPATH,'/'),'eval',$code],[0=>['file','/dev/null','r'],1=>['file',$out,'a'],2=>['file',$err,'a']],$pipes,$run);
                if(!is_resource($p))throw new RuntimeException('Worker start failed');$children[]=$p;
            }
            $deadline=microtime(true)+10;while(!file_exists($gate.'/ready-a.json')||!file_exists($gate.'/ready-b.json')){if(microtime(true)>$deadline)throw new RuntimeException('Worker readiness timeout; inspect private stderr');usleep(10000);}
            $a=json_decode(file_get_contents($gate.'/ready-a.json'),true);$b=json_decode(file_get_contents($gate.'/ready-b.json'),true);if($a['connection']===$b['connection'])throw new RuntimeException('Workers must have independent SQL connections');
            $go=fopen($gate.'/go','x');fclose($go);chmod($gate.'/go',0600);
            $deadline=microtime(true)+8;do{$running=false;foreach($children as $p)$running=proc_get_status($p)['running']||$running;if(!$running)break;if(microtime(true)>$deadline)throw new RuntimeException('Race execution timeout');usleep(10000);}while(true);
            $results=[];foreach(['a','b'] as $label){$file=$gate.'/result-'.$label.'.json';if(!is_file($file))throw new RuntimeException('Worker failed; inspect private stderr');$results[]=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);}
            $leases=secb_accept_rows('SELECT session_id,tracking_number_id,status,expires_at FROM `'.$state['tables'][1].'`');
            $duplicates=secb_accept_rows('SELECT tracking_number_id,COUNT(*) n FROM `'.$state['tables'][1]."` WHERE status='active' AND expires_at>UTC_TIMESTAMP() GROUP BY tracking_number_id HAVING COUNT(*)>1");
            $active=(int)secb_accept_rows('SELECT COUNT(*) n FROM `'.$state['tables'][1]."` WHERE status='active' AND expires_at>UTC_TIMESTAMP()")[0]['n'];
            $duplicateSessions=secb_accept_rows('SELECT session_id,COUNT(*) n FROM `'.$state['tables'][1]."` WHERE status='active' AND expires_at>UTC_TIMESTAMP() GROUP BY session_id HAVING COUNT(*)>1");
            $identity=true;foreach($results as $r)if($r['lease']!==null&&($r['lease']['source']!==$r['source']||$r['lease']['session_id']!==$r['session']))$identity=false;
            $won=count(array_filter($results,fn($r)=>$r['lease']!==null));$pass=$duplicates===[]&&$duplicateSessions===[]&&$identity&&($kind==='lock-timeout'?$won===0:($kind==='same-session'?$active===1:$won===1&&count($leases)===1));
            $summary=['case'=>$case,'pass'=>$pass,'distinct_connections'=>true,'identity_matches'=>$identity,'winners'=>$won,'lease_rows'=>count($leases),'active_rows'=>$active,'duplicate_number_rows'=>count($duplicates),'duplicate_session_rows'=>count($duplicateSessions)];secb_accept_json($gate.'/summary.json',$summary,true);return $summary;
        }finally{
            foreach($children as $p){if(proc_get_status($p)['running']){proc_terminate($p);$end=microtime(true)+1;while(proc_get_status($p)['running']&&microtime(true)<$end)usleep(10000);if(proc_get_status($p)['running'])proc_terminate($p,9);}proc_close($p);}if($held)$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        }
    }
    if($mode==='inspect')return ['number_rows'=>secb_accept_rows('SELECT COUNT(*) n FROM `'.$state['tables'][0].'`')[0]['n'],'lease_rows'=>secb_accept_rows('SELECT COUNT(*) n FROM `'.$state['tables'][1].'`')[0]['n'],'original_after'=>secb_accept_snapshot($state['root_prefix']),'original_before'=>$state['before']];
    if($mode==='cleanup-clones'){
        foreach(array_reverse($state['tables']) as $table)secb_accept_query("DROP TABLE `$table`");
        $after=secb_accept_snapshot($state['root_prefix']);secb_accept_json($run.'/sql-after.json',$after,true);$state['phase']='cleaned';secb_accept_json($run.'/sql-state.json',$state);return ['original_snapshot_equal'=>$after===$state['before'],'evidence_retained'=>true];
    }
    throw new RuntimeException('Unknown mode');
}
