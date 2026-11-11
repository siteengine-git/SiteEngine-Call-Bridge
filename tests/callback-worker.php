<?php
/** Child-process adapter for shipped controllers (which emit and exit). No network/SQL. */
declare(strict_types=1);
$input=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
define('ABSPATH',__DIR__.'/fixture/');define('ARRAY_A','ARRAY_A');define('SECB_REST_NAMESPACE','siteengine-call/v1');
define('SECB_TWILIO_AUTH_TOKEN','fixture-signing-only');define('SECB_WEBHOOK_BASE_URL','https://fixture.invalid');define('SECB_DEBUG_LOG',false);
$_SERVER=['REQUEST_METHOD'=>$input['method']??'POST','REQUEST_URI'=>$input['uri']??'/wp-json/siteengine-call/v1/twilio/status','QUERY_STRING'=>parse_url($input['uri']??'',PHP_URL_QUERY)?:'','HTTP_X_TWILIO_SIGNATURE'=>$input['signature']??''];
$_POST=$input['post']??[];parse_str($_SERVER['QUERY_STRING'],$_GET);$GLOBALS['status']=200;
function wp_unslash($x){return is_array($x)?array_map('wp_unslash',$x):stripslashes((string)$x);}
function status_header($n){$GLOBALS['status']=$n;}
function update_option(...$a){} function wp_json_encode($x,$flags=0){return json_encode($x,$flags);}
function home_url(){return 'https://fixture.invalid';}function mb_substr($s,$start,$len){return substr($s,$start,$len);}
class WP_REST_Request {function get_param($n){return $_GET[$n]??$_POST[$n]??null;}}
class CallbackDb {
 public array $rows;public array $writes=[];public string $last_error='';public int $reads=0;public int $attempts=0;public int $insert_id=10;public string $prefix='fixture_';
 function __construct(array $rows){$this->rows=$rows;}
 function prepare($sql,...$args){foreach($args as $v)$sql=preg_replace('/%[sd]/',"'".str_replace("'","''",(string)$v)."'",$sql,1);return $sql;}
 function get_row($sql,$format=null){$this->last_error='';if(str_contains($sql,'se_cb_calls')){$this->reads++;if(in_array($this->reads,$GLOBALS['callback_input']['read_failures']??[],true)){$this->last_error='fixture read failure';return null;}preg_match("/twilio_call_sid='([^']+)'/",$sql,$m);foreach($this->rows as $r)if($r['twilio_call_sid']===($m[1]??''))return $r;return null;}if(str_contains($sql,'tracking_numbers'))return ['id'=>1,'min_duration_sec'=>60,'phone_number'=>'+10000000081','forward_to'=>'+10000000089','record_calls'=>0,'source'=>'fixture','repeat_window_days'=>30];return null;}
 function get_var($sql){return 0;}
 function update($table,$data,$where,...$rest){$this->last_error='';$this->attempts++;if(in_array($this->attempts,$GLOBALS['callback_input']['write_failures']??[],true)){$this->last_error='fixture write failure';return false;}if($GLOBALS['callback_input']['always_conflict']??false)return 0;foreach($this->rows as &$r)if($r['id']==$where['id']){if(isset($GLOBALS['callback_race'])){$r=array_replace($r,$GLOBALS['callback_race']);unset($GLOBALS['callback_race']);}foreach($where as $k=>$v)if(($r[$k]??null)!==$v)return 0;$r=array_replace($r,$data);$this->writes[]=$data;return 1;}return 0;}
 function insert($table,$data,...$rest){$data['id']=$this->insert_id++;$this->rows[]=$data;$this->writes[]=$data;return 1;}
}
$GLOBALS['callback_input']=$input;
$wpdb=new CallbackDb($input['rows']??[]);
$GLOBALS['callback_race']=$input['race']??null;
register_shutdown_function(static function()use($argv){file_put_contents($argv[2],json_encode(['status'=>$GLOBALS['status'],'rows'=>$GLOBALS['wpdb']->rows,'writes'=>$GLOBALS['wpdb']->writes,'reads'=>$GLOBALS['wpdb']->reads,'attempts'=>$GLOBALS['wpdb']->attempts],JSON_THROW_ON_ERROR));});
foreach(['Support/CallCapture.php','Support/Config.php','Support/Logger.php','Support/WebhookEvents.php','Support/Phone.php','Twilio/SignatureValidator.php','Twilio/TwiMLBuilder.php','DB/CallLogRepository.php','DB/TrackingNumberRepository.php','DB/AttributionSessionRepository.php','DB/NumberLeaseRepository.php'] as $p)require dirname(__DIR__).'/src/'.$p;
if($input['mode']==='signature'){echo json_encode(SiteEngine\CallBridge\Twilio\SignatureValidator::validateCurrentRequest());exit;}
$map=['status'=>'TwilioStatusController','recording'=>'TwilioRecordingController','voice'=>'TwilioVoiceController'];$name=$map[$input['mode']];require dirname(__DIR__).'/src/Webhook/'.$name.'.php';$class='SiteEngine\CallBridge\Webhook\\'.$name;(new $class())->handle(new WP_REST_Request());
