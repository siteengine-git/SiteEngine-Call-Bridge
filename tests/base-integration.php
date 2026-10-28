<?php
/** Isolated fixtures: no WordPress installation, SQL server, network or writes. */
error_reporting(E_ALL);
set_error_handler(static function($n,$s,$file,$line){throw new ErrorException($s,0,$n,$file,$line);});
$base=$argv[1]??'';$mode=$argv[2]??'base';
define('ABSPATH',__DIR__.'/fixture/');
define('ARRAY_A','ARRAY_A');
define('SECB_CONFIG_FILE',__DIR__.'/intentionally-absent-config.php');
define('SECB_TWILIO_ACCOUNT_SID','fixture-account-sentinel');
define('SECB_TWILIO_AUTH_TOKEN','fixture-token-sentinel');
define('SECB_TWILIO_API_KEY_SID','fixture-key-sentinel');
define('SECB_TWILIO_API_KEY_SECRET','fixture-key-secret-sentinel');
define('SECB_WEBHOOK_BASE_URL',$mode==='unsafe-url'?'https://credential-user:fixture-url-secret@fixture.invalid/?token=fixture-query-secret':'https://fixture.invalid/subsite');
$GLOBALS['hooks']=[];$GLOBALS['caps']=['manage_options'=>true];$GLOBALS['registered']=[];$GLOBALS['submenu']=[];$GLOBALS['mutations']=[];$GLOBALS['reads']=0;
$GLOBALS['number_counts']=['total'=>'2','active'=>'2','incomplete'=>'0','recording'=>'0'];
function add_filter($n,$cb,$priority=10,$args=1){$GLOBALS['hooks'][$n][$priority][]=$cb;}
function add_action($n,$cb,$priority=10,$args=1){add_filter($n,$cb,$priority,$args);}
function apply_filters($n,$value){$h=$GLOBALS['hooks'][$n]??[];ksort($h);foreach($h as $cbs)foreach($cbs as $cb)$value=$cb($value);return $value;}
function do_action($n){$h=$GLOBALS['hooks'][$n]??[];ksort($h);foreach($h as $cbs)foreach($cbs as $cb)$cb();}
function __($s,$d=''){return $s;}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return esc_html($s);}
function esc_html__($s,$d=''){return esc_html($s);}
function esc_attr__($s,$d=''){return esc_html($s);}
function esc_url($s){return preg_match('~^https?://~i',(string)$s)?esc_html($s):'';}
function sanitize_key($s){return preg_replace('/[^a-z0-9_-]/','',strtolower($s));}
function sanitize_html_class($s){return preg_replace('/[^A-Za-z0-9_-]/','',$s);}
function wp_unslash($s){return $s;}
function current_user_can($cap){return !empty($GLOBALS['caps'][$cap]);}
function wp_die($s){throw new RuntimeException('permission-denied');}
function is_admin(){return true;}
function plugin_dir_path($s){return dirname($s).'/';}
function plugin_dir_url($s){return 'https://fixture.invalid/plugins/';}
function register_activation_hook($a,$b){}
function register_deactivation_hook($a,$b){}
function admin_url($s){return 'https://fixture.invalid/wp-admin/'.$s;}
function home_url(){return 'https://fixture.invalid/subsite';}
function get_option($n,$default=''){return $n==='se_cb_db_version'?'1.0.0':$default;}
function wp_next_scheduled($n){return 1234567890;}
function wp_list_pluck($a,$k){return array_column($a,$k);}
function trailingslashit($s){return rtrim($s,'/\\').'/';}
function get_bloginfo($s){return 'Fixture';}
function wp_strip_all_tags($s){return strip_tags($s);}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function get_file_data($file,$headers){
    $s=file_get_contents($file);$out=[];
    foreach($headers as $k=>$name)$out[$k]=preg_match('/^[ \t\/*#@]*'.preg_quote($name,'/').':\s*(.+)$/mi',$s,$m)?trim($m[1]):'';
    return $out;
}
function add_menu_page($title,$menu,$cap,$slug,$render,$icon='',$pos=0){$GLOBALS['registered'][]=['kind'=>'top','parent'=>null,'slug'=>$slug,'cap'=>$cap,'render'=>$render];}
function add_submenu_page($parent,$title,$menu,$cap,$slug,$render,$pos=null){
    $GLOBALS['registered'][]=['kind'=>'sub','parent'=>$parent,'slug'=>$slug,'cap'=>$cap,'render'=>$render];
    $GLOBALS['submenu'][$parent][]=[$menu,$cap,$slug];return $parent.'_page_'.$slug;
}
function remove_menu_page($slug){}
function mutation($n){$GLOBALS['mutations'][]=$n;throw new RuntimeException('Forbidden mutation/HTTP '.$n);}
function update_option(...$a){return mutation(__FUNCTION__);}
function add_option(...$a){return mutation(__FUNCTION__);}
function delete_option(...$a){return mutation(__FUNCTION__);}
function set_transient(...$a){return mutation(__FUNCTION__);}
function delete_transient(...$a){return mutation(__FUNCTION__);}
function wp_schedule_event(...$a){return mutation(__FUNCTION__);}
function wp_clear_scheduled_hook(...$a){return mutation(__FUNCTION__);}
function wp_remote_get(...$a){return mutation(__FUNCTION__);}
function wp_remote_post(...$a){return mutation(__FUNCTION__);}
function get_field(...$a){return mutation('direct-acf-access');}
class FixtureDb {
    public string $prefix='wp_';
    public function esc_like($s){return addcslashes($s,'_%\\');}
    public function prepare($s,$arg){return str_replace('%s',"'".$arg."'",$s);}
    public function get_var($s){++$GLOBALS['reads'];if(!preg_match("/^SHOW TABLES LIKE '(.+)'$/",$s,$m))return mutation('unexpected-query');return str_replace('\\','',$m[1]);}
    public function get_row($s,$format){++$GLOBALS['reads'];if(!str_starts_with($s,'SELECT COUNT(*) AS total'))return mutation('unexpected-row-query');return $GLOBALS['number_counts'];}
    public function __call($n,$a){return mutation('wpdb-'.$n);}
}
$GLOBALS['wpdb']=new FixtureDb();
class FixtureInstaller {
    const CRON_GC='se_cb_gc';public static int $boots=0;
    public static function maybeUpgrade(){++self::$boots;}
    public static function runGc(){return mutation('gc');}
}
class_alias(FixtureInstaller::class,'SiteEngine\\CallBridge\\Installer');
if($mode==='base'){
    define('SEBASE_PLUGIN_DIR',rtrim($base,'/\\').'/');
    class SiteEngine_Base_ACF_Registration {const PAGE_SLUG='business-data';}
    class SiteEngine_Base_ACF_Provider {
        public static function get_value($k){return ['bd_business_name'=>'<img src=x onerror=fixture>','bd_phone_number'=>'PUBLIC-ROLE','bd_forwarding_phone'=>'SHARED-UNCONFIRMED','bd_privacy_policy_url'=>'javascript:fixture-danger'][$k]??'';}
    }
    require $base.'/includes/class-shared-config.php';
    require $base.'/includes/class-module-registry.php';
    require $base.'/includes/class-admin.php';
    require $base.'/includes/class-readme-page.php';
    SiteEngine_Base_Admin::init();SiteEngine_Base_Readme_Page::init();
}
require dirname(__DIR__).'/siteengine-call-bridge.php';
do_action('plugins_loaded');
use SiteEngine\CallBridge\Integration\BaseIntegration as Integration;
use SiteEngine\CallBridge\Support\Readiness;
use SiteEngine\CallBridge\Support\SharedBusinessContext;
use SiteEngine\CallBridge\Admin\SettingsPage;
$passed=0;$failed=0;
function check($ok,$name){global $passed,$failed;if($ok){++$passed;echo "PASS $name\n";}else{++$failed;echo "FAIL $name\n";}}
function capture($fn){ob_start();try{$fn();return ob_get_contents();}finally{ob_end_clean();}}
function denied($fn){try{$fn();return false;}catch(RuntimeException $e){return $e->getMessage()==='permission-denied';}}
check(SECB_VERSION==='0.1.2'&&SECB_DB_VERSION==='1.0.0','candidate release and unchanged DB metadata');
check(FixtureInstaller::$boots===1,'boot still invokes installer once (isolated adapter, not real migration)');
check(isset($GLOBALS['hooks']['siteengine_modules']),'actual plugin boot registers module before admin_menu');
check(isset($GLOBALS['hooks']['admin_post_secb_save_number'],$GLOBALS['hooks']['admin_post_secb_delete_number']),'existing save/delete action hooks remain');
do_action('admin_menu');
$modules=apply_filters('siteengine_modules',[]);
check(isset($modules['call-bridge'])&&$modules['call-bridge']['page_slug']==='secb-settings','module destination preserves settings slug');
check($modules['call-bridge']['requirements']===[]&&$modules['call-bridge']['capability']==='manage_options','shared facts optional; page capability explicit');
check(isset(Integration::modules(['foreign'=>['title'=>'Foreign']])['foreign']),'module filter preserves unrelated registrations');
foreach(['secb-settings','secb-numbers','secb-calls'] as $slug){
    $pages=array_values(array_filter($GLOBALS['registered'],fn($p)=>$p['kind']==='sub'&&$p['slug']===$slug));
    check(count($pages)===1,'one submenu registration for '.$slug);
    check($pages[0]['parent']===($mode==='base'?'siteengine-options':'secb-numbers'),'correct parent for '.$slug);
    check(is_callable($pages[0]['render'])&&$pages[0]['cap']==='manage_options','working guarded renderer for '.$slug);
}
check(count(array_filter($GLOBALS['registered'],fn($p)=>$p['kind']==='top'&&$p['slug']==='secb-numbers'))===($mode==='base'?0:1),'no duplicate top menu; standalone fallback');
$f=Readiness::inspect();
check($f['numbers']===['total'=>2,'active'=>2,'incomplete'=>0,'recording'=>0],'inspection reads only aggregate routing counts');
$before=$GLOBALS['reads'];Readiness::current();$after=$GLOBALS['reads'];Readiness::current();
check($GLOBALS['reads']===$after&&$after>$before,'request-local inspection cache avoids duplicate queries');
$good=$f;$good['external']='readable';$good['extensions']=array_fill_keys(array_keys($f['extensions']),true);$good['url']='https://fixture.invalid';
$good['account']=true;$good['token']=true;$good['playback']=false;
$state=Readiness::evaluate($good);
check($state['configuration_complete']&&str_contains($state['label'],'calling unverified'),'complete configuration never claims live acceptance');
check(str_contains(json_encode($state),'Not required while recording is off'),'optional media credentials not required with recording off');
$cases=[
 'missing-account'=>['account'=>false], 'missing-token'=>['token'=>false],
 'unreadable-file'=>['external'=>'unreadable'], 'missing-db-version'=>['db_current'=>false],
 'missing-table'=>['tables'=>['tracking_numbers'=>false]], 'missing-extension'=>['extensions'=>['mbstring'=>false]],
 'missing-gc'=>['gc'=>false], 'no-numbers'=>['numbers'=>['total'=>0,'active'=>0,'incomplete'=>0,'recording'=>0]],
 'inactive-numbers'=>['numbers'=>['total'=>2,'active'=>0,'incomplete'=>0,'recording'=>0]],
 'incomplete-route'=>['numbers'=>['total'=>2,'active'=>2,'incomplete'=>1,'recording'=>0]],
 'failed-read'=>['inspection_error'=>true], 'unknown-counts'=>['numbers'=>null],
 'recording-without-playback'=>['numbers'=>['total'=>2,'active'=>2,'incomplete'=>0,'recording'=>1]],
];
foreach($cases as $name=>$change)check(!Readiness::evaluate(array_replace($good,$change))['configuration_complete'],$name.' stays incomplete');
check(Readiness::evaluate(array_replace($good,['external'=>'not_defined']))['configuration_complete'],'existing inline constants remain supported');
$pass=Readiness::evaluate(array_replace($good,['passthrough'=>true]));
check(str_contains($pass['label'],'Passthrough')&&str_contains($pass['label'],'unverified'),'passthrough warning preserves acceptance limit');
check(str_contains(json_encode(Readiness::evaluate(array_replace($good,['debug'=>false]))),'"state":"Off"'),'debug false is off, not missing');
$context=SharedBusinessContext::read();
check($context['phone']['value']===($mode==='base'?'PUBLIC-ROLE':''),'public phone uses guarded Base facade');
check($context['forwarding_phone']['value']===($mode==='base'?'SHARED-UNCONFIRMED':''),'forwarding field remains separate reference');
$html=capture([SettingsPage::class,'render']);
check(str_contains($html,'Setup &amp; Status')&&str_contains($html,'Tracking Numbers')&&str_contains($html,'Call Log'),'administrator setup renderer and navigation');
check(!str_contains($html,'<form')&&!str_contains($html,'type="password"'),'no settings or credential editor');
foreach(['fixture-account-sentinel','fixture-token-sentinel','fixture-key-sentinel','fixture-key-secret-sentinel','fixture-url-secret','fixture-query-secret'] as $secret)check(!str_contains($html,$secret),'redacts '.$secret);
check(!str_contains($html,'<img src=x')&&!str_contains($html,'javascript:fixture-danger'),'shared text escaped and unsafe policy URL removed');
check(str_contains($html,'forward_to')&&str_contains($html,'unconfirmed reference'),'phone roles explicitly separated without routing substitution');
$urls=Readiness::webhookUrls();
check($mode==='unsafe-url'?$urls===[]:count($urls)===4,'unsafe webhook base suppressed; safe base yields four generated URLs');
if($mode!=='unsafe-url'){
 check($urls[0]['url']==='https://fixture.invalid/subsite/wp-json/siteengine-call/v1/twilio/voice'&&$urls[0]['method']==='POST','exact runtime-compatible subdirectory Voice URL and POST');
 check(array_column($urls,'method')===['POST','POST','POST','GET'],'callback/whisper methods match runtime');
}
$dirs=Integration::helpDirectories(['foreign/']);check(Integration::helpDirectories($dirs)===$dirs&&$dirs[0]==='foreign/','help directories append idempotently');
if($mode==='base'){
 $module=SiteEngine_Base_Module_Registry::get_modules()['call-bridge'];
 check(SiteEngine_Base_Module_Registry::get_status($module)['status']==='warning','actual original Base receives warning, not setup_required');
 $method=new ReflectionMethod('SiteEngine_Base_Admin','render_module_card');
 $card=capture(fn()=>$method->invoke(null,$module));
 check(str_contains($card,'page=secb-settings')&&str_contains($card,'>Open</a>')&&!str_contains($card,'Complete Business Data'),'original Base card opens CB without wrong Business Data CTA');
 $tabs=apply_filters('siteengine_readme_tabs',[]);
 check(isset($tabs['call-bridge'])&&$tabs['call-bridge']['order']===40,'actual original Base folder scanner registers CB Help');
 $foreign=['call-bridge'=>['title'=>'Existing','callback'=>static function(){},'order'=>1]];
 check(SiteEngine_Base_Readme_Page::register_folder_tabs($foreign)['call-bridge']['title']==='Existing','folder scanner preserves existing Help slug on collision');
 $_GET['tab']='call-bridge';$help=capture(['SiteEngine_Base_Readme_Page','render_page']);
 check(str_contains($help,'Call Bridge operations guide')&&str_contains($help,'read-me-file'),'actual original Base Help renderer/deep link');
}else{
 $_GET['view']='help';$help=capture([SettingsPage::class,'render']);unset($_GET['view']);
 check(str_contains($help,'Call Bridge operations guide')&&str_contains(Integration::helpUrl(),'view=help'),'standalone complete Help fallback');
}
foreach(['localStorage','pool sizing','Whispers','Duration qualification','Recording defaults','GC','Uninstall','consent','CRM inquiry','call export'] as $topic){
 check(stripos($help,$topic)!==false,'Help covers '.$topic);
}
$unknown=Readiness::evaluate(array_replace($good,['numbers'=>null]));check(!str_contains(json_encode($unknown),'Not required while recording is off'),'failed count read never claims recording is disabled');
foreach(['fixture-token-sentinel','fixture-key-secret-sentinel','fixture-url-secret'] as $secret)check(!str_contains($help,$secret),'Help redacts '.$secret);
$GLOBALS['caps']=['read'=>true];check(denied([SettingsPage::class,'render']),'read-only role denied Setup & Status');
$GLOBALS['caps']=[];
check(denied([SettingsPage::class,'render']),'non-administrator setup denied before reads');
$nohelp=capture(static function(){include SECB_PATH.'readme-tabs/call-bridge.php';});
check($nohelp==='','direct Help include denies non-administrator');
$reads=$GLOBALS['reads'];Integration::status();check($GLOBALS['reads']===$reads,'non-administrator status does not inspect configuration');
check($GLOBALS['mutations']===[],'zero settings/schema/schedule/HTTP/direct-ACF mutations');
echo "RESULT mode=$mode PHP=".PHP_VERSION." $passed PASS / $failed FAIL\n";
exit($failed?1:0);
