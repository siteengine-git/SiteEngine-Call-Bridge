<?php
/** Direct CLI only. Does not include WordPress, wp-config, installed CB or candidate entrypoint. */
declare(strict_types=1);
require_once __DIR__.'/candidate-loader.php';
function secb_standalone_entry(array $argv):int {
    secb_standalone_no_bootstrap();
    umask(0077);
    if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||class_exists('wpdb',false)||isset($GLOBALS['wpdb']))throw new RuntimeException('fresh_native_cli_required');
    [$script,$mode,$run,$site,$toolingHash]=array_pad($argv,5,'');
    $allowed=['prepare','audit','plan','worker','seed-clones','reset-clone-leases','race','sequential','inspect','cleanup-clones'];if(!in_array($mode,$allowed,true))throw new RuntimeException('unknown_mode');
    if(!preg_match('~^/[A-Za-z0-9_./-]+$~D',$site)||realpath($site)!==$site||$run!==dirname($site).'/.secb-acceptance/cb-lease-dni-012-standalone-20261028-01'||str_starts_with($run,$site.'/'))throw new RuntimeException('exact_private_run_required');
    if(!function_exists('posix_geteuid')||fileowner($site)!==posix_geteuid())throw new RuntimeException('registered_site_owner_required');
    secb_standalone_private_tree($run,filegroup($site));
    secb_standalone_tooling(__DIR__,$toolingHash);
    if(realpath(__DIR__)!==$run.'/harness')throw new RuntimeException('harness_outside_run');
    if($mode==='prepare'){
        $files=secb_standalone_zip($run.'/candidate.zip');$target=$run.'/candidate';if(file_exists($target))throw new RuntimeException('preserve_existing_candidate');
        if(!mkdir($target,0700))throw new RuntimeException('candidate_directory');
        foreach($files as $path=>$body){$file=$target.'/siteengine-call-bridge/'.$path;$dir=dirname($file);if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('candidate_parent');if(file_put_contents($file,$body,LOCK_EX)!==strlen($body)||!chmod($file,0600))throw new RuntimeException('candidate_extract_failed');}
        echo json_encode(['prepared_files'=>count($files),'zip_hash'=>SECB_S_ZIP_HASH]),"\n";return 0;
    }
    $verified=secb_standalone_verify($run);$audit=secb_standalone_audit($site);
    $auditHash=hash('sha256',json_encode($audit,JSON_THROW_ON_ERROR));
    if($mode==='audit'){echo json_encode($audit+['candidate_verified'=>true,'review_sha256'=>$auditHash],JSON_PRETTY_PRINT),"\n";return 0;}
    secb_standalone_check_audit($audit,$argv,$mode!=='plan');
    if($mode!=='plan'&&!in_array('--execute',$argv,true))throw new RuntimeException('explicit_execution_gate_required');
    $cfg=secb_standalone_config(file_get_contents($site.'/wp-config.php'));
    $external=$cfg['SECB_CONFIG_FILE']??'';if($external!==dirname($site).'/.secrets/siteengine-call-bridge.php'||!is_readable($external))throw new RuntimeException('external_config_path_requires_review');
    // No external constants/credentials are executed or migrated. Timing is pinned for this SQL experiment.
    foreach(['DB_NAME','DB_USER','DB_PASSWORD','DB_HOST'] as $key){if(defined($key))throw new RuntimeException('predefined_database_constant');define($key,$cfg[$key]);}
    define('ABSPATH',$site.'/');define('ARRAY_A','ARRAY_A');define('SECB_PATH',$verified['root'].'/');define('SECB_CONFIG_FILE',$external);define('SECB_LEASE_MINUTES',30);
    $origins=secb_standalone_load($run);
    $GLOBALS['secb_standalone_config']=$cfg;
    $GLOBALS['secb_standalone_context']=['run'=>$run,'site'=>$site,'tooling_hash'=>$toolingHash,'root'=>$verified['root'],'origins'=>$origins,'audit_pin'=>$auditHash];
    require_once __DIR__.'/database.php';
    $original=$GLOBALS['wpdb']??null;$hadOriginal=array_key_exists('wpdb',$GLOBALS);$db=null;
    $rootPrefix=$cfg['prefix'];
    $restore=static function()use(&$db,$original,$hadOriginal,$rootPrefix){secb_standalone_restore_globals($original,$hadOriginal,$db,$rootPrefix);};
    register_shutdown_function($restore); // Native safety net only; no WP hook registration.
    try {
        $db=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$GLOBALS['wpdb']=$db;
        require_once __DIR__.'/lease-sql.php';
        secb_accept_query('SET SESSION max_statement_time=5');
        if($mode==='worker'){secb_accept_worker($run,$argv[5]??'',$argv[6]??'',true);return 0;}
        $result=secb_accept_sql($mode,$run,$mode!=='plan',($argv[5]??'')?:null);
        secb_standalone_origins($verified['root']);
        echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
        return (in_array($mode,['race','sequential'],true)&&($result['pass']??null)!==true)||($mode==='cleanup-clones'&&($result['shadow_tables_absent']??null)!==true)?1:0;
    } finally {$restore();if($db)$db->close();}
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){try{exit(secb_standalone_entry($argv));}catch(Throwable $e){fwrite(STDERR,'Standalone Stage S stopped: '.$e->getMessage()."\n");exit(1);}}
