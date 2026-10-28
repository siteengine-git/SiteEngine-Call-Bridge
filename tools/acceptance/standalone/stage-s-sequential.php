<?php
/** Finite clone-only follow-ups. Requires validated state and operation.lock. */
declare(strict_types=1);
function secb_s_seq_assert(bool $ok,string $name,array &$checks):void {if(!$ok)throw new RuntimeException('sequential_assertion:'.$name);$checks[]=$name;}
function secb_s_seq_fixture_rows(array $state):array {
    $rows=secb_accept_rows('SELECT * FROM `'.$state['tables'][0].'` ORDER BY id');
    if(count($rows)!==5||count($state['ids'])!==5)throw new RuntimeException('five_owned_fixture_rows_required');
    foreach($rows as $i=>$row){if(secb_s_id($row['id']??null)!==secb_s_id($state['ids'][$i]))throw new RuntimeException('fixture_id_conflict');foreach(['phone_number','forward_to','source','display_label','notes'] as $k)if(($row[$k]??null)!==$state['rows'][$i][$k])throw new RuntimeException('fixture_member_conflict');if((string)$row['record_calls']!=='0')throw new RuntimeException('fixture_recording_conflict');}return $rows;
}
function secb_s_seq_lease(array $state,int $id):array {
    $rows=secb_accept_rows('SELECT * FROM `'.$state['tables'][1].'` WHERE id='.$id);
    if(count($rows)!==1)throw new RuntimeException('owned_lease_missing');$row=$rows[0];
    if(!preg_match('/^cbaccept-20261027-01-seq-(renewal|expiry|pool|source|deactivation|sticky)-[a-z0-9]+$/D',$row['session_id']))throw new RuntimeException('lease_session_not_owned');
    $fixtures=secb_s_seq_fixture_rows($state);$byId=[];foreach($fixtures as $f)$byId[(int)$f['id']]=$f;
    $number=$byId[secb_s_id($row['tracking_number_id']??null)]??null;
    if(!$number||$row['tracking_number']!==$number['phone_number']||$row['source']!==$number['source'])throw new RuntimeException('lease_fixture_identity_mismatch');return $row;
}
function secb_s_seq_expire(array $state,array $lease):void {
    $id=secb_s_id($lease['id']??null);$current=secb_s_seq_lease($state,$id);if($current!==$lease)throw new RuntimeException('lease_changed_before_expiry');
    if(secb_accept_query('UPDATE `'.$state['tables'][1].'` SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id='.$id)!==1)throw new RuntimeException('owned_expiry_update_failed');
}
function secb_s_seq_number_update(array $state,int $index,array $changes):void {
    global $wpdb;$rows=secb_s_seq_fixture_rows($state);$row=$rows[$index];foreach($changes as $k=>$v)if(!in_array($k,['is_active','pool_group'],true))throw new RuntimeException('unsupported_clone_edit');
    $where=['id'=>(int)$row['id'],'phone_number'=>$row['phone_number'],'source'=>$row['source'],'notes'=>$row['notes'],'pool_group'=>$row['pool_group'],'is_active'=>$row['is_active']];
    $n=$wpdb->update($state['tables'][0],$changes,$where);$error=$wpdb->last_error;if($error!==''||$n!==1)throw new RuntimeException('owned_number_edit_failed');
    $after=secb_s_seq_fixture_rows($state)[$index];foreach($changes as $k=>$v)if((string)$after[$k]!== (string)$v)throw new RuntimeException('owned_number_edit_readback_failed');
}
function secb_s_seq_acquire(array $state,string $source,string $sid,bool $alternate,array &$evidence):?array {
    global $wpdb;$original=$wpdb;$prefix=$original->prefix;$peer=null;
    try {
        $parent=secb_s_connection();$peer=new \wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$peer->suppress_errors(true);$wpdb=$peer;
        secb_accept_query('SET SESSION max_statement_time=5');$peerId=secb_s_connection();if($peerId===$parent)throw new RuntimeException('sequential_connection_not_independent');
        $wpdb=$alternate?$peer:$original;
        $connection=secb_s_connection();$lease=secb_accept_in_fixture($state,fn()=>(new \SiteEngine\CallBridge\DB\NumberLeaseRepository())->acquire($source,$sid));
        $lock=secb_accept_in_fixture($state,fn()=>(new \SiteEngine\CallBridge\DB\NumberLeaseRepository())->allocationLockName());
        if(secb_s_connection()!==$connection||secb_s_owner($lock)!==null)throw new RuntimeException('sequential_application_lock_or_connection_failed');
        $wpdb=$alternate?$original:$peer;$observer=secb_s_connection();$probe=secb_s_probe($lock,[$connection],$observer);
        $evidence[]=['allocation_connection'=>$connection,'probe'=>$probe,'alternate'=>$alternate];
        $wpdb=$original;
        if($lease!==null){$row=secb_s_seq_lease($state,secb_s_id($lease['id']??null));foreach(['id','session_id','source','tracking_number_id','tracking_number','expires_at','leased_at','status'] as $k)if((string)($lease[$k]??'')!==(string)($row[$k]??''))throw new RuntimeException('sequential_returned_persisted_mismatch');
            if($row['session_id']!==$sid||$row['source']!==$source||$row['status']!=='active'||secb_s_time($row['expires_at'])<=secb_s_time(secb_s_scalar('SELECT UTC_TIMESTAMP()')))throw new RuntimeException('sequential_winner_expired_or_wrong_request');}
        return $lease;
    } finally {$wpdb=$original;$wpdb->prefix=$prefix;if($peer)$peer->close();}
}
function secb_s_sequential(array $state,string $run,string $case):array {
    global $wpdb;
    if(!in_array($case,['renewal','expiry','pool','source','deactivation','sticky'],true))throw new RuntimeException('unknown_sequential_case');
    if((int)secb_accept_rows('SELECT COUNT(*) n FROM `'.$state['tables'][1].'`')[0]['n']!==0)throw new RuntimeException('reset_owned_clone_leases_first');
    $rows=secb_s_seq_fixture_rows($state);foreach($rows as $i=>$row)foreach($state['rows'][$i] as $k=>$v)if((string)$row[$k]!== (string)$v)throw new RuntimeException('sequential_initial_fixture_drift');
    $gate=$run.'/sequential-'.$case;if(file_exists($gate)||!mkdir($gate,0700))throw new RuntimeException('preserve_sequential_evidence');
    $checks=[];$locks=[];$start=microtime(true);$summary=['pass'=>false,'case'=>$case];
    $sid='cbaccept-20261027-01-seq-'.$case.'-';$alpha='cbaccept_20261027_alpha';$beta='cbaccept_20261027_beta';$pool='cbaccept_20261027_pool';
    $acquire=function($source,$session,$alternate=false)use($state,&$locks,$start){if(microtime(true)-$start>30)throw new RuntimeException('sequential_deadline');return secb_s_seq_acquire($state,$source,$session,$alternate,$locks);};
    try {
        if($case==='pool'){
            $a=$acquire($pool,$sid.'a');$b=$acquire($pool,$sid.'b');$c=$acquire($pool,$sid.'c');
            secb_s_seq_assert($a!==null&&$b!==null&&$c===null&&(int)$a['tracking_number_id']!==(int)$b['tracking_number_id'],'largest_pool_two_distinct_then_null',$checks);
            foreach([$a,$b] as $row)secb_s_seq_assert(in_array((int)$row['tracking_number_id'],[(int)$state['ids'][2],(int)$state['ids'][3]],true),'no_small_pool_fallback',$checks);
            $a=$acquire($beta,$sid.'d');$b=$acquire($beta,$sid.'e');secb_s_seq_assert($a!==null&&(int)$a['tracking_number_id']===(int)$state['ids'][1]&&$b===null,'beta_no_alpha_borrow',$checks);
        } elseif($case==='sticky'){
            $session=null;for($i=0;$i<64;$i++){if(hexdec(substr(sha1($sid.$i),0,8))%2===1){$session=$sid.$i;break;}}if($session===null)throw new RuntimeException('finite_sticky_session_selection_failed');
            $a=$acquire($pool,$session);secb_s_seq_assert($a!==null&&(int)$a['tracking_number_id']===(int)$state['ids'][3],'selected_large_pool_reservation',$checks);
            secb_s_seq_number_update($state,2,['pool_group'=>'cbaccept_small']);
            $b=$acquire($pool,$session,true);secb_s_seq_assert($b!==null&&(int)$b['id']===(int)$a['id']&&(int)$b['tracking_number_id']===(int)$state['ids'][3],'valid_lease_sticky_after_pool_change',$checks);
            secb_s_seq_expire($state,$b);$c=$acquire($pool,$session,true);secb_s_seq_assert($c!==null&&in_array((int)$c['tracking_number_id'],[(int)$state['ids'][2],(int)$state['ids'][4]],true),'post_expiry_uses_current_largest_pool',$checks);
            secb_s_seq_number_update($state,2,['pool_group'=>'cbaccept_large']);
        }else{
            $a=$acquire($alpha,$sid.'a');secb_s_seq_assert($a!==null&&(int)$a['tracking_number_id']===(int)$state['ids'][0],'initial_owned_alpha_lease',$checks);
            if($case==='renewal'){
                $id=(int)$a['id'];secb_s_seq_lease($state,$id);secb_s_seq_assert(secb_accept_query('UPDATE `'.$state['tables'][1].'` SET expires_at=UTC_TIMESTAMP()+INTERVAL 5 SECOND WHERE id='.$id)===1,'owned_short_expiry_prepared',$checks);
                $before=secb_s_seq_lease($state,$id);$b=$acquire($alpha,$sid.'a',true);
                secb_s_seq_assert($b!==null&&(int)$b['id']===$id&&$b['expires_at']>$before['expires_at'],'independent_connection_persisted_renewal',$checks);
                secb_s_seq_assert((int)secb_accept_rows('SELECT COUNT(*) n FROM `'.$state['tables'][1].'`')[0]['n']===1,'renewal_no_insert',$checks);
            }elseif($case==='expiry'){
                secb_s_seq_expire($state,$a);$b=$acquire($alpha,$sid.'b',true);$old=secb_s_seq_lease($state,(int)$a['id']);
                secb_s_seq_assert($b!==null&&(int)$b['id']!==(int)$a['id']&&$old['status']==='expired'&&!empty($old['released_at']),'expired_old_row_released_and_reacquired',$checks);
            }elseif($case==='source'){
                $before=secb_s_seq_lease($state,(int)$a['id']);$b=$acquire($beta,$sid.'a',true);secb_s_seq_assert($b===null&&secb_s_seq_lease($state,(int)$a['id'])===$before,'source_rejection_preserves_exact_reservation',$checks);
                secb_s_seq_expire($state,$before);$c=$acquire($beta,$sid.'a',true);secb_s_seq_assert($c!==null&&(int)$c['tracking_number_id']===(int)$state['ids'][1],'source_transition_only_after_expiry',$checks);
            }elseif($case==='deactivation'){
                $before=secb_s_seq_lease($state,(int)$a['id']);secb_s_seq_number_update($state,0,['is_active'=>0]);$b=$acquire($alpha,$sid.'a',true);
                secb_s_seq_assert($b===null&&secb_s_seq_lease($state,(int)$a['id'])===$before,'deactivation_rejects_without_release_or_extension',$checks);secb_s_seq_number_update($state,0,['is_active'=>1]);
            }
        }
        foreach(secb_accept_rows('SELECT * FROM `'.$state['tables'][1].'`') as $row)secb_s_seq_lease($state,secb_s_id($row['id']));
        $duplicates=secb_accept_rows('SELECT tracking_number_id FROM `'.$state['tables'][1]."` WHERE status='active' AND expires_at>UTC_TIMESTAMP() GROUP BY tracking_number_id HAVING COUNT(*)>1");
        $sessions=secb_accept_rows('SELECT session_id FROM `'.$state['tables'][1]."` WHERE status='active' AND expires_at>UTC_TIMESTAMP() GROUP BY session_id HAVING COUNT(*)>1");
        secb_s_seq_assert($duplicates===[]&&$sessions===[],'no_duplicate_live_ownership',$checks);
        $rows=secb_s_seq_fixture_rows($state);foreach($rows as $i=>$row)foreach($state['rows'][$i] as $k=>$v)if((string)$row[$k]!== (string)$v)throw new RuntimeException('sequential_final_fixture_drift');
        secb_s_seq_assert(microtime(true)-$start<=30,'finite_sequential_completion',$checks);
        $summary=['pass'=>true,'case'=>$case,'assertions'=>count($checks),'checks'=>$checks,'lock_evidence'=>$locks,'native_when_executed'=>true];
    }catch(Throwable $error){$summary=['pass'=>false,'case'=>$case,'checks'=>$checks,'lock_evidence'=>$locks,'reason'=>$error->getMessage()];}
    secb_accept_json($gate.'/summary.json',$summary,true);return $summary;
}
