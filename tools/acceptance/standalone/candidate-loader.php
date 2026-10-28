<?php
/** Standalone package/origin/config gates. No WordPress or SQL bootstrap. */
declare(strict_types=1);
const SECB_S_ZIP_HASH='6b240e1b7633cac208ad08834df76dc9f7d741168884560b8e0aae5ce161c5de';
const SECB_S_MANIFEST_HASH='a8135e0e227eeee68f7cb436b9224ed2d918ad72e18b652ca1b361336e9ec977';
function secb_standalone_classes():array {
    return ['SiteEngine\\CallBridge\\Support\\Config'=>'src/Support/Config.php','SiteEngine\\CallBridge\\DB\\TrackingNumberRepository'=>'src/DB/TrackingNumberRepository.php','SiteEngine\\CallBridge\\DB\\NumberLeaseRepository'=>'src/DB/NumberLeaseRepository.php'];
}
function secb_standalone_no_bootstrap():void {
    foreach(['WP','WP_CLI','SiteEngine\\CallBridge\\Plugin','SiteEngine\\CallBridge\\Installer'] as $class)if(class_exists($class,false))throw new RuntimeException('bootstrap_class_forbidden');
    foreach(['add_action','add_filter','register_activation_hook','get_option'] as $fn)if(function_exists($fn))throw new RuntimeException('wordpress_bootstrap_forbidden');
    if(defined('WP_CLI')||defined('SECB_VERSION')||ini_get('auto_prepend_file')||ini_get('auto_append_file'))throw new RuntimeException('plugin_or_startup_bootstrap_forbidden');
    foreach(get_included_files() as $file)if(preg_match('~(?:wp-load|wp-settings|wp-config|advanced-cache|object-cache|sunrise|db)\.php$|[/\\\\]mu-plugins[/\\\\]|[/\\\\]siteengine-call-bridge\.php$~i',$file))throw new RuntimeException('bootstrap_include_forbidden');
    if(spl_autoload_functions())throw new RuntimeException('foreign_autoloader_forbidden');
}
function secb_standalone_zip(string $zip):array {
    $b=file_get_contents($zip);if(hash('sha256',$b)!==SECB_S_ZIP_HASH)throw new RuntimeException('candidate_zip_hash');
    $end=strrpos($b,"PK\x05\x06");if($end===false)throw new RuntimeException('zip_end');
    $u16=static fn($p)=>unpack('v',substr($b,$p,2))[1];$u32=static fn($p)=>unpack('V',substr($b,$p,4))[1];
    $count=$u16($end+10);$p=$u32($end+16);$files=[];
    for($i=0;$i<$count;$i++){
        if(substr($b,$p,4)!=="PK\x01\x02")throw new RuntimeException('zip_central');
        $method=$u16($p+10);$crc=$u32($p+16);$packed=$u32($p+20);$size=$u32($p+24);$len=$u16($p+28);$extra=$u16($p+30);$comment=$u16($p+32);$offset=$u32($p+42);
        $name=substr($b,$p+46,$len);$p+=46+$len+$extra+$comment;
        if(!preg_match('~^siteengine-call-bridge/([A-Za-z0-9_./-]+)$~D',$name,$m)||str_contains($m[1],'..')||str_ends_with($name,'/'))throw new RuntimeException('zip_path');
        if(substr($b,$offset,4)!=="PK\x03\x04")throw new RuntimeException('zip_local');
        $start=$offset+30+$u16($offset+26)+$u16($offset+28);$raw=substr($b,$start,$packed);
        $body=$method===8?gzinflate($raw):($method===0?$raw:false);
        if($body===false||strlen($body)!==$size||sprintf('%u',crc32($body))!==(string)$crc||isset($files[$m[1]]))throw new RuntimeException('zip_crc_size_duplicate');$files[$m[1]]=$body;
    }
    if(count($files)!==51||hash('sha256',$files['RELEASE-MANIFEST.sha256']??'')!==SECB_S_MANIFEST_HASH)throw new RuntimeException('package_manifest_pin');
    $seen=[];foreach(explode("\n",trim($files['RELEASE-MANIFEST.sha256'])) as $line){if(!preg_match('~^([a-f0-9]{64})  \./([A-Za-z0-9_./-]+)$~D',$line,$m)||isset($seen[$m[2]])||!isset($files[$m[2]])||hash('sha256',$files[$m[2]])!==$m[1])throw new RuntimeException('package_payload_hash');$seen[$m[2]]=true;}
    if(count($seen)!==50)throw new RuntimeException('package_payload_count');return $files;
}
function secb_standalone_verify(string $run):array {
    $files=secb_standalone_zip($run.'/candidate.zip');$root=$run.'/candidate/siteengine-call-bridge';
    if(str_replace('\\','/',realpath($root)?:'')!==$root||is_link($root))throw new RuntimeException('candidate_root');
    $actual=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $f){if($f->isLink()||!$f->isFile())throw new RuntimeException('candidate_symlink_or_nonfile');$rel=str_replace('\\','/',substr($f->getPathname(),strlen($root)+1));$actual[$rel]=true;if(!isset($files[$rel])||!hash_equals(hash('sha256',$files[$rel]),hash_file('sha256',$f->getPathname())))throw new RuntimeException('extracted_payload_changed');}
    if(count($actual)!==51)throw new RuntimeException('candidate_missing_file');return ['root'=>$root,'files'=>$files];
}
function secb_standalone_origins(string $root):array {
    secb_standalone_no_bootstrap();$expected=secb_standalone_classes();$seen=[];
    $manifest=file_get_contents($root.'/RELEASE-MANIFEST.sha256');if(hash('sha256',$manifest)!==SECB_S_MANIFEST_HASH)throw new RuntimeException('origin_manifest_changed');
    $hashes=[];foreach(explode("\n",trim($manifest)) as $line){if(preg_match('~^([a-f0-9]{64})  \./(.+)$~D',$line,$m))$hashes[$m[2]]=$m[1];}
    foreach(get_declared_classes() as $class)if(str_starts_with(strtolower($class),'siteengine\\callbridge\\')&&!isset($expected[$class]))throw new RuntimeException('unexpected_cb_class');
    foreach($expected as $class=>$path){if(!class_exists($class,false))throw new RuntimeException('candidate_class_missing');$r=new ReflectionClass($class);$file=str_replace('\\','/',$r->getFileName());if($file!==$root.'/'.$path||str_replace('\\','/',realpath($file)?:'')!==$file)throw new RuntimeException('candidate_class_wrong_origin');$hash=hash_file('sha256',$file);if(($hashes[$path]??null)!==$hash)throw new RuntimeException('class_payload_changed');$seen[$class]=['path'=>$file,'sha256'=>$hash];}return $seen;
}
function secb_standalone_load(string $run):array {
    secb_standalone_no_bootstrap();$verified=secb_standalone_verify($run);
    foreach(get_declared_classes() as $class)if(str_starts_with(strtolower($class),'siteengine\\callbridge\\'))throw new RuntimeException('preloaded_cb_class');
    foreach(secb_standalone_classes() as $path)require $verified['root'].'/'.$path;
    return secb_standalone_origins($verified['root']);
}
/** Parse literals only. Never eval/include wp-config or any external config. */
function secb_standalone_config(string $text):array {
    $tokens=token_get_all($text);$values=[];$prefix=null;
    $literal=static function(string $s):string {
        $quote=$s[0];$out='';$map=['n'=>"\n",'r'=>"\r",'t'=>"\t",'v'=>"\v",'e'=>chr(27),'f'=>"\f",'\\'=>'\\','"'=>'"','$'=>'$'];
        for($i=1;$i<strlen($s)-1;$i++){
            $c=$s[$i];if($c!=='\\'){$out.=$c;continue;}$next=$s[$i+1];
            if($quote==="'"){if($next==="'"||$next==='\\'){$out.=$next;$i++;}else $out.=$c;}
            else{if(isset($map[$next])){$out.=$map[$next];$i++;}elseif(($next>='0'&&$next<='9')||$next==='x'||$next==='u')throw new RuntimeException('complex_literal_requires_review');else $out.=$c;}
        }return $out;
    };
    $depth=0;
    $t=array_values(array_filter($tokens,static fn($t)=>!is_array($t)||!in_array($t[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)));
    for($i=0;$i<count($t);$i++){
        if($t[$i]==='{')$depth++;if($t[$i]==='}')$depth--;
        $top=$depth===0&&($i===0||$t[$i-1]===';'||(is_array($t[$i-1])&&$t[$i-1][0]===T_OPEN_TAG));
        if(is_array($t[$i])&&$t[$i][0]===T_STRING&&strtolower($t[$i][1])==='define'&&($t[$i+1]??null)==='('&&is_array($t[$i+2]??null)&&$t[$i+2][0]===T_CONSTANT_ENCAPSED_STRING){$name=$literal($t[$i+2][1]);if(!in_array($name,['DB_NAME','DB_USER','DB_PASSWORD','DB_HOST','SECB_CONFIG_FILE'],true))continue;if(!$top||($t[$i+3]??null)!==','||!is_array($t[$i+4]??null)||$t[$i+4][0]!==T_CONSTANT_ENCAPSED_STRING||($t[$i+5]??null)!==')'||isset($values[$name]))throw new RuntimeException('nonliteral_or_duplicate_config');$values[$name]=$literal($t[$i+4][1]);}
        if(is_array($t[$i])&&$t[$i][0]===T_VARIABLE&&$t[$i][1]==='$table_prefix'){if(!$top||($t[$i+1]??null)!=='='||!is_array($t[$i+2]??null)||$t[$i+2][0]!==T_CONSTANT_ENCAPSED_STRING||($t[$i+3]??null)!==';'||$prefix!==null)throw new RuntimeException('nonliteral_or_duplicate_prefix');$prefix=$literal($t[$i+2][1]);}
    }
    foreach(['DB_NAME','DB_USER','DB_PASSWORD','DB_HOST'] as $k)if(!isset($values[$k]))throw new RuntimeException('literal_config_required');
    if(!is_string($prefix)||!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new RuntimeException('literal_prefix_required');return $values+['prefix'=>$prefix];
}
function secb_standalone_private_tree(string $root,int $group):void {
    if(PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')||is_link($root)||realpath($root)!==$root)throw new RuntimeException('native_private_root_required');
    $items=[new SplFileInfo($root)];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $f)$items[]=$f;
    foreach($items as $f)if($f->isLink()||$f->getOwner()!==posix_geteuid()||$f->getGroup()!==$group||($f->getPerms()&0777)!==($f->isDir()?0700:0600))throw new RuntimeException('private_ownership_mode_required');
}
function secb_standalone_audit(string $site):array {
    $out=['wordpress_bootstrapped'=>false,'mu_and_dropins_executed'=>false,'files'=>[]];
    $paths=[$site.'/wp-config.php',$site.'/wp-settings.php'];foreach(['advanced-cache.php','object-cache.php','db.php','sunrise.php'] as $name)$paths[]=$site.'/wp-content/'.$name;
    foreach(glob($site.'/wp-content/mu-plugins/*.php')?:[] as $p)$paths[]=$p;
    foreach($paths as $p){if(is_link($p))throw new RuntimeException('audit_symlink_requires_review');$out['files'][$p]=is_file($p)?hash_file('sha256',$p):null;}
    $text=file_get_contents($site.'/wp-config.php');$counts=[];
    foreach(token_get_all($text) as $token)if(is_array($token)&&in_array($token[0],[T_INCLUDE,T_INCLUDE_ONCE,T_REQUIRE,T_REQUIRE_ONCE,T_EVAL],true)){$name=token_name($token[0]);$counts[$name]=($counts[$name]??0)+1;}
    $out['config_effect_tokens']=$counts;
    $out['custom_routing_requires_review']=is_file($site.'/wp-content/db.php')||is_file($site.'/wp-content/sunrise.php')||preg_match('/[\'"](?:WP_CONTENT_DIR|WP_PLUGIN_DIR|WPMU_PLUGIN_DIR)[\'"]/',$text)===1;
    $out['note']='File inventory only; no claim that skip-plugins prevents MU/drop-in/startup effects. This runner includes no WordPress bootstrap.';return $out;
}

function secb_standalone_check_workers(array $ready,array $results,string $root):array {
    $origins=secb_standalone_origins($root);
    foreach(['a','b'] as $label)if(($ready[$label]['candidate_origins']??null)!==$origins||($results[$label]['candidate_origins']??null)!==$origins)throw new RuntimeException('worker_candidate_origin_mismatch');
    return $origins;
}
function secb_standalone_restore_globals(mixed $original,bool $hadOriginal,mixed $db,string $prefix):void {
    if($db){$db->endCandidate();$db->prefix=$prefix;}
    if($hadOriginal)$GLOBALS['wpdb']=$original;else unset($GLOBALS['wpdb']);
    unset($GLOBALS['secb_standalone_config'],$GLOBALS['secb_standalone_context']);
}

function secb_standalone_check_audit(array $audit,array $argv,bool $required):string {
    $hash=hash('sha256',json_encode($audit,JSON_THROW_ON_ERROR));
    $ack=array_values(array_filter($argv,static fn($v)=>str_starts_with($v,'--audit-pin=')));
    if($required&&(count($ack)!==1||substr($ack[0],12)!==$hash))throw new RuntimeException('reviewed_bootstrap_inventory_pin_required');
    if($audit['custom_routing_requires_review']??true)throw new RuntimeException('custom_database_or_content_routing_requires_review');
    return $hash;
}

function secb_standalone_tooling(string $dir,string $hash):void {
    if(!preg_match('/^[a-f0-9]{64}$/D',$hash)||hash_file('sha256',$dir.'/STAGE-S-MANIFEST.sha256')!==$hash)throw new RuntimeException('independent_tooling_manifest_required');
    $expected=['STAGE-S-MANIFEST.sha256'];
    foreach(file($dir.'/STAGE-S-MANIFEST.sha256',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){
        if(!preg_match('~^([a-f0-9]{64})  \./([A-Za-z0-9_.-]+)$~D',$line,$m)||str_contains($m[2],'..')||in_array($m[2],$expected,true)||hash_file('sha256',$dir.'/'.$m[2])!==$m[1])throw new RuntimeException('tooling_payload_changed');$expected[]=$m[2];
    }
    $actual=array_values(array_diff(scandir($dir),['.','..']));sort($actual);sort($expected);
    if(count($expected)!==10||$actual!==$expected)throw new RuntimeException('unexpected_tooling_inventory');
}
