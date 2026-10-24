<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\DB;
use SiteEngine\CallBridge\Support\Config;
use SiteEngine\CallBridge\Support\TrackingNumberResolver;
defined('ABSPATH') || exit;
final class NumberLeaseRepository
{
    public function table(): string { global $wpdb; return $wpdb->prefix . 'se_cb_number_leases'; }
    public function expireStale(): int
    {
        global $wpdb;
        $n=$wpdb->query("UPDATE {$this->table()} SET status='expired', released_at=COALESCE(released_at,UTC_TIMESTAMP()) WHERE status='active' AND expires_at<=UTC_TIMESTAMP()");
        return is_int($n)?$n:0;
    }
    public function findActiveBySession(string $sessionId): ?array
    {
        global $wpdb; $this->expireStale();
        $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE session_id=%s AND status='active' AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1",$sessionId),ARRAY_A);
        return $r?:null;
    }
    public function findActiveByTrackingNumber(string $e164): ?array
    {
        global $wpdb; $this->expireStale();
        $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE tracking_number=%s AND status='active' AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1",$e164),ARRAY_A);
        return $r?:null;
    }
    public function acquire(string $source,string $sessionId): ?array
    {
        global $wpdb;
        $lock='secb_lease_' . substr(sha1($source),0,24);
        $got=(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,2)',$lock));
        if($got!==1) return null;
        try {
            $this->expireStale();
            $existing=$this->findActiveBySession($sessionId);
            if($existing){
                $expires=gmdate('Y-m-d H:i:s',time()+Config::leaseMinutes()*60);
                $wpdb->update($this->table(),['expires_at'=>$expires],['id'=>(int)$existing['id']],['%s'],['%d']);
                $existing['expires_at']=$expires;
                return $existing;
            }
            $numbers=(new TrackingNumberRepository())->listActiveBySource($source);
            if(!$numbers) return null;
            // Preserve LeadEngine's source/pool intent: choose the largest
            // configured pool for this source. A standalone number is its own
            // one-number group and is never silently merged with another
            // standalone number.
            $groups=[];
            foreach($numbers as $number){
                $group=trim((string)($number['pool_group']??''));
                $key=$group!==''?'pool:'.$group:'standalone:'.(int)$number['id'];
                $groups[$key][]=$number;
            }
            uasort($groups,static fn($a,$b)=>count($b)<=>count($a));
            $numbers=array_values(reset($groups));
            $used=$wpdb->get_col("SELECT tracking_number_id FROM {$this->table()} WHERE status='active' AND expires_at>UTC_TIMESTAMP()")?:[];
            $used=array_map('intval',$used);
            $available=array_values(array_filter($numbers,static fn($r)=>!in_array((int)$r['id'],$used,true)));
            if(!$available) return null;
            $idx=hexdec(substr(sha1($sessionId),0,8)) % count($available);
            $row=$available[$idx];
            $now=gmdate('Y-m-d H:i:s');
            $expires=gmdate('Y-m-d H:i:s',time()+Config::leaseMinutes()*60);
            $data=['session_id'=>$sessionId,'tracking_number_id'=>(int)$row['id'],'tracking_number'=>(string)$row['phone_number'],'source'=>$source,'leased_at'=>$now,'expires_at'=>$expires,'status'=>'active'];
            $ok=$wpdb->insert($this->table(),$data,['%s','%d','%s','%s','%s','%s','%s']);
            if($ok===false) return null;
            $data['id']=(int)$wpdb->insert_id;
            return $data;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        }
    }
}
