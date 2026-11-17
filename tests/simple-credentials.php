<?php
/** Real installed-contract Base service/Sodium; memory storage and WP auth adapters. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/fixture/');define('ARRAY_A','ARRAY_A');define('SECB_REST_NAMESPACE','siteengine-call/v1');
class WP_Error {function __construct(private string$c,private string$m='',private mixed$data=null){}function get_error_code(){return $this->c;}function get_error_message(){return $this->m;}function get_error_data(){return $this->data;}}
function is_wp_error($v){return $v instanceof WP_Error;}function __($s,$d=''){return $s;}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}function esc_attr($s){return esc_html($s);}function esc_url($s){return esc_html($s);}function esc_html__($s,$d=''){return esc_html($s);}function esc_attr__($s,$d=''){return esc_attr($s);}
$allowed=true;function current_user_can($c){return $GLOBALS['allowed']&&$c==='manage_options';}function get_current_user_id(){return 1;}function wp_verify_nonce($n,$a){return $n==='fixture-nonce'&&$a==='siteengine-credentials-v1';}
function wp_generate_uuid4(){$h=bin2hex(random_bytes(16));return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-8'.substr($h,17,3).'-'.substr($h,20,12);}
function add_query_arg($a,$url){return $url.(str_contains($url,'?')?'&':'?').http_build_query($a);} function add_action(...$a){}function wp_nonce_field($a){echo '<input name="_wpnonce" value="fixture-nonce">';}function admin_url($p){return 'https://fixture.invalid/wp-admin/'.$p;}function submit_button($s,...$a){echo '<button>'.esc_html($s).'</button>';}
class SiteEngine_Base_Admin {const PARENT_SLUG='siteengine-options';}
$base=$argv[1]??'';foreach(['class-credential-registry.php','class-credentials.php','class-credential-admin.php']as$f)require $base.'/includes/'.$f;
require dirname(__DIR__).'/src/Integration/BaseCredentials.php';require dirname(__DIR__).'/src/Support/Config.php';
class OnboardingStore {public array$rows=[],$audit=[];public bool$failNext=false;function install(){return true;}function load($id){return $this->rows[$id]??null;}function save($id,$revision,$envelope,$audit){if($this->failNext){$this->failNext=false;return new WP_Error('storage_unavailable');}if(($this->rows[$id]['revision']??0)!==$revision)return new WP_Error('revision_conflict');$this->rows[$id]=['revision'=>$revision+1,'envelope'=>$envelope];$this->audit[]=$audit;return true;}}
$store=new OnboardingStore();$installation='11111111-1111-4111-8111-111111111111';$service=new SiteEngine_Base_Credentials($store,static fn()=>['installation'=>$installation,'active_key'=>'fixture','keys'=>['fixture'=>str_repeat('k',32)]]);
// Test-only injection isolates real facade calls from any host database/key provider.
$rp=new ReflectionProperty(SiteEngine_Base_Credentials::class,'service');$rp->setValue(null,$service);
SiteEngine\CallBridge\Integration\BaseCredentials::register();$_SERVER['REQUEST_METHOD']='POST';$pass=0;
function check($ok,$name){global$pass;if(!$ok)throw new RuntimeException($name);$pass++;echo 'PASS '.$name."\n";}
function savePair($candidate,array$extra=[]){global$service,$installation;$meta=$service->metadata('call-bridge','twilio-webhook','default');$post=['module'=>'call-bridge','credential'=>'twilio-webhook','resource'=>'default','operation'=>'save','candidate'=>$candidate,'expected_revision'=>(string)$meta['revision'],'operation_id'=>wp_generate_uuid4(),'_wpnonce'=>'fixture-nonce','installation'=>$installation];return SiteEngine_Base_Credential_Admin::process(array_replace($post,$extra),$service);}
function currentPair(){return SiteEngine\CallBridge\Integration\BaseCredentials::pair('webhook');}
$pair=['account_sid'=>'AC'.str_repeat('a',32),'auth_token'=>str_repeat('b',32)];
check(currentPair()===null,'initial required pair absent');
$result=savePair($pair);check(!is_wp_error($result)&&currentPair()===$pair,'ordinary Save validates and activates complete pair');
$metadata=$service->metadata('call-bridge','twilio-webhook','default');check($metadata['present']&&empty($metadata['staged_version']),'ordinary Save leaves no unactivated stage');
$before=$store->rows;$audit=$store->audit;$result=savePair(['account_sid'=>'','auth_token'=>'']);check(!is_wp_error($result)&&$store->rows===$before&&$store->audit===$audit,'both blank retains active pair without writes');
foreach([['account_sid'=>$pair['account_sid'],'auth_token'=>''],['account_sid'=>'invalid','auth_token'=>$pair['auth_token']],['account_sid'=>$pair['account_sid'],'auth_token'=>'********************************']]as$invalid){$result=savePair($invalid);check(is_wp_error($result)&&$store->rows===$before&&currentPair()===$pair,'invalid or partial Save preserves active pair');}
$replacement=array_replace($pair,['auth_token'=>str_repeat('c',32)]);$result=savePair($replacement);check(!is_wp_error($result)&&currentPair()===$replacement,'same-account complete replacement succeeds');
$before=$store->rows;$store->failNext=true;$result=savePair($pair);check(is_wp_error($result)&&$store->rows===$before&&currentPair()===$replacement,'modeled precommit storage failure preserves active pair');
$result=savePair($pair,['expected_revision'=>'0']);check(is_wp_error($result)&&$store->rows===$before,'stale revision cannot overwrite active pair');
$result=savePair($pair,['_wpnonce'=>'bad']);check(is_wp_error($result)&&$store->rows===$before,'invalid nonce preserves active pair');
$allowed=false;$result=savePair($pair);check(is_wp_error($result)&&$store->rows===$before,'non-administrator Save denied without writes');$allowed=true;
check(!str_contains(json_encode($store->rows),$replacement['auth_token']),'Save uses real Base encryption');
$_GET=['module'=>'call-bridge','credential'=>'twilio-webhook','resource'=>'default'];ob_start();SiteEngine_Base_Credential_Admin::render();$html=ob_get_clean();
check(str_contains($html,'Twilio Account SID')&&str_contains($html,'Twilio Auth Token')&&str_contains($html,'Save credentials'),'ordinary rendered field and Save labels');
check(!str_contains($html,$replacement['auth_token'])&&!str_contains($html,$pair['account_sid']),'rendered secrets never prefilled');
check(!str_contains($html,'Resource identifier')&&!str_contains($html,'value="activate"')&&!str_contains($html,'value="stage"'),'ordinary setup hides internal resource and lifecycle controls');
check(str_contains($html,'Advanced')&&str_contains($html,'twilio-playback'),'optional playback group under Advanced');
$changed=array_replace($replacement,['account_sid'=>'AC'.str_repeat('d',32)]);$before=$store->rows;
$result=savePair($changed);check(is_wp_error($result)&&$store->rows===$before&&currentPair()===$replacement,'account change requires explicit intent before mutation');
$result=savePair($changed,['confirm_changes'=>'yes']);check(!is_wp_error($result)&&currentPair()===$changed,'confirmed account change supported without immutable SID binding');
$other=siteengine_register_credentials_v1('cb-test-other','legacy-secret',['label'=>'Other owned test group','kind'=>'secret','purposes'=>['runtime'],'allow_replace'=>true]);
check(!is_wp_error($other),'legacy group still registers');
$context=['nonce'=>'fixture-nonce','installation'=>$installation];$base=['module'=>'cb-test-other','credential'=>'legacy-secret','resource'=>'default','expected_revision'=>0,'operation_id'=>wp_generate_uuid4(),'operation'=>'stage','candidate'=>'synthetic-legacy-group-only'];
$staged=siteengine_credentials_command_v1($base,$context);$base['operation']='activate';unset($base['candidate']);$base['expected_revision']=$staged['revision'];$base['version_id']=$staged['staged_version'];$base['operation_id']=wp_generate_uuid4();$active=siteengine_credentials_command_v1($base,$context);
check(!is_wp_error($active)&&siteengine_credentials_runtime_read_v1('cb-test-other','legacy-secret','default','runtime')['value']==='synthetic-legacy-group-only'&&currentPair()===$changed,'legacy staged group works independently of CB simple Save');
echo "$pass PASS / 0 FAIL; actual Base service/Sodium; modeled WP/storage boundary, not native SQL\n";
