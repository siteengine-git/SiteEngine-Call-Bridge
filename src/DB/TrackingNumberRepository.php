<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\DB;
defined('ABSPATH') || exit;
final class TrackingNumberRepository
{
    public function table(): string { global $wpdb; return $wpdb->prefix . 'se_cb_tracking_numbers'; }
    public function findActiveByPhone(string $e164): ?array { global $wpdb; $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE phone_number=%s AND is_active=1 LIMIT 1",$e164),ARRAY_A); return $r?:null; }
    public function findById(int $id): ?array { global $wpdb; $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id=%d LIMIT 1",$id),ARRAY_A); return $r?:null; }
    public function listAll(): array { global $wpdb; return $wpdb->get_results("SELECT * FROM {$this->table()} ORDER BY is_active DESC, display_label ASC, phone_number ASC",ARRAY_A)?:[]; }
    public function listActiveBySource(string $source): array|false { global $wpdb; $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE source=%s AND is_active=1 ORDER BY id ASC",$source),ARRAY_A); return $wpdb->last_error==='' && is_array($rows) ? $rows : false; }
    public function activeForwardNumbers(): array { global $wpdb; $rows=$wpdb->get_col("SELECT DISTINCT forward_to FROM {$this->table()} WHERE is_active=1 AND forward_to<>''"); return array_values(array_filter(array_map('strval',$rows?:[]))); }
    public function insert(array $data): int|false { global $wpdb; $ok=$wpdb->insert($this->table(),$data,$this->formats($data)); return $ok===false?false:(int)$wpdb->insert_id; }
    public function update(int $id,array $data): bool { global $wpdb; return $wpdb->update($this->table(),$data,['id'=>$id],$this->formats($data),['%d'])!==false; }
    public function delete(int $id): bool { global $wpdb; return $wpdb->delete($this->table(),['id'=>$id],['%d'])!==false; }
    private function formats(array $data): array { $ints=['id','min_duration_sec','record_calls','repeat_window_days','is_active']; $out=[]; foreach(array_keys($data) as $k){$out[]=in_array($k,$ints,true)?'%d':'%s';} return $out; }
}
