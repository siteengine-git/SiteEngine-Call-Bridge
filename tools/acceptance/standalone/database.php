<?php
/** Minimal mysqli wpdb-shaped acceptance adapter; no WordPress core/hooks/drop-ins. */
declare(strict_types=1);
function secb_standalone_sql_gate(string $sql,string $root,string $database,bool $candidate):void {
    if(!preg_match('/^[A-Za-z0-9_]+$/D',$root))throw new RuntimeException('unsafe_root_prefix');
    $clone=$root.'secb_accept_20261027_01_';$tables=[$clone.'se_cb_tracking_numbers',$clone.'se_cb_number_leases'];
    $original=array_map(fn($n)=>$root.'se_cb_'.$n,['tracking_numbers','number_leases','attribution_sessions','calls']);
    if(preg_match('/;|--|\/\*|#|\b(UNION|OUTFILE|DUMPFILE|LOAD_FILE|SLEEP|BENCHMARK|GLOBAL|GRANT|KILL|TRUNCATE)\b/i',$sql))throw new RuntimeException('forbidden_sql_form');
    $lock='secb_alloc_'.substr(sha1($database.'|'.$clone),0,40);
    if(preg_match('/^SELECT (GET_LOCK|RELEASE_LOCK|IS_USED_LOCK|IS_FREE_LOCK)\(\'([^\']+)\'(?:,([02]))?\)$/D',$sql,$m)){if($m[2]!==$lock)throw new RuntimeException('wrong_allocation_lock');return;}
    if(preg_match('/^SELECT (?:CONNECTION_ID|UTC_TIMESTAMP)\(\)$/D',$sql))return;
    if($sql==='SET SESSION max_statement_time=5'){if($candidate)throw new RuntimeException('candidate_session_setting');return;}
    if(preg_match('/^(SELECT|CHECKSUM TABLE)\b/i',$sql)){
        if($candidate&&str_starts_with($sql,'CHECKSUM'))throw new RuntimeException('candidate_checksum');
        preg_match_all('/\b(?:FROM|JOIN|TABLE)\s+`?([A-Za-z0-9_.]+)`?/i',$sql,$m);
        if(!$m[1])throw new RuntimeException('unscoped_read');
        foreach($m[1] as $table)if(!in_array($table,$candidate?$tables:array_merge($tables,$original,[$root.'options','information_schema.TABLES','information_schema.PROCESSLIST']),true))throw new RuntimeException('wrong_read_table');
        return;
    }
    if(preg_match('/^(?:INSERT|UPDATE|DELETE)\b/i',$sql)){
        secb_standalone_mutation_gate($sql,$tables,$candidate);return;
    }
    if(!$candidate&&preg_match('/^CREATE TABLE `([A-Za-z0-9_]+)` LIKE `([A-Za-z0-9_]+)`$/D',$sql,$m)){if(!in_array($m[1],$tables,true)||$m[2]!==$root.substr($m[1],strlen($clone)))throw new RuntimeException('wrong_clone_definition');return;}
    if(!$candidate&&preg_match('/^ALTER TABLE `([A-Za-z0-9_]+)` COMMENT=\'cbaccept:20261027:01\'$/D',$sql,$m)&&in_array($m[1],$tables,true))return;
    if(!$candidate&&preg_match('/^DROP TABLE `([A-Za-z0-9_]+)`$/D',$sql,$m)&&in_array($m[1],$tables,true))return;
    throw new RuntimeException('unreviewed_sql_operation');
}
/** Whole-statement grammar for Stage S emitters, never a table-prefix shortcut.
 * Literal fixture values cannot contain quotes/backslashes (no SQL-mode-dependent
 * escape interpretation). Expressions, aliases, joins, nested reads and tails
 * are unsupported. Exceptions are the fixed expiry sweep and two expiry-time edits.
 */
function secb_standalone_mutation_gate(string $sql,array $tables,bool $candidate):void {
    $column='\x60[a-z_]+\x60';
    $literal="(?:'[^'\\\\]*'|NULL)";
    $assignment=$column.'='.$literal;
    foreach($tables as $index=>$table){
        $quoted='\x60'.preg_quote($table,'~').'\x60';
        if(preg_match('~\AINSERT INTO '.$quoted.' \\('.$column.'(?:,'.$column.')*\\) VALUES \\('.$literal.'(?:,'.$literal.')*\\)\z~D',$sql))return;
        if(preg_match('~\AUPDATE '.$quoted.' SET '.$assignment.'(?:,'.$assignment.')* WHERE '.$assignment.'(?: AND '.$assignment.')*\z~D',$sql))return;
        if($index!==1)continue;
        if($sql==="UPDATE $table SET status='expired', released_at=COALESCE(released_at,UTC_TIMESTAMP()) WHERE status='active' AND expires_at<=UTC_TIMESTAMP()")return;
        if(!$candidate&&preg_match('~\AUPDATE '.$quoted.' SET expires_at=UTC_TIMESTAMP\\(\\)(?:-INTERVAL 1|\\+INTERVAL 5) SECOND WHERE id=[1-9][0-9]*\z~D',$sql))return;
        if(!$candidate&&$sql==='DELETE FROM '.chr(96).$table.chr(96))return;
    }
    throw new RuntimeException('unsupported_mutation_shape');
}

final class wpdb {
    public string $prefix,$last_error='';public int $insert_id=0;private mysqli $db;private bool $candidate=false;private string $root;
    public function __construct(string $user,string $password,string $database,string $host){
        $cfg=$GLOBALS['secb_standalone_config'];if([$user,$password,$database,$host]!==[$cfg['DB_USER'],$cfg['DB_PASSWORD'],$cfg['DB_NAME'],$cfg['DB_HOST']])throw new RuntimeException('connection_config_changed');
        $this->root=$this->prefix=$cfg['prefix'];
        if(!preg_match('/^([A-Za-z0-9_.-]+)(?::([0-9]{1,5}))?$/D',$host,$m))throw new RuntimeException('db_host_requires_review');
        mysqli_report(MYSQLI_REPORT_OFF);try{$this->db=@new mysqli($m[1],$user,$password,$database,isset($m[2])?(int)$m[2]:3306);if($this->db->connect_errno||!$this->db->set_charset('utf8mb4'))throw new RuntimeException();}catch(Throwable $e){throw new RuntimeException('standalone_connection_failed');}
    }
    public function beginCandidate(string $prefix):void {if($this->candidate||$prefix!==$this->root.'secb_accept_20261027_01_'||$this->prefix!==$prefix)throw new RuntimeException('candidate_prefix_mismatch');$this->candidate=true;}
    public function endCandidate():void {$this->candidate=false;}
    public function prepare(string $sql,mixed ...$args):string {
        $i=0;$out=preg_replace_callback('/%[sd]/',function($m)use(&$i,$args){if(!array_key_exists($i,$args))throw new RuntimeException('prepare_arguments');$v=$args[$i++];return $m[0]==='%d'?(string)(int)$v:"'".$this->db->real_escape_string((string)$v)."'";},$sql);
        if($i!==count($args))throw new RuntimeException('prepare_argument_count');return $out;
    }
    public function query(string $sql):int|false {
        foreach(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame)if(str_starts_with($frame['class']??'','SiteEngine\\CallBridge\\')&&!$this->candidate)throw new RuntimeException('candidate_scope_required');
        $this->last_error='';secb_standalone_sql_gate($sql,$this->root,DB_NAME,$this->candidate);
        if($this->candidate&&$this->prefix!==$this->root.'secb_accept_20261027_01_')throw new RuntimeException('candidate_prefix_changed');
        $r=$this->db->query($sql);if($r===false){$this->last_error='standalone SQL operation failed';$this->result=null;return false;}
        $this->result=$r instanceof mysqli_result?$r:null;$this->insert_id=(int)$this->db->insert_id;return $r instanceof mysqli_result?$r->num_rows:$this->db->affected_rows;
    }
    private ?mysqli_result $result=null;
    public function get_results(string $sql,mixed $format=null):?array {if($this->query($sql)===false)return null;return $this->result?$this->result->fetch_all(MYSQLI_ASSOC):[];}
    public function get_row(string $sql,mixed $format=null):?array {$rows=$this->get_results($sql);return $rows[0]??null;}
    public function get_col(string $sql):array {$rows=$this->get_results($sql);return $rows?array_map(fn($r)=>array_values($r)[0],$rows):[];}
    public function get_var(string $sql):mixed {$r=$this->get_row($sql);return $r?array_values($r)[0]:null;}
    private function assignments(array $data,string $separator=','):string {$out=[];foreach($data as $key=>$v){if(!preg_match('/^[a-z_]+$/D',$key))throw new RuntimeException('column_identifier');$out[]='`'.$key.'`='.($v===null?'NULL':$this->prepare('%s',$v));}return implode($separator,$out);}
    public function insert(string $table,array $data,array $formats=[]):int|false {
        foreach(array_keys($data) as $key)if(!preg_match('/^[a-z_]+$/D',$key))throw new RuntimeException('column_identifier');
        $values=array_map(fn($v)=>$v===null?'NULL':$this->prepare('%s',$v),array_values($data));return $this->query('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($data)).'`) VALUES ('.implode(',',$values).')');
    }
    public function update(string $table,array $data,array $where,array $formats=[],array $whereFormats=[]):int|false {return $this->query('UPDATE `'.$table.'` SET '.$this->assignments($data).' WHERE '.$this->assignments($where,' AND '));}
    public function hide_errors():void {}public function suppress_errors(bool $value=true):void {}public function close():void {$this->db->close();}
}
function wp_get_scheduled_event(string $hook):object|false {
    global $wpdb;$r=$wpdb->get_var("SELECT option_value FROM `{$wpdb->prefix}options` WHERE option_name='cron'");if($wpdb->last_error!=='')throw new RuntimeException('cron_read_failed');$cron=unserialize((string)$r,['allowed_classes'=>false]);if(!is_array($cron))throw new RuntimeException('cron_decode_failed');
    $events=[];foreach($cron as $stamp=>$hooks)if(is_array($hooks)&&isset($hooks[$hook]))foreach($hooks[$hook] as $event)$events[]=(object)(['timestamp'=>(int)$stamp]+$event);
    if(count($events)>1)throw new RuntimeException('duplicate_gc_requires_review');return $events[0]??false;
}
