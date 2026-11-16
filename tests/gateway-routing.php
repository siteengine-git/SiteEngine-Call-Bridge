<?php
/** Native local files/locks; in-memory destination and database adapters, no network. */
declare(strict_types=1);
require dirname(__DIR__).'/src/Gateway/Protocol.php';require dirname(__DIR__).'/gateway/Router.php';
use SiteEngine\CallBridge\Gateway\{Protocol,Router,Receiver,Rejection,Context};
$root=sys_get_temp_dir().'/cb-router-test-'.bin2hex(random_bytes(8));mkdir($root,0700);
foreach(['web','state','nonce']as$d)mkdir($root.'/'.$d,0700);
define('ABSPATH',$root.'/web/');define('ARRAY_A','ARRAY_A');define('SECB_REST_NAMESPACE','siteengine-call/v1');
require dirname(__DIR__).'/src/DB/CallLogRepository.php';require dirname(__DIR__).'/src/Gateway/Receiver.php';require dirname(__DIR__).'/src/Gateway/Context.php';require dirname(__DIR__).'/src/Support/Config.php';
class GatewayDb {
 public string $prefix='fixture_';public string $last_error='';public array $rows=[];public bool $fail=false;
 function prepare($sql,...$args){foreach($args as$a)$sql=preg_replace('/%s/',"'".$a."'",$sql,1);return $sql;}
 function get_row($sql,$format){$this->last_error=$this->fail?'fixture read failure':'';preg_match("/twilio_call_sid='([^']+)'/",$sql,$m);return $this->rows[$m[1]??'']??null;}
}
$wpdb=new GatewayDb();$pass=0;$requests=[];
function check($ok,$why){global$pass;if(!$ok)throw new RuntimeException($why);$pass++;echo 'PASS '.$why."\n";}
function rejects(callable $f,int $status,string $why){try{$f();check(false,$why);}catch(Rejection$e){check($e->status===$status,$why);}}
$account='AC'.str_repeat('a',32);$accountB='AC'.str_repeat('b',32);$sid='CA'.str_repeat('1',32);$sid2='CA'.str_repeat('2',32);
$dA=['url'=>'https://a.fixture.invalid/receive','secret'=>'receiver-a-synthetic-only-'.str_repeat('a',32)];$dB=['url'=>'https://b.fixture.invalid/receive','secret'=>'receiver-b-synthetic-only-'.str_repeat('b',32)];
$config=['public_url'=>'https://gateway.fixture.invalid/gateway.php','state_dir'=>$root.'/state','document_root'=>$root.'/web','clients'=>[
 'alpha'=>['account_sid'=>$account,'auth_token'=>'provider-alpha-synthetic','numbers'=>['+10000000081'],'current_destination'=>'a','destinations'=>['a'=>$dA,'b'=>$dB]],
 'beta'=>['account_sid'=>$accountB,'auth_token'=>'provider-beta-synthetic','numbers'=>['+10000000082'],'current_destination'=>'b','destinations'=>['b'=>['url'=>'https://beta.fixture.invalid/receive','secret'=>'receiver-beta-synthetic-'.str_repeat('c',32)]]],
]];
function input(array $config,array $form,array $query):array{
 $method=$query['event']==='whisper'?'GET':'POST';$uri='/gateway.php?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);$raw=$method==='POST'?http_build_query($form,'','&',PHP_QUERY_RFC3986):'';
 $url='https://gateway.fixture.invalid'.$uri;$sorted=$method==='POST'?$form:[];ksort($sorted,SORT_STRING);foreach($sorted as$k=>$v)$url.=$k.$v;
 $sig=base64_encode(hash_hmac('sha1',$url,$config['clients'][$query['client']]['auth_token']??'unknown',true));
 return [['REQUEST_METHOD'=>$method,'REQUEST_URI'=>$uri,'CONTENT_TYPE'=>'application/x-www-form-urlencoded','HTTP_X_TWILIO_SIGNATURE'=>$sig],$raw];
}
$transport=static function($url,$body,$signature)use(&$requests,$dA,$dB){$requests[]=[$url,json_decode($body,true),$body,$signature];return [200,'<?xml version="1.0"?><Response><Say>Owned fixture</Say></Response>'];};
$post=['AccountSid'=>$account,'CallSid'=>$sid,'To'=>'+10000000081','From'=>'+10000000089'];$query=['client'=>'alpha','event'=>'voice'];
try{
 $router=new Router($config);[$server,$raw]=input($config,$post,$query);
 $bad=$server;$bad['HTTP_X_TWILIO_SIGNATURE']='bad';rejects(fn()=>$router->handle($bad,$raw,$transport),403,'invalid provider signature');check(count(glob($root.'/state/*'))===0,'invalid signature creates no binding');
 $bad=$server;$bad['REQUEST_URI'].='&extra=tampered';rejects(fn()=>$router->handle($bad,$raw,$transport),403,'exact external query signature');
 [$s,$r]=input($config,array_replace($post,['AccountSid'=>$accountB]),$query);rejects(fn()=>$router->handle($s,$r,$transport),403,'cross-account provider request');
 [$s,$r]=input($config,array_replace($post,['To'=>'+10000000082']),$query);rejects(fn()=>$router->handle($s,$r,$transport),403,'other client number rejected');
 $unknown=$server;$unknown['REQUEST_URI']='/gateway.php?client=unknown&event=voice';rejects(fn()=>$router->handle($unknown,$raw,$transport),404,'unknown client');
 rejects(fn()=>Protocol::form('x=a&x=b'),400,'duplicate form grammar rejected');rejects(fn()=>Protocol::form('x[]=a'),400,'nested parameters rejected');
 $answer=$router->handle($server,$raw,$transport);check($answer[0]===200&&count($requests)===1&&$requests[0][0]===$dA['url'],'voice reaches exact registered destination');$env=$requests[0][1];
 check(hash_equals(hash_hmac('sha256',$requests[0][2],$dA['secret']),$requests[0][3]),'handoff authenticates exact envelope');
 $router->handle($server,$raw,$transport);check(count($requests)===1,'duplicate voice returns cached TwiML without downstream effects');
 $moved=$config;$moved['clients']['alpha']['current_destination']='b';$next=new Router($moved);$next->handle($server,$raw,$transport);check(count($requests)===1,'route switch retains original voice response');
 $cb=['client'=>'alpha','event'=>'status','call'=>$sid,'binding'=>$env['binding']];$status=['AccountSid'=>$account,'CallSid'=>$sid,'DialCallStatus'=>'completed','DialCallDuration'=>'65'];[$s,$r]=input($config,$status,$cb);$next->handle($s,$r,$transport);check(end($requests)[0]===$dA['url'],'existing status sticks to destination A after switch');
 [$s,$r]=input($config,array_replace($post,['CallSid'=>$sid2]),$query);$next->handle($s,$r,$transport);check(end($requests)[0]===$dB['url'],'new call reaches destination B');
 $cb['event']='recording';$record=['AccountSid'=>$account,'CallSid'=>$sid,'RecordingSid'=>'RE'.str_repeat('4',32),'RecordingUrl'=>'https://media.invalid/owned','RecordingStatus'=>'completed'];[$s,$r]=input($config,$record,$cb);$next->handle($s,$r,$transport);check(end($requests)[0]===$dA['url'],'recording remains with original destination');
 $whisper=$cb;$whisper['event']='whisper';$whisper+=['AccountSid'=>$account,'CallSid'=>'CA'.str_repeat('3',32),'ParentCallSid'=>$sid,'msg'=>"New & repeat's fixture"];[$s,$r]=input($config,[],$whisper);$next->handle($s,$r,$transport);check(end($requests)[0]===$dA['url']&&end($requests)[1]['query']['msg']==="New & repeat's fixture",'GET whisper parent and exact signed text routed');
 $whisper['ParentCallSid']=$sid2;[$s,$r]=input($config,[],$whisper);rejects(fn()=>$next->handle($s,$r,$transport),403,'whisper wrong parent rejected');
 $cb['binding']=str_repeat('0',64);[$s,$r]=input($config,$record,$cb);rejects(fn()=>$next->handle($s,$r,$transport),403,'binding capability cannot cross calls');
 [$s,$r]=input($config,array_replace($status,['CallSid'=>'CA'.str_repeat('9',32)]),['client'=>'alpha','event'=>'status']);rejects(fn()=>$next->handle($s,$r,$transport),503,'early callback never guesses current destination');
 $failSid='CA'.str_repeat('5',32);[$s,$r]=input($config,array_replace($post,['CallSid'=>$failSid]),$query);$failCount=0;$failure=static function()use(&$failCount){$failCount++;throw new RuntimeException('timeout');};rejects(fn()=>$next->handle($s,$r,$failure),503,'destination timeout not acknowledged');rejects(fn()=>$next->handle($s,$r,$failure),503,'uncertain voice not automatically resent');check($failCount===1,'only one call-control transport attempt');
 $broken=$config;$broken['clients']['alpha']['destinations']['a']['url']='https://changed.fixture.invalid/receive';$changed=new Router($broken);rejects(fn()=>$changed->handle($server,$raw,$transport),503,'in-place destination mutation cannot redirect an existing call');
 $reused=$config;$reused['clients']['beta']['destinations']['b']['secret']=$dA['secret'];rejects(fn()=>new Router($reused),503,'cross-client handoff secret reuse rejected');
 $clients=['alpha'=>['destination'=>'a','account_sid'=>$account,'numbers'=>['+10000000081'],'gateway_base'=>$config['public_url'],'secret'=>$dA['secret']]];
 $verify=static function(array$v,?string$sig=null)use($clients,$root,$dA){$body=json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);return Receiver::verify($body,$sig??hash_hmac('sha256',$body,$dA['secret']),$clients,$root.'/nonce',$root.'/web');};
 rejects(fn()=>$verify($env,'forged'),403,'receiver rejects forged handoff');
 $wrong=$env;$wrong['destination']='b';rejects(fn()=>$verify($wrong),403,'receiver destination scope');
 $wrong=$env;$wrong['account_sid']=$accountB;rejects(fn()=>$verify($wrong),403,'receiver account scope');
 $wrong=$env;$wrong['timestamp']-=120;rejects(fn()=>$verify($wrong),403,'receiver stale handoff');
 $verified=$verify($env);check($verified['call_sid']===$sid,'valid independently signed handoff accepted');rejects(fn()=>$verify($env),409,'same handoff nonce cannot replay');
 $wpdb->rows[$sid]=['tracking_number'=>'+10000000082','transaction_id'=>'twilio:'.$sid];$env['nonce']=bin2hex(random_bytes(32));rejects(fn()=>$verify($env),403,'voice cannot reuse another persisted number identity');unset($wpdb->rows[$sid]);
 $env['event']='recording';$env['form']=$record;$env['nonce']=bin2hex(random_bytes(32));rejects(fn()=>$verify($env),503,'receiver unknown call is not false callback success');
 $wpdb->rows[$sid]=['tracking_number'=>'+10000000081','transaction_id'=>'twilio:'.$sid];$wpdb->fail=true;rejects(fn()=>$verify($env),503,'receiver read error fails closed');$wpdb->fail=false;
 $verified=$verify($env);check($verified['event']==='recording','recording handoff targets actual persisted identity');
 check(SiteEngine\CallBridge\Support\Config::callbackUrl('status')==='https://direct.fixture.invalid/wp-json/siteengine-call/v1/twilio/status','direct URL unchanged');
 Context::enter($verified);foreach(['status','recording','whisper']as$event){$url=SiteEngine\CallBridge\Support\Config::callbackUrl($event,['msg'=>'A & B']);parse_str(parse_url($url,PHP_URL_QUERY),$q);check($q['client']==='alpha'&&$q['call']===$sid&&$q['event']===$event&&$q['binding']===$verified['binding'],'central generated '.$event.' URL pins original call');}Context::clear();
 check(!Context::active(),'request context clears');
}finally{
 Context::clear();foreach(['state','nonce','web']as$d){foreach(glob($root.'/'.$d.'/*')as$f)unlink($f);rmdir($root.'/'.$d);}rmdir($root);
}
echo "$pass PASS / 0 FAIL; native local file durability; transport/SQL adapters, not Alma acceptance\n";
function home_url(){return 'https://direct.fixture.invalid';}
