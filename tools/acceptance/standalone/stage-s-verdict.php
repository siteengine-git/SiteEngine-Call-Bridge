<?php
/** Pure acceptance verdict. No WordPress/SQL/filesystem operations. */
declare(strict_types=1);
function secb_s_require(bool $ok,string $why):void { if(!$ok)throw new RuntimeException($why); }
function secb_s_decode(string $json):array {
    $value=json_decode($json,true,64,JSON_THROW_ON_ERROR);secb_s_require(is_array($value)&&!array_is_list($value),'malformed_evidence_object');return $value;
}
function secb_s_id(mixed $v):int {
    secb_s_require(is_int($v)||(is_string($v)&&preg_match('/^[1-9][0-9]*$/D',$v)===1),'invalid_positive_id');
    secb_s_require(filter_var($v,FILTER_VALIDATE_INT)!==false&&(int)$v>0,'invalid_positive_id');return (int)$v;
}
function secb_s_time(mixed $v):int {
    secb_s_require(is_string($v),'missing_time');$t=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$v,new DateTimeZone('UTC'));
    secb_s_require($t!==false&&$t->format('Y-m-d H:i:s')===$v,'invalid_time');return $t->getTimestamp();
}
function secb_s_expected(string $case,string $label):array {
    secb_s_require(preg_match('/^(last-slot|same-session|lock-timeout)-[0-9]{2}$/D',$case,$m)===1&&in_array($label,['a','b'],true),'invalid_case');
    return ['case'=>$case,'label'=>$label,'session'=>'cbaccept-20261027-01-'.$case.($m[1]==='same-session'?'':'-'.$label),'source'=>'cbaccept_20261027_'.($m[1]==='same-session'&&$label==='b'?'beta':'alpha')];
}
function secb_s_verdict(array $e):array {
    try {
        secb_s_require(($e['complete']??null)===true,'incomplete_evidence');
        $case=$e['case']??'';
        secb_s_expected($case,'a');$kind=str_starts_with($case,'lock-timeout')?'lock-timeout':(str_starts_with($case,'same-session')?'same-session':'last-slot');
        $now=secb_s_time($e['now']??null);$super=secb_s_id($e['supervisor_connection']??null);
        secb_s_require(is_string($e['lock_name']??null)&&preg_match('/^secb_alloc_[a-f0-9]{40}$/D',$e['lock_name'])===1,'invalid_lock');
        secb_s_require(array_keys($e['workers']??[])===['a','b'],'missing_workers');$connections=[];$wins=[];
        foreach(['a','b'] as $label){
            $w=$e['workers'][$label];$want=secb_s_expected($case,$label);
            secb_s_require(is_array($w)&&is_array($w['ready']??null)&&is_array($w['result']??null),'missing_worker_evidence');
            foreach(['ready','result'] as $part)foreach($want as $key=>$value)secb_s_require(($w[$part][$key]??null)===$value,'worker_identity_mismatch');
            $id=secb_s_id($w['ready']['connection']??null);secb_s_require($id==secb_s_id($w['result']['connection']??null)&&$id==secb_s_id($w['result']['connection_after']??null)&&$id!==$super,'connection_identity_mismatch');$connections[$label]=$id;
            secb_s_require(($w['exit']['closed']??null)===true&&($w['exit']['running']??null)===false&&($w['exit']['exit_code']??null)===0&&($w['exit']['terminated']??null)===false&&($w['exit']['timed_out']??null)===false&&($w['exit']['signaled']??null)===false,'worker_exit_failed_or_unknown');
            $lock=$w['result']['lock']??null;
            secb_s_require(is_array($lock)&&($lock['name']??null)===$e['lock_name']&&($lock['checked_while_open']??null)===true&&array_key_exists('owner_after',$lock),'missing_lock_evidence');
            $owner=$lock['owner_after'];if($owner!==null)$owner=secb_s_id($owner);secb_s_require($owner!==$id,'worker_lock_leak');
            secb_s_require(is_numeric($w['result']['elapsed']??null)&&is_finite((float)$w['result']['elapsed'])&&$w['result']['elapsed']>=0&&$w['result']['elapsed']<=8,'invalid_worker_duration');
            if($kind==='lock-timeout')secb_s_require($owner===$super&&$w['result']['elapsed']>=1.8,'timeout_not_demonstrated');
            secb_s_require(array_key_exists('lease',$w['result']),'missing_lease');
            if($w['result']['lease']!==null){secb_s_require(is_array($w['result']['lease']),'malformed_lease');$wins[]=[$label,$w['result']['lease']];}
        }
        secb_s_require($connections['a']!==$connections['b'],'connections_not_independent');
        foreach($e['workers'] as $w){$owner=$w['result']['lock']['owner_after'];secb_s_require($owner===null||in_array((int)$owner,array_merge(array_values($connections),[$super]),true),'unexpected_lock_owner');}
        $probe=$e['probe']??[];$open=$probe['open_connections']??[];sort($open);$ids=array_values($connections);sort($ids);
        secb_s_require($open===$ids&&($probe['connection']??null)===$super&&($probe['acquired']??null)===1&&($probe['released']??null)===1&&array_key_exists('owner_before',$probe)&&$probe['owner_before']===null&&array_key_exists('owner_after',$probe)&&$probe['owner_after']===null,'independent_open_connection_probe_failed');
        secb_s_require(($e['supervisor_lock']['acquired']??null)===($kind==='lock-timeout'?1:0)&&($e['supervisor_lock']['released']??null)===($kind==='lock-timeout'?1:0),'supervisor_lock_failure');
        secb_s_require(array_key_exists('final_lock_owner',$e)&&$e['final_lock_owner']===null,'final_lock_not_free');
        secb_s_require(is_array($e['leases']??null)&&array_is_list($e['leases'])&&is_array($e['fixtures']??null)&&array_is_list($e['fixtures'])&&count($e['fixtures'])===5,'missing_persisted_state');
        $fixtures=[];$phones=[];
        foreach($e['fixtures'] as $i=>$f){
            $id=secb_s_id($f['id']??null);$n=$i+1;$src=$n===1?'alpha':($n===2?'beta':'pool');$pool=$n===1?'last':($n===2?'beta':($n===5?'small':'large'));
            secb_s_require(!isset($fixtures[$id])&&($f['phone_number']??null)==='+120255501'.sprintf('%02d',$n)&&($f['source']??null)==='cbaccept_20261027_'.$src&&($f['pool_group']??null)==='cbaccept_'.$pool&&($f['notes']??null)==='cbaccept-20261027-01'&&($f['display_label']??null)==='cbaccept-20261027-01-'.$n&&($f['forward_to']??null)==='+12025550190'&&(string)($f['record_calls']??'')==='0'&&(string)($f['is_active']??'')==='1','fixture_membership_mismatch');
            secb_s_require(!isset($phones[$f['phone_number']]),'duplicate_fixture_phone');$phones[$f['phone_number']]=true;$fixtures[$id]=$f;
        }
        secb_s_require(is_array($e['fixture_ids']??null)&&array_map('secb_s_id',$e['fixture_ids'])===array_keys($fixtures),'fixture_id_journal_mismatch');
        $active=0;$seenIds=[];$seenNumbers=[];$seenSessions=[];
        foreach($e['leases'] as $row){
            secb_s_require(is_array($row),'malformed_persisted_row');$id=secb_s_id($row['id']??null);$number=secb_s_id($row['tracking_number_id']??null);
            secb_s_require(isset($fixtures[$number])&&!isset($seenIds[$id])&&($row['tracking_number']??null)===$fixtures[$number]['phone_number']&&($row['source']??null)===$fixtures[$number]['source'],'persisted_fixture_mismatch');$seenIds[$id]=true;
            $expires=secb_s_time($row['expires_at']??null);secb_s_time($row['leased_at']??null);
            if(($row['status']??null)==='active'&&$expires>$now){$active++;secb_s_require(!isset($seenNumbers[$number])&&!isset($seenSessions[$row['session_id']??'']),'duplicate_ownership');$seenNumbers[$number]=true;$seenSessions[$row['session_id']??'']=true;}
        }
        if($kind==='lock-timeout')secb_s_require(count($wins)===0&&count($e['leases'])===0&&$active===0,'timeout_persisted_allocation');
        else {
            secb_s_require(count($wins)===1&&count($e['leases'])===1&&$active===1,'winner_cardinality_or_expiry');
            [$label,$winner]=$wins[0];$row=$e['leases'][0];$want=secb_s_expected($case,$label);
            foreach(['id','tracking_number_id'] as $k)secb_s_require(secb_s_id($winner[$k]??null)===secb_s_id($row[$k]??null),'winner_id_mismatch');
            foreach(['session_id','source','tracking_number','expires_at','status','leased_at'] as $k)secb_s_require(is_string($winner[$k]??null)&&$winner[$k]===($row[$k]??null),'winner_persisted_mismatch');
            secb_s_require($row['session_id']===$want['session']&&$row['source']===$want['source']&&$row['status']==='active','winner_request_mismatch');
        }
        return ['pass'=>true,'case'=>$case,'winners'=>count($wins),'lease_rows'=>count($e['leases']),'active_rows'=>$active,'distinct_connections'=>true,'identity_matches'=>true,'process_and_lock_checks'=>true];
    }catch(Throwable $error){return ['pass'=>false,'case'=>is_string($e['case']??null)?$e['case']:null,'reason'=>$error->getMessage()];}
}
