<?php
/** Real local SQLite persistence + a narrow wpdb/WordPress adapter, not installed WP. */
declare(strict_types=1);
define('ABSPATH', __DIR__.'/fixture/');
define('ARRAY_A', 'ARRAY_A');
define('SECB_REST_NAMESPACE','siteengine-call/v1');
define('SECB_TWILIO_AUTH_TOKEN', 'fixture-signing-only');
define('SECB_WEBHOOK_BASE_URL', 'https://fixture.invalid');
define('SECB_DEBUG_LOG', false);
function update_option(...$args) { $GLOBALS['option_writes']++; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value); }
function status_header($status) { $GLOBALS['http_status'] = $status; }
function home_url() { return 'https://fixture.invalid'; }
class WP_REST_Request {}

final class EvidenceSqliteDb
{
    public string $prefix = 'fixture_';
    public string $last_error = '';
    public int $insert_id = 0;
    public array $queries = [];
    public bool $failReads = false;
    public function __construct(public SQLite3 $db) {}
    public function prepare($sql, ...$args) {
        foreach ($args as $arg) {
            $sql = preg_replace_callback('/%[sd]/', static fn($m) => $m[0] === '%d' ? (string) (int) $arg : "'".SQLite3::escapeString((string) $arg)."'", $sql, 1);
        }
        return $sql;
    }
    public function get_row($sql, $output = null) {
        $this->queries[] = $sql;
        $this->last_error = '';
        if ($this->failReads) { $this->last_error = 'injected_read_failure'; return null; }
        try { $r = $this->db->query($sql); return $r->fetchArray(SQLITE3_ASSOC) ?: null; }
        catch (Throwable $e) { $this->last_error = 'sqlite_read_failed'; return null; }
    }
    public function query($sql) { $this->last_error='';$this->queries[]=$sql;try{$this->db->exec($sql);return $this->db->changes();}catch(Throwable $e){$this->last_error='sqlite_write_failed';return false;} }
    public function get_var($sql) { if(str_contains($sql,'INTERVAL'))return 0;return $this->db->querySingle($sql); }
    public function insert($table, $data, $formats = null) {
        $s = $this->db->prepare('INSERT INTO '.$table.' ('.implode(',', array_keys($data)).') VALUES ('.implode(',', array_fill(0, count($data), '?')).')');
        $this->bind($s, array_values($data));
        $s->execute(); $this->insert_id = $this->db->lastInsertRowID();
        return $this->db->changes();
    }
    public function update($table, $data, $where, $formats = null, $whereFormats = null) {
        $this->last_error = '';
        $set = array_map(static fn($k) => $k.'=?', array_keys($data));
        $values = array_values($data); $clauses = [];
        foreach ($where as $k => $v) { $clauses[] = $k.($v === null ? ' IS NULL' : '=?'); if ($v !== null) $values[] = $v; }
        $sql = 'UPDATE '.$table.' SET '.implode(',', $set).' WHERE '.implode(' AND ', $clauses);
        $this->queries[] = $sql;
        $s = $this->db->prepare($sql); $this->bind($s, $values); $s->execute();
        return $this->db->changes();
    }
    private function bind(SQLite3Stmt $s, array $values): void {
        foreach ($values as $i => $v) $s->bindValue($i+1, $v, $v === null ? SQLITE3_NULL : (is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT));
    }
}

[$script, $path, $mode, $receipt] = $argv;
$db = new SQLite3($path, $mode === 'read' || $mode === 'invalid' || $mode === 'missing' || $mode === 'error' ? SQLITE3_OPEN_READONLY : SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
$db->enableExceptions(true);
$db->createFunction('UTC_TIMESTAMP',static fn()=>gmdate('Y-m-d H:i:s'),0);
$wpdb = new EvidenceSqliteDb($db);
$GLOBALS['option_writes'] = 0; $GLOBALS['http_status'] = 200;
spl_autoload_register(static function($class) {
    $prefix = 'SiteEngine\\CallBridge\\';
    if (str_starts_with($class, $prefix)) require dirname(__DIR__).'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
});
$identity = 'twilio:CA'.str_repeat('0', 31).'1';
if ($mode === 'seed' || $mode === 'seed-capture') {
    if ($db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE name='fixture_se_cb_calls'") !== 0) throw new RuntimeException('preserve_existing_fixture');
    $columns = ['id INTEGER PRIMARY KEY','twilio_call_sid TEXT UNIQUE','transaction_id TEXT UNIQUE','tracking_number_id INTEGER','qualification_threshold_sec INTEGER','call_duration_sec INTEGER','is_repeat_caller INTEGER','prior_qualifying_calls INTEGER'];
    foreach (['call_capture_json','created_at','updated_at','matched_session_id','consent_receipt_id','tracking_number','forwarded_to','source','gclid','gbraid','wbraid','fbclid','msclkid','utm_source','utm_medium','utm_campaign','utm_term','utm_content','landing_page','call_status','qualification_status','caller_number','hashed_caller_number','caller_city','caller_state','caller_country','notes','recording_sid','recording_url','recording_duration_sec'] as $column) $columns[] = $column.' TEXT';
    $db->exec('CREATE TABLE fixture_se_cb_calls ('.implode(',', $columns).')');
    $db->exec("CREATE TABLE fixture_se_cb_tracking_numbers (id INTEGER PRIMARY KEY,phone_number TEXT,is_active INTEGER,min_duration_sec INTEGER,source TEXT,pool_group TEXT,record_calls INTEGER,forward_to TEXT,repeat_window_days INTEGER,whisper_new TEXT,whisper_repeat TEXT)");
    $db->exec("INSERT INTO fixture_se_cb_tracking_numbers (id,phone_number,is_active,min_duration_sec,source,pool_group,record_calls) VALUES (1,'+10000000081',1,60,'current-unrelated-source','current-unrelated-pool',0)");
    $row = ['twilio_call_sid'=>substr($identity, 7),'transaction_id'=>$identity,'created_at'=>'2026-11-06 12:00:00','updated_at'=>'2026-11-06 12:00:00','matched_session_id'=>'synthetic-original-session','tracking_number_id'=>1,'tracking_number'=>'+10000000081','forwarded_to'=>'+10000000089','source'=>'synthetic-original-source','utm_source'=>'synthetic-original-acquisition','utm_campaign'=>'CB-EVIDENCE-SYNTHETIC-20261106','landing_page'=>'https://fixture.invalid/original','call_status'=>'ringing','call_duration_sec'=>null,'qualification_status'=>'pending','is_repeat_caller'=>0,'prior_qualifying_calls'=>0,'caller_number'=>'+10000000082','notes'=>'SYNTHETIC ONLY; DO_NOT_EXPORT_NOTES'];
    if ($mode !== 'seed-capture' && (new SiteEngine\CallBridge\DB\CallLogRepository())->insert($row) !== 1) throw new RuntimeException('fixture_insert');
}
if($mode==='seed-capture') {
    $db->exec("UPDATE fixture_se_cb_tracking_numbers SET source='synthetic-original-source',pool_group='original-allocation-pool',forward_to='+10000000089',repeat_window_days=30");
    $db->exec("CREATE TABLE fixture_se_cb_attribution_sessions (id INTEGER PRIMARY KEY,session_id TEXT,utm_source TEXT,utm_campaign TEXT,landing_page TEXT,consent_receipt_id TEXT)");
    $db->exec("INSERT INTO fixture_se_cb_attribution_sessions VALUES (1,'synthetic-original-session','synthetic-acquisition','CB-CAPTURE-SYNTHETIC','https://fixture.invalid/original',NULL)");
    $db->exec("CREATE TABLE fixture_se_cb_number_leases (id INTEGER PRIMARY KEY,session_id TEXT,tracking_number_id INTEGER,tracking_number TEXT,source TEXT,pool_group TEXT,leased_at TEXT,expires_at TEXT,status TEXT,released_at TEXT)");
    $start=gmdate('Y-m-d H:i:s',time()-60);$end=gmdate('Y-m-d H:i:s',time()+1800);
    $db->exec("INSERT INTO fixture_se_cb_number_leases VALUES (1,'synthetic-original-session',1,'+10000000081','synthetic-original-source','original-allocation-pool','$start','$end','active',NULL)");
}
if($mode==='change-capture-context') {
    $db->exec("UPDATE fixture_se_cb_tracking_numbers SET source='changed-current-source',pool_group='changed-current-pool',min_duration_sec=120");
    $db->exec("UPDATE fixture_se_cb_attribution_sessions SET utm_source='changed-current-acquisition',utm_campaign='changed-current-campaign'");
    $db->exec("UPDATE fixture_se_cb_number_leases SET pool_group='changed-current-lease-pool'");
}
if($mode==='change-terminal-threshold')$db->exec("UPDATE fixture_se_cb_tracking_numbers SET min_duration_sec=1");
if($mode==='legacy-terminal')$db->exec("UPDATE fixture_se_cb_calls SET call_status='completed',call_duration_sec=75,qualification_status='qualified'");
if($mode==='clear-original-pool')$db->exec("UPDATE fixture_se_cb_number_leases SET pool_group=NULL");
if($mode==='conflicting-capture'){$json=json_decode($db->querySingle('SELECT call_capture_json FROM fixture_se_cb_calls'),true);$json['call_identity']='twilio:CA'.str_repeat('0',31).'9';$st=$db->prepare('UPDATE fixture_se_cb_calls SET call_capture_json=?');$st->bindValue(1,json_encode($json));$st->execute();}
if($mode==='corrupt-capture')$db->exec("UPDATE fixture_se_cb_calls SET call_capture_json='{malformed'");
if ($mode === 'change-current-number') $db->exec("UPDATE fixture_se_cb_tracking_numbers SET source='another-current-source',pool_group='another-current-pool',is_active=0");
if ($mode === 'conflict') $db->exec("UPDATE fixture_se_cb_calls SET transaction_id='corrupt-fixture-identity'");
if ($mode === 'error') $wpdb->failReads = true;
if ($mode === 'invalid') $identity = 'ff:123';
if ($mode === 'missing') $identity = 'twilio:CA'.str_repeat('0', 31).'2';
$emit = static function() use ($receipt, $identity, $wpdb, $db) {
    $evidence = SiteEngine\CallBridge\Integration\CallEvidence::read($identity);
    file_put_contents($receipt, json_encode(['fixture'=>'SYNTHETIC LOCAL SQLITE ONLY','pid'=>getmypid(),'evidence'=>$evidence,'queries'=>$wpdb->queries,'option_writes'=>$GLOBALS['option_writes'],'http_status'=>$GLOBALS['http_status'],'call_count'=>$db->querySingle('SELECT COUNT(*) FROM fixture_se_cb_calls')], JSON_THROW_ON_ERROR));
};
if (str_starts_with($mode, 'callback-') || $mode==='voice-new') {
    $status = substr($mode, 9);
    $post = ['CallSid'=>substr($identity, 7),'DialCallStatus'=>$status,'DialCallDuration'=>$status === 'completed' ? '90' : '0'];
    $route=$mode==='voice-new'?'voice':'status';
    if($route==='voice')$post=['CallSid'=>substr($identity,7),'From'=>'+10000000082','To'=>'+10000000081'];
    $uri = '/wp-json/siteengine-call/v1/twilio/'.$route; $text = 'https://fixture.invalid'.$uri;
    ksort($post, SORT_STRING); foreach ($post as $k => $v) $text .= $k.$v;
    $_SERVER = ['REQUEST_METHOD'=>'POST','REQUEST_URI'=>$uri,'HTTP_X_TWILIO_SIGNATURE'=>base64_encode(hash_hmac('sha1', $text, 'fixture-signing-only', true))];
    $_POST = $post; register_shutdown_function($emit);
    $controller=$route==='voice'?new SiteEngine\CallBridge\Webhook\TwilioVoiceController():new SiteEngine\CallBridge\Webhook\TwilioStatusController();
    $controller->handle(new WP_REST_Request());
} else $emit();
