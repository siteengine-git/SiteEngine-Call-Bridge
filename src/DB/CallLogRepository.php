<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\DB;
defined('ABSPATH') || exit;
final class CallLogRepository
{
    public function table(): string { global $wpdb; return $wpdb->prefix . 'se_cb_calls'; }
    public function insert(array $data): int|false { global $wpdb; $ok=$wpdb->insert($this->table(),$data,$this->formats($data)); return $ok===false?false:(int)$wpdb->insert_id; }
    public function update(int $id,array $data): bool { global $wpdb; return $wpdb->update($this->table(),$data,['id'=>$id],$this->formats($data),['%d'])!==false; }
    public function findByCallSid(string $sid): ?array { global $wpdb; $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE twilio_call_sid=%s LIMIT 1",$sid),ARRAY_A); return $r?:null; }
    public function findById(int $id): ?array { global $wpdb; $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id=%d LIMIT 1",$id),ARRAY_A); return $r?:null; }
    public function countQualifyingPriorCalls(string $caller,int $windowDays,int $minDurationSec,?string $excludeSid=null): int
    {
        global $wpdb; $sql="SELECT COUNT(*) FROM {$this->table()} WHERE caller_number=%s AND call_status='completed' AND call_duration_sec>=%d AND created_at >= (UTC_TIMESTAMP() - INTERVAL %d DAY)"; $args=[$caller,$minDurationSec,$windowDays];
        if($excludeSid){$sql.=' AND twilio_call_sid != %s';$args[]=$excludeSid;}
        return (int)$wpdb->get_var($wpdb->prepare($sql,$args));
    }
    public function listRecent(int $limit=50,int $offset=0): array { global $wpdb; return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} ORDER BY created_at DESC LIMIT %d OFFSET %d",$limit,$offset),ARRAY_A)?:[]; }
    public function countAll(): int { global $wpdb; return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table()}"); }
    private function formats(array $data): array { $ints=['id','tracking_number_id','call_duration_sec','recording_duration_sec','is_repeat_caller','prior_qualifying_calls']; $out=[]; foreach(array_keys($data) as $k){$out[]=in_array($k,$ints,true)?'%d':'%s';} return $out; }
}
