<?php
/** Child-process adapter for shipped controllers (which emit and exit). No network/SQL. */
declare(strict_types=1);
$input=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
define('ABSPATH',$input['webroot']??(__DIR__.'/fixture/'));define('ARRAY_A','ARRAY_A');define('SECB_REST_NAMESPACE','siteengine-call/v1');
if(isset($input['gateway_clients'])){define('SECB_GATEWAY_CLIENTS',$input['gateway_clients']);define('SECB_GATEWAY_NONCE_DIR',$input['nonce_dir']);}
define('SECB_TWILIO_AUTH_TOKEN','fixture-signing-only');define('SECB_WEBHOOK_BASE_URL','https://fixture.invalid');define('SECB_DEBUG_LOG',false);
$_SERVER=['REQUEST_METHOD'=>$input['method']??'POST','REQUEST_URI'=>$input['uri']??'/wp-json/siteengine-call/v1/twilio/status','QUERY_STRING'=>parse_url($input['uri']??'',PHP_URL_QUERY)?:'','HTTP_X_TWILIO_SIGNATURE'=>$input['signature']??''];
$_POST=$input['post']??[];parse_str($_SERVER['QUERY_STRING'],$_GET);$GLOBALS['status']=200;
$_SERVER=array_replace($_SERVER,$input['server_headers']??[]);
function wp_unslash($x){return is_array($x)?array_map('wp_unslash',$x):stripslashes((string)$x);}
function wp_slash($x){return is_array($x)?array_map('wp_slash',$x):addslashes((string)$x);}
function add_action($hook,$cb,$priority=10){$GLOBALS['fixture_actions'][$hook][$priority][]=$cb;}
register_shutdown_function(static function(){ $a=$GLOBALS['fixture_actions']['shutdown']??[];ksort($a);foreach($a as$list)foreach($list as$cb)$cb(); });
function status_header($n){$GLOBALS['status']=$n;}
function update_option(...$a){} function wp_json_encode($x,$flags=0){return json_encode($x,$flags);}
function home_url(){return 'https://fixture.invalid';}function mb_substr($s,$start,$len){return substr($s,$start,$len);}
class WP_REST_Request {public array $query=[],$body=[];function __construct(...$args){}function get_param($n){return $this->body[$n]??$this->query[$n]??$_GET[$n]??$_POST[$n]??null;}function set_query_params($v){$this->query=$v;}function set_body_params($v){$this->body=$v;}function get_body(){return $GLOBALS['callback_input']['envelope']??'';}function get_header($n){return $GLOBALS['callback_input']['handoff_signature']??'';}}
class CallbackDb {
 public array $rows;public array $writes=[];public string $last_error='';public int $reads=0;public int $attempts=0;public int $insert_id=10;public string $prefix='fixture_';
 function __construct(array $rows){$this->rows=$rows;}
 function prepare($sql,...$args){foreach($args as $v)$sql=preg_replace('/%[sd]/',"'".str_replace("'","''",(string)$v)."'",$sql,1);return $sql;}
 function get_row($sql,$format=null){$this->last_error='';if(str_contains($sql,'se_cb_calls')){$this->reads++;if(in_array($this->reads,$GLOBALS['callback_input']['read_failures']??[],true)){$this->last_error='fixture read failure';return null;}preg_match("/twilio_call_sid='([^']+)'/",$sql,$m);foreach($this->rows as $r)if($r['twilio_call_sid']===($m[1]??''))return $r;return null;}if(str_contains($sql,'tracking_numbers'))return array_replace(['id'=>1,'min_duration_sec'=>60,'phone_number'=>'+10000000081','forward_to'=>'+10000000089','record_calls'=>0,'source'=>'fixture','repeat_window_days'=>30],$GLOBALS['callback_input']['number']??[]);return null;}
 function get_var($sql){return 0;}
 function update($table,$data,$where,...$rest){$this->last_error='';$this->attempts++;if(in_array($this->attempts,$GLOBALS['callback_input']['write_failures']??[],true)){$this->last_error='fixture write failure';return false;}if($GLOBALS['callback_input']['always_conflict']??false)return 0;foreach($this->rows as &$r)if($r['id']==$where['id']){if(isset($GLOBALS['callback_race'])){$r=array_replace($r,$GLOBALS['callback_race']);unset($GLOBALS['callback_race']);}foreach($where as $k=>$v)if(($r[$k]??null)!==$v)return 0;$r=array_replace($r,$data);$this->writes[]=$data;return 1;}return 0;}
 function insert($table,$data,...$rest){$data['id']=$this->insert_id++;$this->rows[]=$data;$this->writes[]=$data;return 1;}
}
$GLOBALS['callback_input']=$input;
$wpdb=new CallbackDb($input['rows']??[]);
$GLOBALS['callback_race']=$input['race']??null;
register_shutdown_function(static function()use($argv){file_put_contents($argv[2],json_encode(['status'=>$GLOBALS['status'],'rows'=>$GLOBALS['wpdb']->rows,'writes'=>$GLOBALS['wpdb']->writes,'reads'=>$GLOBALS['wpdb']->reads,'attempts'=>$GLOBALS['wpdb']->attempts,'gateway_context_cleared'=>!class_exists('SiteEngine\\CallBridge\\Gateway\\Context',false)||!SiteEngine\CallBridge\Gateway\Context::active(),'method_restored'=>($_SERVER['REQUEST_METHOD']??'')===($GLOBALS['callback_input']['method']??'POST')],JSON_THROW_ON_ERROR));});
foreach(['Support/CallCapture.php','Support/Config.php','Support/Logger.php','Support/WebhookEvents.php','Support/Phone.php','Twilio/SignatureValidator.php','Twilio/TwiMLBuilder.php','DB/CallLogRepository.php','DB/TrackingNumberRepository.php','DB/AttributionSessionRepository.php','DB/NumberLeaseRepository.php'] as $p)require dirname(__DIR__).'/src/'.$p;
if($input['mode']==='signature'){echo json_encode(SiteEngine\CallBridge\Twilio\SignatureValidator::validateCurrentRequest());exit;}
if($input['mode']==='gateway'){
 foreach(['Gateway/Protocol.php','Gateway/Context.php','Gateway/Receiver.php','Webhook/TwilioVoiceController.php','Webhook/TwilioStatusController.php','Webhook/TwilioRecordingController.php','Webhook/TwilioWhisperController.php','Webhook/GatewayHandoffController.php']as$p)require dirname(__DIR__).'/src/'.$p;
 (new SiteEngine\CallBridge\Webhook\GatewayHandoffController())->handle(new WP_REST_Request());exit;
}
$map=['status'=>'TwilioStatusController','recording'=>'TwilioRecordingController','voice'=>'TwilioVoiceController'];$name=$map[$input['mode']];require dirname(__DIR__).'/src/Webhook/'.$name.'.php';$class='SiteEngine\CallBridge\Webhook\\'.$name;(new $class())->handle(new WP_REST_Request());
