<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\DB;
defined('ABSPATH') || exit;
final class CallLogRepository
{
    public function table(): string { global $wpdb; return $wpdb->prefix . 'se_cb_calls'; }
    public function insert(array $data): int|false { global $wpdb; $ok=$wpdb->insert($this->table(),$data,$this->formats($data)); return $ok===false?false:(int)$wpdb->insert_id; }
    public function update(int $id,array $data): bool { global $wpdb; return $wpdb->update($this->table(),$data,['id'=>$id],$this->formats($data),['%d'])!==false; }
    /** Checked status callers distinguish SQL failure from zero-row contention. */
    public function updateIfCurrent(int $id,array $data,array $expected,bool $throwOnError=false): bool
    {
        global $wpdb;
        $where=['id'=>$id]+$expected;
        $result=$wpdb->update($this->table(),$data,$where,$this->formats($data),$this->formats($where));
        if($throwOnError && $result===false){throw new \RuntimeException('callback_write_failed');}
        return $result===1;
    }
    public function findByCallSid(string $sid,bool $throwOnError=false): ?array
    {
        global $wpdb;
        $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE twilio_call_sid=%s LIMIT 1",$sid),ARRAY_A);
        if($throwOnError && $wpdb->last_error!==''){throw new \RuntimeException('callback_read_failed');}
        return $r?:null;
    }
    public function findById(int $id): ?array { global $wpdb; $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id=%d LIMIT 1",$id),ARRAY_A); return $r?:null; }
    /** Persisted call snapshot only; deliberately excludes caller PII, notes and media. */
    public function findEvidenceByCallSid(string $sid): ?array
    {
        global $wpdb;
        $columns = 'id,twilio_call_sid,transaction_id,created_at,updated_at,matched_session_id,consent_receipt_id,tracking_number_id,tracking_number,forwarded_to,source,gclid,gbraid,wbraid,fbclid,msclkid,utm_source,utm_medium,utm_campaign,utm_term,utm_content,landing_page,call_status,call_duration_sec,qualification_status,is_repeat_caller,prior_qualifying_calls,call_capture_json,qualification_threshold_sec';
        $row = $wpdb->get_row($wpdb->prepare("SELECT {$columns} FROM {$this->table()} WHERE twilio_call_sid=%s LIMIT 1", $sid), ARRAY_A);
        if ($wpdb->last_error !== '') { throw new \RuntimeException('call_evidence_read_failed'); }
        return $row ?: null;
    }
    public function countQualifyingPriorCalls(string $caller,int $windowDays,int $minDurationSec,?string $excludeSid=null): int
    {
        global $wpdb; $sql="SELECT COUNT(*) FROM {$this->table()} WHERE caller_number=%s AND call_status='completed' AND call_duration_sec>=%d AND created_at >= (UTC_TIMESTAMP() - INTERVAL %d DAY)"; $args=[$caller,$minDurationSec,$windowDays];
        if($excludeSid){$sql.=' AND twilio_call_sid != %s';$args[]=$excludeSid;}
        return (int)$wpdb->get_var($wpdb->prepare($sql,$args));
    }
    public function listRecent(int $limit=50,int $offset=0): array { global $wpdb; return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} ORDER BY created_at DESC LIMIT %d OFFSET %d",$limit,$offset),ARRAY_A)?:[]; }
    public function countAll(): int { global $wpdb; return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table()}"); }
    private function formats(array $data): array { $ints=['id','tracking_number_id','qualification_threshold_sec','call_duration_sec','recording_duration_sec','is_repeat_caller','prior_qualifying_calls']; $out=[]; foreach(array_keys($data) as $k){$out[]=in_array($k,$ints,true)?'%d':'%s';} return $out; }
}
