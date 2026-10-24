<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\DB;
defined('ABSPATH') || exit;
final class AttributionSessionRepository
{
    public function table(): string { global $wpdb; return $wpdb->prefix . 'se_cb_attribution_sessions'; }
    public function findBySessionId(string $sessionId): ?array { global $wpdb; $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE session_id=%s LIMIT 1",$sessionId),ARRAY_A); return $r?:null; }
    public function upsert(array $data): int|false
    {
        global $wpdb; if(empty($data['session_id'])) return false;
        $existing=$this->findBySessionId((string)$data['session_id']);
        if(!$existing){$ok=$wpdb->insert($this->table(),$data,$this->formats($data)); return $ok===false?false:(int)$wpdb->insert_id;}
        $ok=$wpdb->update($this->table(),$data,['id'=>(int)$existing['id']],$this->formats($data),['%d']);
        return $ok===false?false:(int)$existing['id'];
    }
    public function purgeExpired(): int { global $wpdb; $n=$wpdb->query("DELETE FROM {$this->table()} WHERE expires_at < UTC_TIMESTAMP()"); return is_int($n)?$n:0; }
    private function formats(array $data): array { $out=[]; foreach(array_keys($data) as $k){$out[]=$k==='id'?'%d':'%s';} return $out; }
}
