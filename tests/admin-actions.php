<?php
/** Actual number action/repository, in-memory wpdb/WordPress adapters; no server. */
declare(strict_types=1);
define('ABSPATH', __DIR__.'/fixture/');
final class ActionStop extends RuntimeException { public function __construct(public string $url='',public int $status=0){parent::__construct('action-boundary');} }
$GLOBALS['allowed']=true;$GLOBALS['nonce_valid']=true;$GLOBALS['writes']=[];
function current_user_can($cap){return $cap==='manage_options'&&$GLOBALS['allowed'];}
function wp_die($message,$title='', $args=[]){throw new ActionStop('',(int)($args['response']??500));}
function check_admin_referer($action){if(!$GLOBALS['nonce_valid'])wp_die('invalid nonce','',['response'=>403]);}
function sanitize_text_field($s){return strip_tags($s);}
function sanitize_textarea_field($s){return strip_tags($s);}
function sanitize_key($s){return preg_replace('/[^a-z0-9_-]/','',strtolower($s));}
function admin_url($s){return 'https://fixture.invalid/wp-admin/'.$s;}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function wp_safe_redirect($url){throw new ActionStop($url,302);}
final class ActionDb {
    public string $prefix='fixture_';
    public array $rows=[7=>['label'=>'owned'],8=>['label'=>'unrelated']];
    public function update($table,$data,$where,$formats,$whereFormats){
        if($table!=='fixture_se_cb_tracking_numbers'||$where!==['id'=>7])throw new RuntimeException('unexpected write');
        $this->rows[7]=$data;$GLOBALS['writes'][]=$data;return 1;
    }
}
$GLOBALS['wpdb']=new ActionDb();
foreach(['Admin/Menu.php','Admin/TrackingNumbersPage.php','DB/TrackingNumberRepository.php','Support/Phone.php']as$p)require dirname(__DIR__).'/src/'.$p;
use SiteEngine\CallBridge\Admin\TrackingNumbersPage;
use SiteEngine\CallBridge\Admin\Menu;
function check($ok,$name){if(!$ok)throw new RuntimeException('FAIL '.$name);$GLOBALS['pass']=($GLOBALS['pass']??0)+1;echo "PASS $name\n";}
function save(){try{TrackingNumbersPage::handleSave();}catch(ActionStop $e){return $e;}throw new RuntimeException('action did not terminate');}
$_GET=['tab'=>'calls'];
$_POST=['id'=>'7','display_label'=>'Updated synthetic label','phone_number'=>'+10000000071','forward_to'=>'+10000000079','source'=>'google_ads','pool_group'=>'fixture_pool','min_duration_sec'=>'60','repeat_window_days'=>'30','whisper_new'=>'New fixture','whisper_repeat'=>'Repeat fixture'];
$r=save();parse_str(parse_url($r->url,PHP_URL_QUERY),$q);
check($r->status===302&&$q===['notice'=>'updated','edit'=>'7','page'=>'secb-settings','tab'=>'numbers'],'valid edit/save returns to Numbers and preserves edit/notice');
check(count($GLOBALS['writes'])===1&&$GLOBALS['wpdb']->rows[7]['whisper_repeat']==='Repeat fixture'&&$GLOBALS['wpdb']->rows[7]['record_calls']===0,'same validated repository fields and recording-off value');
check($GLOBALS['wpdb']->rows[8]===['label'=>'unrelated'],'unrelated row untouched');
$before=$GLOBALS['wpdb']->rows;$_POST['phone_number']='not-a-phone';$r=save();parse_str(parse_url($r->url,PHP_URL_QUERY),$q);
check($q['tab']==='numbers'&&$q['notice']==='invalid_phone'&&$GLOBALS['wpdb']->rows===$before,'invalid number preserves validation/no write and Numbers notice');
$_POST['phone_number']='+10000000071';$GLOBALS['nonce_valid']=false;$r=save();
check($r->status===403&&$GLOBALS['wpdb']->rows===$before,'invalid nonce denies before write');
$GLOBALS['allowed']=false;$GLOBALS['nonce_valid']=true;$r=save();
check($r->status===403&&$GLOBALS['wpdb']->rows===$before,'subscriber cannot save or change data');
foreach(['edit'=>7,'paged'=>3]as$key=>$value){parse_str(parse_url(Menu::url('numbers',[$key=>$value]),PHP_URL_QUERY),$q);check($q[$key]==$value&&$q['tab']==='numbers','Numbers URL preserves '.$key);}
echo $GLOBALS['pass']." PASS / 0 FAIL; actual action/repository with isolated adapters\n";
