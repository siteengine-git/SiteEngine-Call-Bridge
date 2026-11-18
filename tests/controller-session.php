<?php
namespace SiteEngine\CallBridge\Webhook {
    function time(): int { return $GLOBALS['lease_clock']; }
    function gmdate(string $format,?int $at=null): string { return \gmdate($format,$at??time()); }
}
namespace {
/** Actual controller/session/lease integration; explicit fake database and REST objects. */
define('SECB_TEST_LIBRARY',true);
require __DIR__.'/lease-repository.php';
require dirname(__DIR__).'/src/DB/AttributionSessionRepository.php';
require dirname(__DIR__).'/src/Support/TrackingNumberResolver.php';
require dirname(__DIR__).'/src/Support/Logger.php';
require dirname(__DIR__).'/src/Webhook/TrackSessionController.php';
final class WP_REST_Request {public function __construct(private array $body){}public function get_json_params(){return $this->body;}}
final class WP_REST_Response {public function __construct(public array $data,public int $status=200){}}
function get_transient($key){return false;}
function set_transient(...$args){return true;}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));}
function sanitize_text_field($v){return strip_tags($v);}
function esc_url_raw($v){return $v;}
function wp_json_encode($v,...$args){return json_encode($v,...$args);}
final class SessionDb extends LeaseDb {
    public array $sessions=[]; public ?string $fault=null; public bool $zeroSessionUpdate=false;
    private function attribution(string $sql):bool {return str_contains($sql,'se_cb_attribution_sessions');}
    public function get_row(string $sql,$format){
        if(!$this->attribution($sql))return parent::get_row($sql,$format);
        $this->last_error='';if($this->fault==='read'){$this->last_error='synthetic attribution read failure';return null;}
        if(!preg_match("/session_id='([^']+)'/",$sql,$m))throw new RuntimeException('Unmodelled attribution read');
        return $this->sessions[$m[1]]??null;
    }
    public function insert($table,$data,$formats){
        if(!$this->attribution($table))return parent::insert($table,$data,$formats);
        $this->last_error='';if($this->fault==='insert'){$this->last_error='synthetic attribution insert failure';return false;}
        $this->insert_id=100;$this->sessions[$data['session_id']]=$data+['id'=>100];
        if($this->fault==='readback')$this->fault='read';return 1;
    }
    public function update($table,$data,$where,...$formats){
        if(!$this->attribution($table))return parent::update($table,$data,$where,...$formats);
        $this->last_error='';if($this->fault==='update'){$this->last_error='synthetic attribution update failure';return false;}
        if($this->zeroSessionUpdate)return 0;
        $this->sessions[$data['session_id']]=array_merge($this->sessions[$data['session_id']],$data);
        if($this->fault==='readback')$this->fault='read';return 1;
    }
}
$results=[];
function environment():array {[$s]=setup();number($s,1);number($s,2,'fixture_beta');$db=new SessionDb($s,'controller');$GLOBALS['wpdb']=$db;return [$s,$db];}
function post(string $source,string $campaign='fixture-original'):WP_REST_Response {
    return (new \SiteEngine\CallBridge\Webhook\TrackSessionController())->handle(new WP_REST_Request(['session_id'=>'fixture-controller-session','source'=>$source,'utm_source'=>$source,'utm_campaign'=>$campaign]));
}
[$s,$db]=environment();$first=post('fixture_alpha');$original=$db->sessions;$lease=$s->leases;$GLOBALS['lease_clock']+=60;
$r=post('fixture_beta','fixture-rejected');
check($first->data['tracking_number']!==null&&$r->data['tracking_number']===null&&$r->status===200,'controller rejects different source with no tracking number');
check($original===$db->sessions&&$lease===$s->leases,'rejected source transition preserves attribution AND original lease expiry/reservation');
$GLOBALS['lease_clock']+=1801;$r=post('fixture_beta','fixture-after-expiry');
check($r->data['tracking_number']==='+12025550102'&&$db->sessions['fixture-controller-session']['utm_campaign']==='fixture-after-expiry','expired session may adopt new source attribution and pool');
foreach(['read','insert','readback'] as $fault){[$s,$db]=environment();$db->fault=$fault;$r=post('fixture_alpha');check($r->data['tracking_number']===null&&$s->leases===[]&&$s->locks===[],'attribution '.$fault.' failure prevents allocation');}
foreach(['read','update','readback'] as $fault){[$s,$db]=environment();post('fixture_alpha');$lease=$s->leases;$GLOBALS['lease_clock']+=60;$db->fault=$fault;$r=post('fixture_alpha','fixture-new');check($r->data['tracking_number']===null&&$s->leases===$lease&&$s->locks===[],'attribution '.$fault.' failure prevents renewal');}
[$s,$db]=environment();post('fixture_alpha');$db->zeroSessionUpdate=true;$r=post('fixture_alpha');
check($r->data['tracking_number']!==null,'zero-row attribution update accepted when readback equals requested values');
$r=post('fixture_alpha','fixture-not-persisted');check($r->data['tracking_number']===null,'zero-row attribution update rejected when readback does not match');
[$s,$db]=environment();post('fixture_alpha');$before=$db->sessions;$s->numbers[1]['is_active']=0;$r=post('fixture_alpha','fixture-inactive');
check($r->data['tracking_number']===null&&$db->sessions===$before,'ineligible renewal cannot overwrite attribution');
[$s,$db]=environment();$s->numbers=[];$r=post('fixture_alpha');
check($r->data['tracking_number']===null&&isset($db->sessions['fixture-controller-session'])&&$s->leases===[],'successful empty pool preserves attribution capture without allocation');
[$s,$db]=environment();$other=new SessionDb($s,'controller-other');$other->sessions=&$db->sessions;$contender=null;
$db->duringUsedRead=function()use($db,$other,&$contender){$GLOBALS['wpdb']=$other;$contender=post('fixture_beta','fixture-contender');$GLOBALS['wpdb']=$db;};
$winner=post('fixture_alpha');
check($winner->data['tracking_number']!==null&&$contender->data['tracking_number']===null&&$db->sessions['fixture-controller-session']['utm_source']==='fixture_alpha'&&count($s->leases)===1,'interleaved cross-source controller request cannot replace winner attribution');
$failed=count(array_filter($results,fn($r)=>!$r['ok']));echo 'RESULT '.(count($results)-$failed).' PASS / '.$failed." FAIL; controller integration with fake DB, no WordPress/SQL bootstrap.\n";
if(isset($argv[1]))file_put_contents($argv[1],json_encode($results,JSON_PRETTY_PRINT)."\n");exit($failed?1:0);
}
