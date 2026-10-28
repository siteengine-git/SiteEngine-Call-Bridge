<?php
/** Actual repositories, fake wpdb/clock. NO SQL server or concurrency proof. */
namespace SiteEngine\CallBridge\DB {
    function time(): int { return $GLOBALS['lease_clock']; }
    function gmdate(string $format, ?int $at=null): string { return \gmdate($format,$at??time()); }
}
namespace {
define('DB_NAME','fixture_database');
define('ABSPATH',__DIR__.'/fixture/');define('ARRAY_A','ARRAY_A');
require dirname(__DIR__).'/src/Support/Config.php';
require dirname(__DIR__).'/src/DB/TrackingNumberRepository.php';
require dirname(__DIR__).'/src/DB/NumberLeaseRepository.php';
use SiteEngine\CallBridge\DB\NumberLeaseRepository as Leases;
final class LeaseState {
    public array $numbers=[],$leases=[],$locks=[],$events=[];
    public int $next=1;
}
class LeaseDb {
    public string $prefix='fixture_', $last_error='';public int $insert_id=0;
    public array $fail=[];public $duringUsedRead=null;public $afterUpdate=null;public $afterInsert=null;public bool $zeroInsert=false;public bool $zeroUpdate=false;
    public function __construct(public LeaseState $state,public string $connection){}
    public function prepare(string $sql,...$args): string {
        $i=0;return preg_replace_callback('/%[sd]/',function($m)use(&$i,$args){$v=$args[$i++];return $m[0]==='%d'?(string)(int)$v:"'".str_replace("'","''",$v)."'";},$sql);
    }
    private function failed(string $op): bool {
        $this->last_error='';if(!isset($this->fail[$op]))return false;
        $kind=$this->fail[$op];unset($this->fail[$op]);$this->last_error='synthetic '.$op.' failure';
        if($kind==='throw')throw new \RuntimeException($this->last_error);return true;
    }
    public function get_var(string $sql){
        if(preg_match("/SELECT GET_LOCK\('([^']+)',2\)/",$sql,$m)){
            $this->state->events[]=['get',$this->connection,$m[1]];
            if($this->failed('lock')||isset($this->state->locks[$m[1]]))return 0;
            $this->state->locks[$m[1]]=$this->connection;return 1;
        }
        if(preg_match("/SELECT RELEASE_LOCK\('([^']+)'\)/",$sql,$m)){
            $this->state->events[]=['release',$this->connection,$m[1]];
            if(($this->state->locks[$m[1]]??null)!==$this->connection)return 0;
            unset($this->state->locks[$m[1]]);return 1;
        }
        throw new \RuntimeException('Unmodelled scalar query');
    }
    public function query(string $sql){
        if(!str_starts_with($sql,'UPDATE fixture_se_cb_number_leases SET status='))throw new \RuntimeException('Unmodelled query');
        if($this->failed('expiry'))return false;$n=0;
        foreach($this->state->leases as &$l)if($l['status']==='active'&&$l['expires_at']<=gmdate('Y-m-d H:i:s',$GLOBALS['lease_clock'])){$l['status']='expired';$l['released_at']??=gmdate('Y-m-d H:i:s',$GLOBALS['lease_clock']);++$n;}
        return $n;
    }
    public function get_row(string $sql,$format){
        if(str_contains($sql,'fixture_se_cb_tracking_numbers')){
            if($this->failed('eligibility'))return null;
            if(!preg_match('/WHERE id=(\d+)/',$sql,$id))throw new \RuntimeException('Unmodelled eligibility query');
            return $this->state->numbers[(int)$id[1]]??null;
        }
        if($this->failed('session_unknown')){$this->last_error='';return false;}
        if($this->failed('session_read'))return null;
        if(!preg_match("/WHERE (session_id|tracking_number)='([^']+)'/",$sql,$m))throw new \RuntimeException('Unmodelled row query');
        $rows=array_filter($this->state->leases,fn($l)=>$l[$m[1]]===$m[2]&&$l['status']==='active'&&$l['expires_at']>gmdate('Y-m-d H:i:s',$GLOBALS['lease_clock']));
        usort($rows,fn($a,$b)=>$b['id']<=>$a['id']);return $rows[0]??null;
    }
    public function get_results(string $sql,$format){
        if($this->failed('number_unknown')){$this->last_error='';return null;}
        if($this->failed('number_read'))return null;
        if(!preg_match("/WHERE source='([^']+)' AND is_active=1 ORDER BY id ASC/",$sql,$m))throw new \RuntimeException('Unmodelled number query');
        $rows=array_values(array_filter($this->state->numbers,fn($n)=>$n['source']===$m[1]&&$n['is_active']===1));usort($rows,fn($a,$b)=>$a['id']<=>$b['id']);return $rows;
    }
    public function get_col(string $sql){
        $column=str_starts_with($sql,'SELECT session_id FROM')?'session_id':'tracking_number_id';
        if(!str_starts_with($sql,'SELECT '.$column.' FROM fixture_se_cb_number_leases'))throw new \RuntimeException('Unmodelled column query');
        if($column==='session_id' && $this->failed('sessions_read'))return [];
        // wpdb returns an empty column array on a failed result, with last_error set.
        if($column==='tracking_number_id' && $this->failed('used_read'))return [];
        if($this->duringUsedRead){$cb=$this->duringUsedRead;$this->duringUsedRead=null;$cb();}
        return array_column(array_filter($this->state->leases,fn($l)=>$l['status']==='active'&&$l['expires_at']>gmdate('Y-m-d H:i:s',$GLOBALS['lease_clock'])),$column);
    }
    public function insert($table,$data,$formats){if($this->failed('insert'))return false;if($this->zeroInsert)return 0;$data['id']=$this->state->next++;$this->insert_id=$data['id'];$this->state->leases[$data['id']]=$data;if($this->afterInsert){$cb=$this->afterInsert;$this->afterInsert=null;$cb();}return 1;}
    public function update($table,$data,$where,...$formats){
        if($this->failed('renewal'))return false;$id=$where['id'];
        if($this->zeroUpdate)return 0;
        foreach($where as $k=>$v)if($this->state->leases[$id][$k]!==$v)return 0;
        $changed=$this->state->leases[$id]!==array_merge($this->state->leases[$id],$data);
        $this->state->leases[$id]=array_merge($this->state->leases[$id],$data);
        if($this->afterUpdate){$cb=$this->afterUpdate;$this->afterUpdate=null;$cb();}
        return $changed?1:0;
    }
}
if(defined('SECB_TEST_LIBRARY')) return;
$results=[];
function check(bool $ok,string $name):void {global $results;$results[]=['ok'=>$ok,'name'=>$name];echo ($ok?'PASS ':'FAIL ').$name."\n";}
function setup():array {$GLOBALS['lease_clock']=1767225600;$s=new LeaseState();$a=new LeaseDb($s,'A');$b=new LeaseDb($s,'B');$GLOBALS['wpdb']=$a;return [$s,$a,$b];}
function number(LeaseState $s,int $id,string $source='fixture_alpha',?string $pool='fixture_pool'):void {$s->numbers[$id]=['id'=>$id,'phone_number'=>'+120255501'.sprintf('%02d',$id),'forward_to'=>'+12025550190','source'=>$source,'pool_group'=>$pool,'is_active'=>1];}
function acquire(LeaseDb $db,string $sid,string $source='fixture_alpha'):?array {$GLOBALS['wpdb']=$db;return (new Leases())->acquire($source,$sid);}

[$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-session-a');$q=acquire($b,'fixture-session-b');
check($r!==null&&$q===null&&count($s->leases)===1,'independent fake clients: last number exclusive; second session exhausted');
check($s->locks===[],'success and exhaustion release named locks');
[$s,$a,$b]=setup();number($s,1);$contender='not-run';$a->duringUsedRead=function()use($a,$b,&$contender){$contender=acquire($b,'fixture-contender');$GLOBALS['wpdb']=$a;};
$r=acquire($a,'fixture-owner');check($r!==null&&$contender===null&&count($s->leases)===1,'interleaved fake lock contender denied (not real SQL contention)');
check($s->locks===[],'interleaved owner releases lock');
[$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-renew');$GLOBALS['lease_clock']+=60;$renew=acquire($b,'fixture-renew');
check($renew['id']===$r['id']&&$renew['expires_at']>$r['expires_at']&&count($s->leases)===1,'renewal extends same lease without insertion');
$GLOBALS['lease_clock']+=1800;$new=acquire($a,'fixture-after-expiry');
check($s->leases[$r['id']]['status']==='expired'&&isset($s->leases[$r['id']]['released_at']),'expiry records expired status and release time');
check($new!==null&&$new['tracking_number_id']===$r['tracking_number_id'],'expired number becomes allocatable');
[$s,$a,$b]=setup();$r=acquire($a,'fixture-empty');check($r===null&&$s->locks===[],'empty pool safely returns null and releases lock');
[$s,$a,$b]=setup();number($s,1);number($s,2);number($s,3,'fixture_alpha','other_pool');
$r=acquire($a,'fixture-pool-a');$q=acquire($b,'fixture-pool-b');$x=acquire($a,'fixture-pool-c');
check($r&&$q&&$r['tracking_number_id']!==$q['tracking_number_id']&&$x===null,'largest source pool fills exclusively; no fallback to smaller group');
[$s,$a,$b]=setup();number($s,1,'fixture_alpha',null);number($s,2,'fixture_alpha',null);
check(acquire($a,'fixture-standalone-a')!==null&&acquire($b,'fixture-standalone-b')===null,'ungrouped standalone numbers are not merged');
[$s,$a,$b]=setup();number($s,1);number($s,2,'fixture_beta');
check(acquire($a,'fixture-alpha')!==null&&acquire($b,'fixture-beta','fixture_beta')['tracking_number_id']===2,'different sources select their own pool');
[$s,$a,$b]=setup();number($s,1);$s->numbers[1]['is_active']=0;
check(acquire($a,'fixture-inactive')===null,'deactivated number excluded from new allocation');
foreach(['lock','number_read','insert'] as $failure){[$s,$a,$b]=setup();number($s,1);$a->fail[$failure]='false';check(acquire($a,'fixture-failure-'.$failure)===null&&count($s->leases)===0&&$s->locks===[],$failure.' failure: no allocation or leaked lock');}
[$s,$a,$b]=setup();number($s,1);$s->locks[(new Leases())->allocationLockName()]='external';
check(acquire($a,'fixture-timeout')===null&&count($s->leases)===0,'modelled GET_LOCK timeout causes no write');
check(count($s->locks)===1,'timed-out client does not release another connection lock');
[$s,$a,$b]=setup();number($s,1);$a->fail['insert']='throw';try{acquire($a,'fixture-throw');check(false,'insertion exception propagates');}catch(\RuntimeException $e){check(true,'insertion exception propagates');}check($s->locks===[],'finally releases lock on thrown insertion');
[$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-expiry-error');$GLOBALS['lease_clock']+=1801;$a->fail['expiry']='false';
$GLOBALS['wpdb']=$a;check((new Leases())->expireStale()===false,'failed expiry write remains distinguishable from successful zero changes');
$n=acquire($b,'fixture-expiry-retry');check($n!==null&&$s->leases[$r['id']]['status']==='expired','next expiry pass recovers failed expiry write');

// Original failure contracts preserved; historical red results remain in verification documentation.
[$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-renew-fail');$GLOBALS['lease_clock']+=60;$a->fail['renewal']='false';$n=acquire($a,'fixture-renew-fail');
check($n===null,'DEFECT L1: failed renewal must not claim a new expiration');check($s->locks===[],'failed renewal still releases lock');
[$s,$a,$b]=setup();number($s,1);acquire($a,'fixture-used-a');$b->fail['used_read']='false';$n=acquire($b,'fixture-used-b');
check($n===null&&count($s->leases)===1,'DEFECT L2: failed used-number read must fail closed, not double allocate');check($s->locks===[],'used-number read failure still releases lock');
[$s,$a,$b]=setup();number($s,1);number($s,2,'fixture_beta');acquire($a,'fixture-source-change');$n=acquire($b,'fixture-source-change','fixture_beta');
check($n===null,'DEFECT L3: source change must not return an old-source lease as current');
[$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-deactivate');$s->numbers[1]['is_active']=0;$n=acquire($b,'fixture-deactivate');
check($n===null,'DEFECT L4: deactivated number must not be returned by renewal');
[$s,$a,$b]=setup();number($s,1);number($s,2);acquire($a,'fixture-session-read');$b->fail['session_read']='false';$n=acquire($b,'fixture-session-read');
check($n===null&&count($s->leases)===1,'DEFECT L5: failed existing-session read must not create a second lease');
[$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-pool-edit');number($s,2,'fixture_alpha','new_pool');number($s,3,'fixture_alpha','new_pool');$n=acquire($b,'fixture-pool-edit');
check($n['id']===$r['id'],'locked policy: existing lease stays sticky when largest pool changes');
$GLOBALS['lease_clock']+=1801;$n=acquire($a,'fixture-new-pool');check(in_array($n['tracking_number_id'],[2,3],true),'after expiry a new allocation follows the new largest pool');
foreach(['number_read','used_read'] as $failure){[$s,$a,$b]=setup();number($s,1);$a->fail[$failure]='throw';try{acquire($a,'fixture-read-throw');check(false,$failure.' exception propagates');}catch(\RuntimeException $e){check(true,$failure.' exception propagates');}check($s->locks===[],$failure.' exception releases lock');}

// New transition, error and persistence cases, still explicit fake SQL/clock.
foreach(['expiry','sessions_read'] as $failure){
    [$s,$a,$b]=setup();number($s,1);$a->fail[$failure]='false';
    check(acquire($a,'fixture-critical-fail')===null&&$s->leases===[]&&$s->locks===[],$failure.' failure prevents allocation');
}
foreach(['expiry','sessions_read','used_read','eligibility'] as $failure){
    [$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-critical-renew');$GLOBALS['lease_clock']+=60;$a->fail[$failure]='false';
    check(acquire($a,'fixture-critical-renew')===null&&$s->leases[$r['id']]['expires_at']===$r['expires_at']&&$s->locks===[],$failure.' failure prevents renewal and retains reservation');
}
[$s,$a,$b]=setup();number($s,1);number($s,2,'fixture_beta');$r=acquire($a,'fixture-transition');$GLOBALS['lease_clock']+=60;
check(acquire($b,'fixture-transition','fixture_beta')===null&&$s->leases[$r['id']]===$r,'source transition rejects without renewal, relabel or early release');
check(acquire($b,'fixture-unrelated')===null,'rejected source transition does not free number for another visitor');
$GLOBALS['lease_clock']+=1801;$n=acquire($b,'fixture-transition','fixture_beta');
check($n&&$n['source']==='fixture_beta'&&$s->leases[$r['id']]['status']==='expired','after expiry same session may acquire the new source');
foreach(['inactive','deleted','source','phone'] as $change){
    [$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-eligibility-change');$GLOBALS['lease_clock']+=60;
    if($change==='inactive')$s->numbers[1]['is_active']=0;
    if($change==='deleted')unset($s->numbers[1]);
    if($change==='source')$s->numbers[1]['source']='fixture_beta';
    if($change==='phone')$s->numbers[1]['phone_number']='+12025550188';
    check(acquire($b,'fixture-eligibility-change')===null&&$s->leases[$r['id']]===$r,$change.' number cannot renew; original reservation retained');
}
[$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-zero');$n=acquire($b,'fixture-zero');
check($n&&$n['id']===$r['id']&&$n['expires_at']===$r['expires_at'],'legitimate zero-row same-second renewal accepted by readback');
$GLOBALS['lease_clock']+=60;$a->zeroUpdate=true;
check(acquire($a,'fixture-zero')===null&&$s->leases[$r['id']]['expires_at']===$r['expires_at'],'zero-row renewal without target expiry fails checked readback');
[$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-readback');$GLOBALS['lease_clock']+=60;$a->afterUpdate=function()use($a){$a->fail['session_read']='false';};
check(acquire($a,'fixture-readback')===null&&$s->locks===[],'successful renewal with failed readback returns no claimed expiry');
[$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-readback-eligibility');$GLOBALS['lease_clock']+=60;$a->afterUpdate=function()use($a){$a->fail['eligibility']='false';};
check(acquire($a,'fixture-readback-eligibility')===null&&$s->locks===[],'failed post-persistence eligibility read returns no number');
[$s,$a,$b]=setup();number($s,1);number($s,2,'fixture_beta');$contender='not-run';
$a->duringUsedRead=function()use($a,$b,&$contender){$contender=acquire($b,'fixture-cross-source','fixture_beta');$GLOBALS['wpdb']=$a;};
$r=acquire($a,'fixture-cross-source');$q=acquire($b,'fixture-cross-source','fixture_beta');
check($r&&$contender===null&&$q===null&&count($s->leases)===1&&$s->locks===[],'cross-source same-session fake interleaving serialized and source-pinned');
[$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-callback');$GLOBALS['lease_clock']+=60;$GLOBALS['wpdb']=$a;
check((new Leases())->acquire('fixture_alpha','fixture-callback',fn()=>false)===null&&$s->leases[$r['id']]===$r,'failed attribution persistence prevents renewal and preserves old reservation');
[$s,$a,$b]=setup();number($s,1);$GLOBALS['wpdb']=$a;
check((new Leases())->acquire('fixture_alpha','fixture-callback-new',fn()=>false)===null&&$s->leases===[],'failed attribution persistence prevents new allocation');
[$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-duplicate');$duplicate=$r;$duplicate['id']=99;$s->leases[99]=$duplicate;
check(acquire($a,'fixture-duplicate')===null&&$s->locks===[],'preexisting duplicate reservation fails closed');

[$s,$a,$b]=setup();number($s,1);$a->fail['number_unknown']='false';
check(acquire($a,'fixture-unknown')===null&&$s->leases===[],'unknown non-array availability result fails closed even without error text');
[$s,$a,$b]=setup();number($s,1);$a->zeroInsert=true;
check(acquire($a,'fixture-zero-insert')===null&&$s->leases===[],'zero-row insert does not establish persistence');
[$s,$a,$b]=setup();number($s,1);$a->afterInsert=function()use($a){$a->fail['session_read']='false';};
check(acquire($a,'fixture-insert-readback')===null&&count($s->leases)===1&&$s->locks===[],'failed insertion readback returns no number but keeps persisted reservation');
[$s,$a,$b]=setup();number($s,1);$r=acquire($a,'fixture-renew-reservation');$GLOBALS['lease_clock']+=60;$a->fail['renewal']='false';acquire($a,'fixture-renew-reservation');
check(acquire($b,'fixture-other-visitor')===null&&$s->leases[$r['id']]===$r,'failed renewal leaves original reservation unavailable to another session');
[$s,$a,$b]=setup();number($s,1);$a->fail['session_unknown']='false';
check(acquire($a,'fixture-unknown-session')===null&&$s->leases===[],'unknown non-row session lookup fails closed without error text');
[$s,$a,$b]=setup();$lock=(new Leases())->allocationLockName();$a->prefix='other_site_';
check($lock!==(new Leases())->allocationLockName()&&strlen($lock)===51,'allocation lock scopes database/site prefix and fits native lock name limit');
$failed=count(array_filter($results,fn($r)=>!$r['ok']));echo 'RESULT '.(count($results)-$failed).' PASS / '.$failed." FAIL; fake wpdb only, no SQL exclusivity acceptance.\n";
if(isset($argv[1]))file_put_contents($argv[1],json_encode($results,JSON_PRETTY_PRINT)."\n");exit($failed?1:0);
}
