<?php
/** Direct-child process tracking shared with local regression tests. */
declare(strict_types=1);
function secb_s_process_new($handle):array {
    if(!is_resource($handle))throw new RuntimeException('worker_start_failed');
    $s=proc_get_status($handle);$p=['handle'=>$handle,'pid'=>$s['pid'],'running'=>true,'exit_code'=>null,'signaled'=>false,'terminated'=>false,'timed_out'=>false,'closed'=>false];secb_s_process_observe($p,$s);return $p;
}
function secb_s_process_observe(array &$p,array $s):void {
    if(($s['pid']??null)!==$p['pid']||!is_bool($s['running']??null))throw new RuntimeException('process_identity_unknown');
    $p['running']=$s['running'];$p['signaled']=$p['signaled']||($s['signaled']??false)||($s['stopped']??false);
    if(!$s['running']&&is_int($s['exitcode']??null)&&$s['exitcode']>=0){
        if($p['exit_code']!==null&&$p['exit_code']!==$s['exitcode'])throw new RuntimeException('conflicting_exit_status');
        $p['exit_code']=$s['exitcode']; // Preserve first known status; never replace it with -1.
    }
}
function secb_s_process_poll(array &$p):bool {
    if($p['closed'])return false;secb_s_process_observe($p,proc_get_status($p['handle']));return $p['running'];
}
function secb_s_process_merge_close(array &$p,int $exit):void {
    if($exit>=0){if($p['exit_code']!==null&&$p['exit_code']!==$exit)throw new RuntimeException('conflicting_close_status');$p['exit_code']=$exit;}
    $p['closed']=true;$p['running']=false;
}
function secb_s_process_close(array &$p):array {
    if(!$p['closed']){if(secb_s_process_poll($p))throw new RuntimeException('closing_running_worker');$exit=proc_close($p['handle']);secb_s_process_merge_close($p,$exit);unset($p['handle']);}
    return array_diff_key($p,['handle'=>true]);
}
function secb_s_process_abort(array &$p):void {
    if($p['closed'])return;
    if(secb_s_process_poll($p)){
        $p['terminated']=true;proc_terminate($p['handle']);$end=microtime(true)+1;
        while(secb_s_process_poll($p)&&microtime(true)<$end)usleep(10000);
        if($p['running']){proc_terminate($p['handle'],9);$end=microtime(true)+1;while(secb_s_process_poll($p)&&microtime(true)<$end)usleep(10000);}
        if($p['running'])throw new RuntimeException('owned_worker_still_running');
    }
    secb_s_process_close($p);
}
