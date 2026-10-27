<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Support;
defined('ABSPATH') || exit;
/** Booleans/aggregate reads only. No installer, lease lookup, writes or HTTP. */
final class Readiness
{
    private static ?array $cached=null;
    public static function current(): array
    {
        return self::$cached ??= self::evaluate(self::inspect());
    }
    public static function inspect(): array
    {
        global $wpdb;
        $f=[
            'external'=>!defined('SECB_CONFIG_FILE')?'not_defined':
                (is_string(SECB_CONFIG_FILE)&&SECB_CONFIG_FILE!==''&&is_readable(SECB_CONFIG_FILE)?'readable':'unreadable'),
            'account'=>Config::twilioAccountSid()!==null, 'token'=>Config::twilioAuthToken()!==null,
            'playback'=>(Config::twilioApiKeySid()!==null&&Config::twilioApiKeySecret()!==null)
                ||(Config::twilioAccountSid()!==null&&Config::twilioAuthToken()!==null),
            'url'=>self::publicBaseUrl(), 'lease_minutes'=>Config::leaseMinutes(),
            'session_days'=>Config::sessionTtlDays(), 'debug'=>Config::debugLog(),
            'passthrough'=>Config::passthroughMode(), 'extensions'=>[], 'tables'=>[],
            'numbers'=>null, 'db_current'=>false, 'gc'=>false, 'inspection_error'=>false,
        ];
        foreach (['mysqli','json','hash','mbstring','openssl'] as $name) $f['extensions'][$name]=extension_loaded($name);
        try {
            $f['db_current']=(string)get_option('se_cb_db_version','')===SECB_DB_VERSION;
            $f['gc']=(bool)wp_next_scheduled('se_cb_gc');
            foreach (['tracking_numbers','attribution_sessions','number_leases','calls'] as $suffix) {
                $table=$wpdb->prefix.'se_cb_'.$suffix;
                $f['tables'][$suffix]=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))===$table;
            }
            if ($f['tables']['tracking_numbers']) {
                $table=$wpdb->prefix.'se_cb_tracking_numbers';
                $row=$wpdb->get_row("SELECT COUNT(*) AS total,
                    COALESCE(SUM(is_active=1),0) AS active,
                    COALESCE(SUM(is_active=1 AND (TRIM(phone_number)='' OR TRIM(forward_to)='')),0) AS incomplete,
                    COALESCE(SUM(is_active=1 AND record_calls<>0),0) AS recording FROM {$table}",ARRAY_A);
                if (is_array($row)&&isset($row['total'],$row['active'],$row['incomplete'],$row['recording'])) {
                    $f['numbers']=array_map('intval',$row);
                } else $f['inspection_error']=true;
            }
        } catch (\Throwable $e) {
            $f['inspection_error']=true; // Never expose raw database errors.
        }
        return $f;
    }
    /** Pure composition separates disabled options from missing prerequisites. */
    public static function evaluate(array $f): array
    {
        $rows=[];$missing=[];
        $add=static function(string $name,string $state,string $detail,bool $blocks=false) use (&$rows,&$missing):void {
            $rows[]=['name'=>$name,'state'=>$state,'detail'=>$detail];
            if($blocks)$missing[]=$name;
        };
        $add('External configuration',$f['external']==='readable'?'Configured':($f['external']==='unreadable'?'Needs attention':'Recommended'),
            $f['external']==='readable'?'Referenced external file is readable':
                ($f['external']==='unreadable'?'Ask the administrator to resolve the unreadable reference':'Existing inline constants still work; an external secrets file is recommended'),
            $f['external']==='unreadable');
        $add('Account SID',$f['account']?'Present, unverified':'Missing','Required account identity; values are never displayed',!$f['account']);
        $add('Webhook Auth Token',$f['token']?'Present, unverified':'Missing','Required for signatures; API keys do not replace it',!$f['token']);
        $add('Public webhook base',$f['url']!==''?'Configured, unverified':'Needs attention','Public HTTPS base without embedded credentials, query or fragment; verify the actual proxy URL',$f['url']==='');
        $extensions=!in_array(false,$f['extensions'],true);
        $add('PHP extensions',$extensions?'Present':'Missing',implode(', ',array_keys($f['extensions'])).'; current PHP runtime only',!$extensions);
        $tables=count($f['tables'])===4&&!in_array(false,$f['tables'],true);
        $add('Database',$tables&&$f['db_current']?'Tables/version present':'Needs attention','Existence/version only, not a full column/index validation; this page does not migrate',!$tables||!$f['db_current']);
        $add('Garbage collection',$f['gc']?'Scheduled':'Missing','Hourly se_cb_gc registration only, not execution proof; this page does not schedule or run it',!$f['gc']);
        $n=$f['numbers'];
        $state=$n===null?'Not inspected':($n['total']===0?'No numbers configured':($n['active']===0?'All numbers inactive':($n['incomplete']>0?'Incomplete routing fields':'Active rows present')));
        $add('Tracking numbers',$state,'Review each source/pool and forwarding destination. Presence does not prove ownership or delivery',$n===null||$n['active']===0||$n['incomplete']>0);
        $recording=$n!==null&&$n['recording']>0;
        $add('Recording',$n===null?'Not inspected':($recording?'Enabled on active number rows':'Off on active numbers'),'Defaults off per number; this page does not change flags/notices');
        $add('Recording playback credentials',$n===null?'Not inspected':(!$recording?'Not required while recording is off':($f['playback']?'Present, unverified':'Missing')),
            'Optional API Key SID/Secret preferred; Account SID/Auth Token fallback. Playback acceptance is separate',$recording&&!$f['playback']);
        $add('Debug logging',$f['debug']?'On':'Off','Off is a deliberate value, not missing setup');
        $add('Operating mode',$f['passthrough']?'Passthrough enabled':'Normal tracking','Passthrough bypasses normal attribution/logging, not per-number recording');
        if($f['inspection_error'])$add('Inspection','Incomplete','Some reads failed; inspect diagnostics privately',true);
        $complete=$missing===[];
        return ['facts'=>$f,'rows'=>$rows,'missing'=>$missing,'configuration_complete'=>$complete,
            'label'=>!$complete?'Configuration needed':($f['passthrough']?'Passthrough enabled; calling unverified':'Configuration complete; calling unverified'),
            'acceptance'=>'Pending: live calling, attribution, signatures through the proxy and secured playback are not accepted by configuration checks.'];
    }
    public static function publicBaseUrl(): string
    {
        $url=Config::webhookBaseUrl();$p=parse_url($url);
        if(!is_array($p)||($p['scheme']??'')!=='https'||empty($p['host'])
            ||isset($p['user'])||isset($p['pass'])||isset($p['query'])||isset($p['fragment'])
            ||!filter_var($url,FILTER_VALIDATE_URL))return '';
        return $url;
    }
    public static function webhookUrls(): array
    {
        $base=self::publicBaseUrl();if($base==='')return [];
        $root=$base.'/wp-json/'.SECB_REST_NAMESPACE;
        return [
            ['label'=>'Inbound Voice','method'=>'POST','url'=>$root.'/twilio/voice','use'=>'Configure the dedicated number only after provider-change approval'],
            ['label'=>'Dial status','method'=>'POST','url'=>$root.'/twilio/status','use'=>'Generated in the Dial action'],
            ['label'=>'Recording callback','method'=>'POST','url'=>$root.'/twilio/recording','use'=>'Generated for recording-enabled calls'],
            ['label'=>'Recipient whisper','method'=>'GET','url'=>$root.'/twilio/whisper','use'=>'Generated with a signed query; do not configure a static message URL'],
        ];
    }
}
