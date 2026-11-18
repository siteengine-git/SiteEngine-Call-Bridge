<?php
/** Whole-statement SQL confinement; real adapter emitters, mocked mysqli, NO SQL. */
declare(strict_types=1);
if (extension_loaded('mysqli')) throw new RuntimeException('Run with PHP -n; this suite requires its explicit mysqli mock');
// Only exercise serialization/gating. These never open a connection or execute SQL.
const MYSQLI_REPORT_OFF=0;
function mysqli_report(int $flags):void {}
final class mysqli {
    public int $connect_errno=0,$insert_id=1,$affected_rows=1;
    public array $queries=[];
    public function __construct(mixed ...$args) {}
    public function set_charset(string $charset):bool {return true;}
    public function real_escape_string(string $value):string {return addcslashes($value,"\\'\x00\n\r\x1a");}
    public function query(string $sql):bool {$this->queries[]=$sql;return true;}
}
require $argv[1]??dirname(__DIR__).'/tools/acceptance/standalone/database.php';
$pass=0;$fail=0;
function confinement_check(bool $ok,string $label):void {global $pass,$fail;echo ($ok?'PASS ':'FAIL ').$label."\n";$ok?$pass++:$fail++;}
$root='fixture_';$clone=$root.'secb_accept_20261027_01_';$numbers=$clone.'se_cb_tracking_numbers';$leases=$clone.'se_cb_number_leases';
define('DB_NAME','fixture_db');
$GLOBALS['secb_standalone_config']=['prefix'=>$root,'DB_USER'=>'fixture','DB_PASSWORD'=>'not-a-credential','DB_NAME'=>DB_NAME,'DB_HOST'=>'localhost'];
$db=new wpdb('fixture','not-a-credential',DB_NAME,'localhost');$db->prefix=$clone;
$number=['phone_number'=>'+12025550101','forward_to'=>'+12025550190','source'=>'cbaccept_20261027_alpha','pool_group'=>'cbaccept_last','display_label'=>'cbaccept-20261027-01-1','record_calls'=>0,'is_active'=>1,'notes'=>'cbaccept-20261027-01'];
$lease=['session_id'=>'cbaccept-20261027-01-last-slot-a','tracking_number_id'=>1,'tracking_number'=>'+12025550101','source'=>'cbaccept_20261027_alpha','leased_at'=>'2026-10-28 12:00:00','expires_at'=>'2026-10-28 12:30:00','status'=>'active'];
$db->beginCandidate($clone);
foreach(['five-number fixture insert'=>fn()=>$db->insert($numbers,$number),'new lease insert'=>fn()=>$db->insert($leases,$lease),'checked renewal'=>fn()=>$db->update($leases,['expires_at'=>'2026-10-28 12:30:01'],['id'=>1,'status'=>'active','expires_at'=>'2026-10-28 12:30:00'])] as $label=>$action){try{$ok=$action()===1;}catch(Throwable $e){$ok=false;}confinement_check($ok,'actual adapter emitter: '.$label);}
foreach([[1,'alpha','last'],[2,'beta','beta'],[3,'pool','large'],[4,'pool','large'],[5,'pool','small']] as [$n,$source,$pool]){
    $row=$number;$row['phone_number']='+120255501'.sprintf('%02d',$n);$row['source']='cbaccept_20261027_'.$source;$row['pool_group']='cbaccept_'.$pool;$row['display_label']='cbaccept-20261027-01-'.$n;
    try{$ok=$db->insert($numbers,$row)===1;}catch(Throwable $e){$ok=false;}confinement_check($ok,'actual fixture row serialization '.$n);
}
foreach([['notes'=>null],['notes'=>'literal SELECT FROM fixture_options, JOIN text']] as $i=>$changes){try{$ok=$db->update($numbers,$changes,['id'=>1])===1;}catch(Throwable $e){$ok=false;}confinement_check($ok,'literal-only adapter syntax '.$i);}
$db->endCandidate();
$where=['id'=>1,'phone_number'=>$number['phone_number'],'source'=>$number['source'],'notes'=>$number['notes'],'pool_group'=>$number['pool_group'],'is_active'=>1];
foreach([['is_active'=>0],['is_active'=>1],['pool_group'=>'cbaccept_small'],['pool_group'=>'cbaccept_large'],['is_active'=>1,'pool_group'=>'cbaccept_large']] as $i=>$changes){try{$ok=$db->update($numbers,$changes,$where)===1;}catch(Throwable $e){$ok=false;}confinement_check($ok,'actual adapter emitter: owned fixture edit '.$i);}
$expiry="UPDATE $leases SET status='expired', released_at=COALESCE(released_at,UTC_TIMESTAMP()) WHERE status='active' AND expires_at<=UTC_TIMESTAMP()";
$minus="UPDATE `$leases` SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=1";
$plus="UPDATE `$leases` SET expires_at=UTC_TIMESTAMP()+INTERVAL 5 SECOND WHERE id=1";
$reset="DELETE FROM `$leases`";
foreach([[$expiry,true],[$expiry,false],[$minus,false],[$plus,false],[$reset,false],["CREATE TABLE `$leases` LIKE `fixture_se_cb_number_leases`",false],["ALTER TABLE `$leases` COMMENT='cbaccept:20261027:01'",false],["DROP TABLE `$leases`",false],["SELECT option_value FROM fixture_options WHERE option_name='cron'",false]] as $i=>[$sql,$scope]){try{secb_standalone_sql_gate($sql,$root,DB_NAME,$scope);$ok=true;}catch(Throwable $e){$ok=false;}confinement_check($ok,'reviewed direct statement '.$i);}
$negative=[
 'joined foreign UPDATE'=>"UPDATE `$leases` JOIN fixture_options ON 1=1 SET fixture_options.option_value='changed'",
 'joined shadow UPDATE'=>"UPDATE `$leases` JOIN `$numbers` ON 1=1 SET `$leases`.status='expired'",
 'multi-target UPDATE'=>"UPDATE `$leases`,fixture_options SET fixture_options.option_value='changed'",
 'INSERT SELECT foreign'=>"INSERT INTO `$leases` (`session_id`) SELECT option_value FROM fixture_options",
 'INSERT SELECT shadow'=>"INSERT INTO `$leases` (`session_id`) SELECT session_id FROM `$leases`",
 'multi-target DELETE'=>"DELETE `$leases`,fixture_options FROM `$leases` JOIN fixture_options ON 1=1",
 'DELETE USING foreign'=>"DELETE FROM `$leases` USING `$leases` JOIN fixture_options ON 1=1",
 'DELETE joined tail'=>"DELETE FROM `$leases` JOIN fixture_options ON 1=1",
 'UPDATE value subquery'=>"UPDATE `$leases` SET `expires_at`=(SELECT option_value FROM fixture_options) WHERE `id`='1'",
 'UPDATE predicate subquery'=>"UPDATE `$leases` SET `status`='expired' WHERE `id`=(SELECT option_id FROM fixture_options)",
 'INSERT value subquery'=>"INSERT INTO `$leases` (`session_id`) VALUES ((SELECT option_value FROM fixture_options))",
 'DELETE predicate subquery'=>"DELETE FROM `$leases` WHERE id IN (SELECT option_id FROM fixture_options)",
 'INSERT duplicate-key tail'=>"INSERT INTO `$leases` (`session_id`) VALUES ('fixture') ON DUPLICATE KEY UPDATE session_id=(SELECT option_value FROM fixture_options)",
 'INSERT RETURNING'=>"INSERT INTO `$leases` (`session_id`) VALUES ('fixture') RETURNING session_id",
 'multi-row INSERT'=>"INSERT INTO `$leases` (`session_id`) VALUES ('fixture'),('second')",
 'UPDATE order tail'=>"UPDATE `$leases` SET `status`='expired' WHERE `id`='1' ORDER BY id",
 'UPDATE limit tail'=>"UPDATE `$leases` SET `status`='expired' WHERE `id`='1' LIMIT 1",
 'UPDATE OR tail'=>"UPDATE `$leases` SET `status`='expired' WHERE `id`='1' OR 1=1",
 'UPDATE no predicate'=>"UPDATE `$leases` SET `status`='expired'",
 'UPDATE expression'=>"UPDATE `$leases` SET `id`=1+1 WHERE `id`='1'",
 'qualified identifier'=>"UPDATE `$leases` SET fixture_options.option_value='changed' WHERE `id`='1'",
 'qualified table'=>"UPDATE fixture_db.`$leases` SET `status`='expired' WHERE `id`='1'",
 'unmatched table quote'=>"UPDATE `$leases SET `status`='expired' WHERE `id`='1'",
 'mode-dependent escaped quote'=>"INSERT INTO `$leases` (`session_id`) VALUES ('a\\'b')",
 'backslash literal'=>"INSERT INTO `$leases` (`session_id`) VALUES ('a\\b')",
 'statement newline tail'=>"UPDATE `$leases` SET `status`='expired' WHERE `id`='1'\nSELECT * FROM fixture_options",
 'wrong expiry interval'=>"UPDATE `$leases` SET expires_at=UTC_TIMESTAMP()+INTERVAL 6 SECOND WHERE id=1",
 'wrong expiry table'=>"UPDATE `$numbers` SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=1",
 'delete tracking clone'=>"DELETE FROM `$numbers`",
 'original UPDATE'=>"UPDATE fixture_options SET `option_value`='changed' WHERE `option_name`='fixture'",
 'original INSERT'=>"INSERT INTO fixture_options (`option_name`) VALUES ('fixture')",
 'original DELETE'=>"DELETE FROM fixture_options",
];
foreach($negative as $label=>$sql)foreach([false,true] as $scope){$rejected=false;try{secb_standalone_sql_gate($sql,$root,DB_NAME,$scope);}catch(RuntimeException $e){$rejected=true;}confinement_check($rejected,($scope?'candidate':'supervisor').' rejects '.$label);}
foreach([$minus,$plus,$reset] as $i=>$sql){$rejected=false;try{secb_standalone_sql_gate($sql,$root,DB_NAME,true);}catch(RuntimeException $e){$rejected=true;}confinement_check($rejected,'supervisor-only mutation rejected in candidate scope '.$i);}
foreach([false,true] as $scope){$lock='secb_alloc_'.substr(sha1(DB_NAME.'|'.$clone),0,40);foreach(['GET_LOCK'=>',2','RELEASE_LOCK'=>'','IS_USED_LOCK'=>'','IS_FREE_LOCK'=>''] as $fn=>$args){$ok=true;try{secb_standalone_sql_gate("SELECT $fn('$lock'$args)",$root,DB_NAME,$scope);}catch(Throwable $e){$ok=false;}confinement_check($ok,'clone lock retained '.$fn.' '.(int)$scope);}foreach(["SELECT GET_LOCK('foreign',2)","SELECT * FROM fixture_options"] as $i=>$sql){$reject=false;try{secb_standalone_sql_gate($sql,$root,DB_NAME,$scope);}catch(RuntimeException $e){$reject=true;}confinement_check($reject===($i===0||$scope),'original-read/foreign-lock boundary '.$i.' '.(int)$scope);}}
echo "SUMMARY: $pass PASS / $fail FAIL; PHP ".PHP_VERSION."; mysqli mocked; no native SQL.\n";exit($fail?1:0);
