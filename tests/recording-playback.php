<?php
/** Actual proxy in fresh processes; WordPress/database/HTTP adapters, no provider traffic. */
declare(strict_types=1);
if (isset($argv[1])) {
    $in=json_decode($argv[1],true,512,JSON_THROW_ON_ERROR);
    define('ABSPATH',__DIR__.'/fixture/');define('ARRAY_A','ARRAY_A');
    define('SECB_TWILIO_ACCOUNT_SID','AC'.str_repeat('a',32));define('SECB_TWILIO_AUTH_TOKEN','synthetic-only');
    $GLOBALS['status']=200;$GLOBALS['fetches']=[];
    function is_user_logged_in(){global $in;return $in['logged']??true;}
    function current_user_can($cap){global $in;return $in['admin']??true;}
    function wp_verify_nonce($v,$action){return $v==='fixture-nonce'&&$action==='wp_rest';}
    function status_header($s){$GLOBALS['status']=$s;}
    function nocache_headers(){}
    function wp_remote_get($url,$args){global $in;$GLOBALS['fetches'][]=['url'=>$url,'args'=>$args];return ['code'=>$in['upstream']??200,'body'=>(($in['oversize']??false)?'RIFFxxxxWAVE'.str_repeat('x',20971521):($in['body']??'RIFFxxxxWAVEfixture'))];}
    function is_wp_error($r){return false;}
    function wp_remote_retrieve_response_code($r){return $r['code'];}
    function wp_remote_retrieve_body($r){return $r['body'];}
    class WP_REST_Request {function get_param($k){global $in;return $k==='id'?($in['id']??1):($in['nonce']??'fixture-nonce');}}
    class PlaybackDb {
        public string $prefix='fixture_';public string $last_error='';
        function prepare($sql,...$args){return $sql;}
        function get_row($sql,$format){global $in;$this->last_error=($in['read_error']??false)?'fixture error':'';return ($in['missing']??false)?null:['recording_sid'=>$in['sid']??('RE'.str_repeat('b',32)),'recording_url'=>$in['url']??'https://foreign.invalid/never-fetch'];}
    }
    $wpdb=new PlaybackDb();
    register_shutdown_function(static function(){echo "\nRESULT:".json_encode(['status'=>$GLOBALS['status'],'fetches'=>$GLOBALS['fetches']],JSON_THROW_ON_ERROR);});
    require dirname(__DIR__).'/src/Support/Config.php';require dirname(__DIR__).'/src/DB/CallLogRepository.php';require dirname(__DIR__).'/src/Webhook/RecordingPlaybackController.php';
    (new SiteEngine\CallBridge\Webhook\RecordingPlaybackController())->handle(new WP_REST_Request());
    exit;
}
$pass=0;
function check($ok,$label){global $pass;if(!$ok)throw new RuntimeException($label);$pass++;echo 'PASS '.$label."\n";}
function run(array $in):array {
    $p=proc_open([PHP_BINARY,'-n','tests/recording-playback.php',json_encode($in,JSON_THROW_ON_ERROR)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$o=stream_get_contents($pipes[1]);$e=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);if($code!==0)throw new RuntimeException($e);[$body,$receipt]=explode("\nRESULT:",$o,2);return ['body'=>$body]+json_decode($receipt,true,512,JSON_THROW_ON_ERROR);
}
foreach([['logged'=>false],['admin'=>false],['nonce'=>''],['nonce'=>'wrong']]as$i){$r=run($i);check($r['status']===403&&$r['fetches']===[],'unauthorized/nonce denial before any fetch '.json_encode($i));}
foreach([[['id'=>0],400],[['missing'=>true],404],[['read_error'=>true],503],[['sid'=>'REbad/../../options'],503]]as[$i,$status]){$r=run($i);check($r['status']===$status&&$r['fetches']===[],'invalid/missing/error fails closed '.json_encode($i));}
$r=run([]);$f=$r['fetches'][0];check($r['status']===200&&$r['body']==='RIFFxxxxWAVEfixture','administrator gets validated audio');
check($f['url']==='https://api.twilio.com/2010-04-01/Accounts/AC'.str_repeat('a',32).'/Recordings/RE'.str_repeat('b',32).'.wav?RequestedChannels=2','canonical account/SID URL ignores foreign stored URL');
check($f['args']['redirection']===0&&$f['args']['sslverify']===true&&$f['args']['limit_response_size']===20971521,'redirects blocked, TLS verified, response bounded');
foreach([302,401,404,500]as$code){$r=run(['upstream'=>$code]);check($r['status']===502&&count($r['fetches'])===1&&!str_contains($r['body'],'synthetic-only'),'upstream '.$code.' no redirect or credential disclosure');}
$r=run(['body'=>'<html>failure</html>']);check($r['status']===502,'non-audio rejected');
$r=run(['oversize'=>true]);check($r['status']===502,'oversized audio rejected rather than truncated playback');
$source=file_get_contents(dirname(__DIR__).'/src/Webhook/RecordingPlaybackController.php');check(str_contains($source,'private, no-store')&&str_contains($source,'nosniff'),'private noncacheable response policy');
echo "$pass PASS / 0 FAIL; fresh-process proxy adapters, not installed media acceptance\n";
