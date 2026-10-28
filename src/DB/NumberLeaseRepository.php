<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\DB;
use SiteEngine\CallBridge\Support\Config;
defined('ABSPATH') || exit;
final class NumberLeaseRepository
{
    public function table(): string { global $wpdb; return $wpdb->prefix . 'se_cb_number_leases'; }
    /** One connection-owned lock per database/site prefix, across all sources. */
    public function allocationLockName(): string
    {
        global $wpdb;
        return 'secb_alloc_' . substr(sha1(DB_NAME . '|' . $wpdb->prefix),0,40);
    }
    public function expireStale(): int|false
    {
        global $wpdb;
        $n=$wpdb->query("UPDATE {$this->table()} SET status='expired', released_at=COALESCE(released_at,UTC_TIMESTAMP()) WHERE status='active' AND expires_at<=UTC_TIMESTAMP()");
        return is_int($n) && $wpdb->last_error==='' ? $n : false;
    }
    private function active(string $column,string $value,?bool &$readOk=null): ?array
    {
        global $wpdb;
        $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE {$column}=%s AND status='active' AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1",$value),ARRAY_A);
        $readOk=$wpdb->last_error==='' && ($r===null || (is_array($r) && $r!==[]));
        return $readOk && is_array($r) ? $r : null;
    }
    public function findActiveBySession(string $sessionId): ?array
    {
        return $this->expireStale()===false ? null : $this->active('session_id',$sessionId);
    }
    public function findActiveByTrackingNumber(string $e164): ?array
    {
        return $this->expireStale()===false ? null : $this->active('tracking_number',$e164);
    }
    private function eligible(array $lease,string $source,string $sessionId): bool
    {
        global $wpdb;
        if($lease['session_id']!==$sessionId || $lease['source']!==$source || $lease['status']!=='active') return false;
        $number=(new TrackingNumberRepository())->findById((int)$lease['tracking_number_id']);
        return $wpdb->last_error==='' && $number && (int)$number['is_active']===1
            && $number['source']===$source && $number['phone_number']===$lease['tracking_number'];
    }
    /** Attribution persistence runs after source/eligibility checks, under this same lock. */
    public function acquire(string $source,string $sessionId,?callable $persistSession=null): ?array
    {
        global $wpdb;
        $lock=$this->allocationLockName();
        $got=$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,2)',$lock));
        if($wpdb->last_error!=='' || (string)$got!=='1') return null;
        try {
            if($this->expireStale()===false) return null;
            $existing=$this->active('session_id',$sessionId,$readOk);
            if(!$readOk) return null;
            if($existing && !$this->eligible($existing,$source,$sessionId)) return null;
            // Reject unknown availability and preexisting duplicate reservations.
            $used=$wpdb->get_col("SELECT tracking_number_id FROM {$this->table()} WHERE status='active' AND expires_at>UTC_TIMESTAMP()");
            if($wpdb->last_error!=='' || !is_array($used)) return null;
            $used=array_map('intval',$used);
            if($existing && count(array_keys($used,(int)$existing['tracking_number_id'],true))!==1) return null;
            $sessions=$wpdb->get_col("SELECT session_id FROM {$this->table()} WHERE status='active' AND expires_at>UTC_TIMESTAMP()");
            if($wpdb->last_error!=='' || !is_array($sessions) || count(array_keys($sessions,$sessionId,true))!==($existing?1:0)) return null;
            if($existing){
                if($persistSession && $persistSession()!==true) return null;
                $expires=max((string)$existing['expires_at'],gmdate('Y-m-d H:i:s',time()+Config::leaseMinutes()*60));
                $ok=$wpdb->update($this->table(),['expires_at'=>$expires],['id'=>(int)$existing['id'],'status'=>'active','expires_at'=>$existing['expires_at']],['%s'],['%d','%s','%s']);
                if(($ok!==0 && $ok!==1) || $wpdb->last_error!=='') return null;
                // Same-second renewals may affect zero rows; prove persistence by readback.
                $saved=$this->active('session_id',$sessionId);
                if($wpdb->last_error!=='' || !$saved || (int)$saved['id']!==(int)$existing['id']
                    || $saved['expires_at']<$expires || !$this->eligible($saved,$source,$sessionId)) return null;
                return $saved;
            }
            $numbers=(new TrackingNumberRepository())->listActiveBySource($source);
            if($wpdb->last_error!=='' || $numbers===false) return null;
            // Preserve attribution capture for a successfully read but exhausted/empty pool.
            if($persistSession && $persistSession()!==true) return null;
            if(!$numbers) return null;
            // Valid leases stay sticky; new allocations select the current largest pool.
            $groups=[];
            foreach($numbers as $number){
                $group=trim((string)($number['pool_group']??''));
                $key=$group!==''?'pool:'.$group:'standalone:'.(int)$number['id'];
                $groups[$key][]=$number;
            }
            uasort($groups,static fn($a,$b)=>count($b)<=>count($a));
            $numbers=array_values(reset($groups));
            $available=array_values(array_filter($numbers,static fn($r)=>!in_array((int)$r['id'],$used,true)));
            if(!$available) return null;
            $idx=hexdec(substr(sha1($sessionId),0,8)) % count($available);
            $row=$available[$idx];
            $now=gmdate('Y-m-d H:i:s');
            $expires=gmdate('Y-m-d H:i:s',time()+Config::leaseMinutes()*60);
            $data=['session_id'=>$sessionId,'tracking_number_id'=>(int)$row['id'],'tracking_number'=>(string)$row['phone_number'],'source'=>$source,'leased_at'=>$now,'expires_at'=>$expires,'status'=>'active'];
            $ok=$wpdb->insert($this->table(),$data,['%s','%d','%s','%s','%s','%s','%s']);
            if($ok!==1 || $wpdb->last_error!=='' || (int)$wpdb->insert_id<=0) return null;
            $insertId=(int)$wpdb->insert_id;
            $saved=$this->active('session_id',$sessionId);
            if($wpdb->last_error!=='' || !$saved || (int)$saved['id']!==$insertId
                || $saved['expires_at']<$expires || !$this->eligible($saved,$source,$sessionId)) return null;
            return $saved;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        }
    }
}
