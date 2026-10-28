<?php
/** Actual loader/reflection/SQL-gate rejection tests; no server, WP or database. */
declare(strict_types=1);
require dirname(__DIR__).'/tools/acceptance/standalone/candidate-loader.php';
require dirname(__DIR__).'/tools/acceptance/standalone/database.php';
require dirname(__DIR__).'/tools/acceptance/stage-s-process.php';
$base=dirname(__DIR__).'/.secrets/standalone-stage-s-'.bin2hex(random_bytes(6));mkdir($base,0700,true);
copy(dirname(__DIR__).'/siteengine-call-bridge-0.1.2-lease-dni-review-20261028.zip',$base.'/candidate.zip');
$files=secb_standalone_zip($base.'/candidate.zip');foreach($files as $path=>$body){$dest=$base.'/candidate/siteengine-call-bridge/'.$path;if(!is_dir(dirname($dest)))mkdir(dirname($dest),0700,true);file_put_contents($dest,$body);}
$child= <<<'PHP'
<?php
$project=dirname(__DIR__,2);$run=str_replace('\\','/',__DIR__);$candidate=$run.'/candidate/siteengine-call-bridge';
require $project.'/tools/acceptance/standalone/candidate-loader.php';
define('ABSPATH','fixture/');define('ARRAY_A','ARRAY_A');define('DB_NAME','fixture_db');
$mode=$argv[1];$expectReject=!in_array($mode,['load','restore-prefix','config-literals','no-candidate-boot','worker-origins','restore-global'],true);$rejected=false;$restore=null;
try {
 if($mode==='changed-zip'){file_put_contents($run.'/changed.zip',file_get_contents($run.'/candidate.zip').'changed');secb_standalone_zip($run.'/changed.zip');}
 elseif($mode==='preloaded'){require $project.'/src/Support/Config.php';secb_standalone_load($run);}
 elseif(in_array($mode,['wrong-origin','mixed-origin'],true)){
  foreach(secb_standalone_classes() as $class=>$path)require (($mode==='mixed-origin'&&str_contains($class,'Config'))?$candidate:$project).'/'.$path;
  secb_standalone_origins($candidate);
 }
 elseif($mode==='installer-class'){eval('namespace SiteEngine\\CallBridge; class Installer {}');secb_standalone_load($run);}
 elseif($mode==='plugin-class'){eval('namespace SiteEngine\\CallBridge; class Plugin {}');secb_standalone_load($run);}
 elseif($mode==='hook-api'){eval('function add_action() {}');secb_standalone_load($run);}
 elseif($mode==='autoload'){spl_autoload_register(static function($class){});secb_standalone_load($run);}
 elseif($mode==='bootstrap-include'){file_put_contents($run.'/wp-settings.php','<?php $GLOBALS["bootstrap_seen"]=true;');require $run.'/wp-settings.php';secb_standalone_load($run);}
 elseif(in_array($mode,['changed-payload','changed-entry'],true)){
  $p=$candidate.'/'.($mode==='changed-entry'?'siteengine-call-bridge.php':'src/DB/NumberLeaseRepository.php');$old=file_get_contents($p);$restore=static fn()=>file_put_contents($p,$old);file_put_contents($p,'<?php file_put_contents(__DIR__."/BOOTED", "bad");');secb_standalone_load($run);
 }
 elseif($mode==='config-expression'){secb_standalone_config('<?php define("DB_NAME",getenv("DB_NAME"));');}
 elseif($mode==='config-duplicate'){secb_standalone_config('<?php define("DB_NAME","a"); define("DB_NAME","b");');}
 elseif($mode==='config-conditional'){secb_standalone_config('<?php if(false){define("DB_NAME","fictional");}');}
 elseif($mode==='config-unbraced'){secb_standalone_config('<?php if(false) define("DB_NAME","fictional");');}
 elseif($mode==='config-literals'){
  $v=secb_standalone_config('<?php define("DB_NAME","fictional"); define("DB_USER","fixture"); define("DB_PASSWORD","not-a-secret"); define("DB_HOST","localhost"); $table_prefix="fixture_"; require "NEVER_EXECUTED.php";');
  if($v['prefix']!=='fixture_'||$v['DB_NAME']!=='fictional')throw new RuntimeException('literal_parse');
 }
 else {
  $origins=secb_standalone_load($run);if(count($origins)!==3)throw new RuntimeException('origin_count');
  if(in_array($mode,['worker-origins','worker-mixed','worker-missing'],true)){
   $ready=$results=['a'=>['candidate_origins'=>$origins],'b'=>['candidate_origins'=>$origins]];
   if($mode==='worker-mixed')$results['b']['candidate_origins']['SiteEngine\\CallBridge\\Support\\Config']['path']='installed/wrong.php';
   if($mode==='worker-missing')unset($ready['a']['candidate_origins']);
   secb_standalone_check_workers($ready,$results,$candidate);
  }
  if($mode==='restore-global'){
   $before=(object)['prefix'=>'original_'];$temp=new class {public string $prefix='clone_';public bool $scope=true;public function endCandidate():void {$this->scope=false;}};
   $GLOBALS['secb_standalone_config']=['fixture'=>true];$GLOBALS['secb_standalone_context']=['fixture'=>true];
   $GLOBALS['wpdb']=$temp;secb_standalone_restore_globals($before,true,$temp,'original_');if($GLOBALS['wpdb']!==$before||$temp->prefix!=='original_'||$temp->scope||isset($GLOBALS['secb_standalone_config'])||isset($GLOBALS['secb_standalone_context']))throw new RuntimeException('global_restore_failed');
   secb_standalone_restore_globals(null,false,$temp,'original_');if(isset($GLOBALS['wpdb']))throw new RuntimeException('global_unset_failed');
  }
  if($mode==='no-candidate-boot'&&(defined('SECB_VERSION')||class_exists('SiteEngine\\CallBridge\\Installer',false)||class_exists('SiteEngine\\CallBridge\\Plugin',false)||function_exists('add_action')))throw new RuntimeException('unexpected_boot');
  if(in_array($mode,['restore-prefix','wrong-prefix','wrong-target-action','outside-scope'],true)){
   require $project.'/tools/acceptance/standalone/database.php';require $project.'/tools/acceptance/standalone/lease-sql.php';
   $GLOBALS['secb_standalone_config']=['prefix'=>'fixture_'];$GLOBALS['secb_standalone_context']=['root'=>$candidate];
   $wpdb=(new ReflectionClass('wpdb'))->newInstanceWithoutConstructor();$wpdb->prefix='fixture_';$rootProperty=new ReflectionProperty('wpdb','root');$rootProperty->setValue($wpdb,'fixture_');
   $prefix='fixture_secb_accept_20261027_01_';$state=['fixture_prefix'=>$prefix,'tables'=>[$prefix.'se_cb_tracking_numbers',$prefix.'se_cb_number_leases']];
   if($mode==='wrong-prefix')$state['fixture_prefix']='fixture_';
   if($mode==='outside-scope')(new \SiteEngine\CallBridge\DB\NumberLeaseRepository())->expireStale();
   if($mode==='wrong-target-action')secb_accept_in_fixture($state,fn()=>$wpdb->query("UPDATE fixture_se_cb_number_leases SET status='expired'"));
   elseif($mode==='wrong-prefix')secb_accept_in_fixture($state,fn()=>null);
   else {try{secb_accept_in_fixture($state,static function(){throw new RuntimeException('injected');});}catch(RuntimeException $e){if($e->getMessage()!=='injected')throw $e;}
    if($wpdb->prefix!=='fixture_'||(new ReflectionProperty('wpdb','candidate'))->getValue($wpdb)!==false)throw new RuntimeException('prefix_scope_not_restored');}
  }
 }
}catch(Throwable $e){$rejected=true;$reason=$e->getMessage();}finally{if($restore)$restore();}
$ok=$expectReject===$rejected&&!file_exists($candidate.'/BOOTED');echo json_encode(['case'=>$mode,'pass'=>$ok,'rejected'=>$rejected,'reason'=>$reason??null]),"\n";exit($ok?0:1);
PHP;
file_put_contents($base.'/child.php',$child);
$pass=0;$fail=0;
function standalone_check(bool $ok,string $name):void {global $pass,$fail;echo ($ok?'PASS ':'FAIL ').$name."\n";$ok?$pass++:$fail++;}
foreach(['load','no-candidate-boot','preloaded','wrong-origin','mixed-origin','installer-class','plugin-class','hook-api','autoload','bootstrap-include','changed-zip','changed-payload','changed-entry','config-expression','config-duplicate','config-conditional','config-unbraced','config-literals','worker-origins','worker-mixed','worker-missing','restore-global','restore-prefix','wrong-prefix','wrong-target-action','outside-scope'] as $case){
 $h=proc_open([PHP_BINARY,'-n','child.php',$case],[0=>['pipe','r'],1=>['file',$base.'/'.$case.'.json','w'],2=>['file',$base.'/'.$case.'.err','w']],$pipes,$base);fclose($pipes[0]);$p=secb_s_process_new($h);$deadline=microtime(true)+5;
 while(secb_s_process_poll($p)&&microtime(true)<$deadline)usleep(10000);if($p['running']){$p['timed_out']=true;secb_s_process_abort($p);} $exit=secb_s_process_close($p);$r=json_decode(file_get_contents($base.'/'.$case.'.json'),true);
 standalone_check($exit['exit_code']===0&&($r['pass']??false),$case.' (real fresh PHP process, no SQL)');
}
$root='fixture_';$clone=$root.'secb_accept_20261027_01_';$lock='secb_alloc_'.substr(sha1('fixture_db|'.$clone),0,40);
$positive=["SELECT GET_LOCK('$lock',2)","SELECT RELEASE_LOCK('$lock')","SELECT * FROM {$clone}se_cb_number_leases WHERE session_id='fictional'","UPDATE {$clone}se_cb_number_leases SET status='expired', released_at=COALESCE(released_at,UTC_TIMESTAMP()) WHERE status='active' AND expires_at<=UTC_TIMESTAMP()","INSERT INTO `{$clone}se_cb_number_leases` (`session_id`) VALUES ('fictional')","SELECT * FROM {$clone}se_cb_tracking_numbers WHERE id=1"];
$negative=["SELECT GET_LOCK('secb_lease_foreign',2)","SELECT GET_LOCK('secb_alloc_'.'wrong',2)","SELECT * FROM fixture_se_cb_number_leases","UPDATE fixture_se_cb_number_leases SET status='expired'","INSERT INTO fixture_options (option_name) VALUES ('bad')","DELETE FROM fixture_se_cb_calls","DROP TABLE `{$clone}se_cb_number_leases`","CREATE TABLE `{$clone}se_cb_number_leases` LIKE `fixture_se_cb_number_leases`","SELECT * FROM {$clone}se_cb_number_leases; DELETE FROM fixture_options","SELECT * FROM {$clone}se_cb_number_leases UNION SELECT * FROM fixture_options","SELECT SLEEP(1) FROM {$clone}se_cb_number_leases"];
foreach($positive as $i=>$sql){$ok=true;try{secb_standalone_sql_gate($sql,$root,'fixture_db',true);}catch(Throwable $e){$ok=false;}standalone_check($ok,'candidate SQL allowed '.$i);}
foreach($negative as $i=>$sql){$ok=false;try{secb_standalone_sql_gate($sql,$root,'fixture_db',true);}catch(Throwable $e){$ok=true;}standalone_check($ok,'candidate SQL rejected '.$i);}
foreach(["UPDATE fixture_se_cb_number_leases SET status='expired'","DROP TABLE `fixture_se_cb_number_leases`","SET GLOBAL max_statement_time=5","CREATE TABLE `{$clone}se_cb_number_leases` LIKE `fixture_options`"] as $i=>$sql){$ok=false;try{secb_standalone_sql_gate($sql,$root,'fixture_db',false);}catch(Throwable $e){$ok=true;}standalone_check($ok,'supervisor forbidden SQL rejected '.$i);}
$site=$base.'/site';mkdir($site.'/wp-content/mu-plugins',0700,true);
file_put_contents($site.'/wp-config.php','<?php require "NEVER_EXECUTE.php";');file_put_contents($site.'/wp-settings.php','<?php file_put_contents(__DIR__."/BOOTED", "bad");');
file_put_contents($site.'/wp-content/mu-plugins/fixture.php','<?php file_put_contents(__DIR__."/BOOTED", "bad");');file_put_contents($site.'/wp-content/object-cache.php','<?php file_put_contents(__DIR__."/BOOTED", "bad");');
$audit=secb_standalone_audit($site);$auditHash=hash('sha256',json_encode($audit,JSON_THROW_ON_ERROR));
standalone_check(!file_exists($site.'/BOOTED')&&!file_exists($site.'/wp-content/BOOTED')&&!file_exists($site.'/wp-content/mu-plugins/BOOTED')&&$audit['config_effect_tokens']['T_REQUIRE']===1,'bootstrap/MU/drop-in inventory reads only; trap code never executes');
standalone_check(secb_standalone_check_audit($audit,['--audit-pin='.$auditHash],true)===$auditHash,'reviewed inventory pin accepted');
foreach(['missing','wrong','changed','routing'] as $case){$a=$audit;$args=['--audit-pin='.$auditHash];if($case==='missing')$args=[];if($case==='wrong')$args=['--audit-pin='.str_repeat('0',64)];if($case==='changed')$a['files']['changed']=true;if($case==='routing')$a['custom_routing_requires_review']=true;$ok=false;try{secb_standalone_check_audit($a,$args,true);}catch(Throwable $e){$ok=true;}standalone_check($ok,'bootstrap inventory gate rejects '.$case);}
standalone_check(hash_file('sha256',dirname(__DIR__).'/tools/acceptance/standalone/stage-s-process.php')===hash_file('sha256',dirname(__DIR__).'/tools/acceptance/stage-s-process.php'),'strict process implementation unchanged');
standalone_check(hash_file('sha256',dirname(__DIR__).'/tools/acceptance/standalone/stage-s-verdict.php')===hash_file('sha256',dirname(__DIR__).'/tools/acceptance/stage-s-verdict.php'),'strict verdict implementation unchanged');
$toolDir=dirname(__DIR__).'/tools/acceptance/standalone';$toolHash=hash_file('sha256',$toolDir.'/STAGE-S-MANIFEST.sha256');$copy=$base.'/tooling';mkdir($copy);foreach(scandir($toolDir) as $file)if(is_file($toolDir.'/'.$file))copy($toolDir.'/'.$file,$copy.'/'.$file);
$ok=true;try{secb_standalone_tooling($copy,$toolHash);}catch(Throwable $e){$ok=false;}standalone_check($ok,'exact independently pinned tooling inventory accepted');
foreach(['pin','extra','changed'] as $case){$ack=$case==='pin'?str_repeat('0',64):$toolHash;$file=$copy.'/runner.php';$body=file_get_contents($file);if($case==='extra')file_put_contents($copy.'/extra.php','<?php');if($case==='changed')file_put_contents($file,$body.'changed');$ok=false;try{secb_standalone_tooling($copy,$ack);}catch(Throwable $e){$ok=true;}if($case==='extra')unlink($copy.'/extra.php');if($case==='changed')file_put_contents($file,$body);standalone_check($ok,'tooling inventory rejects '.$case);}
echo "SUMMARY: $pass PASS / $fail FAIL; PHP ".PHP_VERSION."; native SQL/Alma not executed.\n";exit($fail?1:0);
