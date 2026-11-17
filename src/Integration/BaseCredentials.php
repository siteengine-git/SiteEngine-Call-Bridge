<?php
/** Consumer of Base credentials-1 only; no secret storage or provider requests. */
declare(strict_types=1);
namespace SiteEngine\CallBridge\Integration;
defined('ABSPATH') || exit;
final class BaseCredentials
{
    private static bool $registered = false;
    private static bool $registrationFailed = false;
    /** Base 2.5 group links; never cast WP_Error into a URL. */
    public static function adminUrl(string $kind = 'webhook'): ?string
    {
        if (!in_array($kind, ['webhook','playback'], true) || !self::available()) return null;
        self::register();
        if (self::$registrationFailed) return null;
        if (!function_exists('siteengine_credentials_admin_url_v1')) return admin_url('admin.php?page=siteengine-credentials&resource=default');
        try { $url = siteengine_credentials_admin_url_v1('call-bridge', 'twilio-'.$kind, 'default'); }
        catch (\Throwable $e) { return null; }
        return !is_wp_error($url) && is_string($url) && $url !== '' ? $url : null;
    }
    public static function available(): bool
    {
        return function_exists('siteengine_credentials_contract_version')
            && siteengine_credentials_contract_version() === 'credentials-1'
            && function_exists('siteengine_register_credentials_v1')
            && function_exists('siteengine_credentials_runtime_read_v1')
            && function_exists('siteengine_credentials_metadata_v1');
    }
    private static function fields(string $kind): array
    { return $kind === 'webhook' ? ['account_sid'=>'SECB_TWILIO_ACCOUNT_SID','auth_token'=>'SECB_TWILIO_AUTH_TOKEN'] : ['api_key_sid'=>'SECB_TWILIO_API_KEY_SID','api_key_secret'=>'SECB_TWILIO_API_KEY_SECRET']; }
    public static function external(string $kind): array
    {
        $v=[]; foreach(self::fields($kind) as $field=>$constant) { $v[$field]=defined($constant) && is_string(constant($constant)) ? constant($constant) : ''; }
        return $v;
    }
    public static function externallyManaged(string $kind): bool
    { foreach(self::fields($kind) as $constant)if(defined($constant)&&constant($constant)!==''&&constant($constant)!==null)return true;return false; }
    public static function validate(mixed $value, string $kind): bool
    {
        if(!is_array($value)||array_diff(array_keys($value),array_keys(self::fields($kind)))!==[]||count($value)!==2)return false;
        foreach($value as$v)if(!is_string($v)||$v===''||trim($v)!==$v||preg_match('/\A\*+\z/',$v))return false;
        return $kind==='webhook'
            ? preg_match('/\AAC[a-fA-F0-9]{32}\z/',$value['account_sid'])===1 && preg_match('/\A[a-fA-F0-9]{32}\z/',$value['auth_token'])===1
            : preg_match('/\ASK[a-fA-F0-9]{32}\z/',$value['api_key_sid'])===1 && preg_match('/\A[!-~]{16,128}\z/',$value['api_key_secret'])===1;
    }
    public static function register(): void
    {
        if(self::$registered||!self::available())return;
        self::$registered=true;
        foreach(['webhook','playback']as$kind){
            $result=siteengine_register_credentials_v1('call-bridge','twilio-'.$kind,[
                'label'=>$kind==='webhook'?'Call Bridge — required Twilio Account SID and Webhook Auth Token':'Call Bridge — optional Twilio playback API key',
                'kind'=>'set','fields'=>array_keys(self::fields($kind)),'purposes'=>['runtime'],
                // Both inputs are password fields. No prefill/readback or partial pair.
                'validator'=>static fn($value,$resource)=>$resource==='default'&&self::validate($value,$kind),
                'authority'=>static fn($resource)=>$resource!=='default'||self::externallyManaged($kind)?'external':'base',
                'allow_replace'=>true,'allow_remove'=>false,'allow_rollback'=>false,
            ]);
            if(is_wp_error($result))self::$registrationFailed=true;
        }
    }
    /** External complete pairs win; never mix external and Base fields. */
    public static function pair(string $kind): ?array
    {
        if(self::externallyManaged($kind)){
            $v=self::external($kind);return in_array('',array_values($v),true)?null:$v;
        }
        if(!self::available())return null;
        self::register();
        if(self::$registrationFailed)return null;
        try{$r=siteengine_credentials_runtime_read_v1('call-bridge','twilio-'.$kind,'default','runtime');}
        catch(\Throwable $e){return null;}
        if(is_wp_error($r)||!is_array($r)||!self::validate($r['value']??null,$kind))return null;
        return $r['value'];
    }
    /** Safe metadata only: never return a credential value, version UUID or key. */
    public static function status(string $kind): array
    {
        if(self::externallyManaged($kind)){
            $v=self::external($kind);$complete=!in_array('',array_values($v),true);$valid=$complete&&self::validate($v,$kind);
            return ['source'=>'External configuration','configured'=>$valid,'local'=>$valid?'Format checked locally':'Incomplete or invalid pair',
                'error'=>$valid?'':'external_pair_invalid','action'=>$valid?'External values are authoritative. WP replacement is blocked; preserve the file. To adopt Base, explicitly remove BOTH external fields under a backed-up maintenance change before staging a complete pair.':'Complete/correct both matching external fields privately. Base fallback and shadowed saves are blocked.'];
        }
        if(!self::available())return ['source'=>'Unavailable','configured'=>false,'local'=>'Not checked','error'=>'base_unavailable','action'=>'Activate a Base version supporting credentials-1, or use the existing external configuration route.'];
        self::register();
        if(self::$registrationFailed)return ['source'=>'Base protected credentials','configured'=>false,'local'=>'Registration failed','error'=>'registration_failed','action'=>'Ask CB’s owner to resolve a conflicting credential definition; do not stage under another module or resource.'];
        try{$m=siteengine_credentials_metadata_v1('call-bridge','twilio-'.$kind,'default');}catch(\Throwable$e){$m=null;}
        $error=is_wp_error($m)?$m->get_error_code():(!is_array($m)?'unavailable':'');
        $actions=['key_unavailable'=>'The host owner must provision/check Base’s protected installation key using its existing host tool. Do not generate a replacement key.',
            'storage_unavailable'=>'Open SiteEngine → Credentials and initialize/check credential tables after host key provisioning.',
            'integrity_failure'=>'Stop replacement and ask Base’s owner to reconcile the installation key and encrypted data; do not reset them.'];
        $configured=$error===''&&!empty($m['present'])&&self::pair($kind)!==null;
        return ['source'=>'Base protected credentials','configured'=>$configured,'local'=>$configured?'Validated locally; provider authentication unverified':'Not configured/activated',
            'error'=>$error,'action'=>$error!==''?($actions[$error]??'Reload protected credential status and resolve the Base error before retrying; no secret was exposed.'):
                ($configured?'Use resource default. To replace, stage the COMPLETE matching pair, then activate the exact staged version. Existing active material remains until activation.':'Open SiteEngine → Credentials, find this Call Bridge section, use resource default, stage the complete pair and activate it. Both blank retains material; partial/masked/invalid input is rejected.')];
    }
}
