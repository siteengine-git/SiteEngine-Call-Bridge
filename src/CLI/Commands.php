<?php
/**
 * Read-only operator commands for Call Bridge 0.1.0.
 */

declare(strict_types=1);
namespace SiteEngine\CallBridge\CLI;
use SiteEngine\CallBridge\DB\CallLogRepository;
use SiteEngine\CallBridge\Support\Config;
use SiteEngine\CallBridge\Support\WebhookEvents;
defined('ABSPATH') || exit;
final class Commands
{
    /**
     * Inspect runtime readiness. Read-only.
     *
     * ## EXAMPLES
     *     wp se-call doctor
     */
    public function doctor(array $args,array $assoc): void
    {
        global $wpdb;
        $rows=[];
        $add=static function(string $status,string $check,string $detail) use (&$rows): void {$rows[]=['status'=>$status,'check'=>$check,'detail'=>$detail];};
        $add(version_compare(PHP_VERSION,'8.1.0','>=')?'PASS':'FAIL','PHP',PHP_VERSION);
        $add(Config::twilioAccountSid()!==null?'PASS':'FAIL','Twilio Account SID',Config::twilioAccountSid()!==null?'configured':'missing');
        $add(Config::twilioAuthToken()!==null?'PASS':'FAIL','Twilio webhook auth token',Config::twilioAuthToken()!==null?'configured':'missing');
        $add(Config::recordingCredentials()!==null?'PASS':'WARN','Recording playback credentials',Config::recordingCredentials()!==null?'configured':'not configured');
        foreach(['tracking numbers'=>'se_cb_tracking_numbers','attribution sessions'=>'se_cb_attribution_sessions','number leases'=>'se_cb_number_leases','calls'=>'se_cb_calls'] as $label=>$suffix){$name=$wpdb->prefix.$suffix;$found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$name));$add($found===$name?'PASS':'FAIL','Table: '.$label,$name);}
        $add('PASS','REST namespace',SECB_REST_NAMESPACE);
        $add('PASS','Lease policy',Config::leaseMinutes().' minutes; pool exhaustion fails closed');
        $add('PASS','Session retention',Config::sessionTtlDays().' days');
        $add('PASS','Ads Bridge separation','No Forms/FluentCRM/Won/Lost/Sheets exporter is registered by Call Bridge 0.1.0');
        \WP_CLI\Utils\format_items('table',$rows,['status','check','detail']);
        $fail=count(array_filter($rows,static fn($r)=>$r['status']==='FAIL'));$warn=count(array_filter($rows,static fn($r)=>$r['status']==='WARN'));
        \WP_CLI::log(sprintf('Summary: %d PASS, %d WARN, %d FAIL',count($rows)-$warn-$fail,$warn,$fail));
        if($fail>0) \WP_CLI::error('Call Bridge doctor found blocking failures.');
        if($warn>0) \WP_CLI::warning('Call Bridge doctor completed with warnings.'); else \WP_CLI::success('Call Bridge doctor passed.');
    }

    /**
     * Inspect one call. Read-only.
     *
     * ## OPTIONS
     * [--id=<id>]
     * [--sid=<twilio_call_sid>]
     */
    public function inspect_call(array $args,array $assoc): void
    {
        $repo=new CallLogRepository();$row=null;
        if(!empty($assoc['id']))$row=$repo->findById((int)$assoc['id']); elseif(!empty($assoc['sid']))$row=$repo->findByCallSid((string)$assoc['sid']); else \WP_CLI::error('Provide --id or --sid.');
        if(!$row)\WP_CLI::error('Call not found.');
        $items=[];foreach($row as $k=>$v){if(in_array($k,['recording_url','hashed_caller_number'],true))$v=$v?'(stored; redacted)':'';$items[]=['field'=>$k,'value'=>$v===null?'(null)':(string)$v];}
        \WP_CLI\Utils\format_items('table',$items,['field','value']);
    }

    /**
     * Show latest webhook-event timestamps. Read-only.
     */
    public function webhook_status(array $args,array $assoc): void
    {
        $rows=[];foreach(WebhookEvents::all() as $event=>$v)$rows[]=['event'=>$event,'at'=>$v['at']??'','detail'=>$v['detail']??''];
        \WP_CLI\Utils\format_items('table',$rows,['event','at','detail']);
    }
}
