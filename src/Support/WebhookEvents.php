<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Support;
defined('ABSPATH') || exit;
final class WebhookEvents
{
    public const VOICE_HIT='voice_hit'; public const VOICE_SIGNATURE_FAIL='voice_signature_fail'; public const VOICE_UNKNOWN_NUMBER='voice_unknown_number'; public const VOICE_UNHANDLED_THROW='voice_unhandled_throw';
    public const STATUS_HIT='status_hit'; public const STATUS_SIGNATURE_FAIL='status_signature_fail'; public const STATUS_UNMATCHED='status_unmatched';
    public const RECORDING_HIT='recording_hit'; public const RECORDING_SIGNATURE_FAIL='recording_signature_fail'; public const RECORDING_UNMATCHED='recording_unmatched';
    private const EVENTS=[self::VOICE_HIT,self::VOICE_SIGNATURE_FAIL,self::VOICE_UNKNOWN_NUMBER,self::VOICE_UNHANDLED_THROW,self::STATUS_HIT,self::STATUS_SIGNATURE_FAIL,self::STATUS_UNMATCHED,self::RECORDING_HIT,self::RECORDING_SIGNATURE_FAIL,self::RECORDING_UNMATCHED];
    private const PREFIX='se_cb_event_';
    public static function record(string $event,?string $detail=null): void
    { if(!in_array($event,self::EVENTS,true)) return; update_option(self::PREFIX.$event,['at'=>gmdate('c'),'detail'=>$detail!==null?mb_substr($detail,0,200):null],true); }
    public static function all(): array
    { $out=[]; foreach(self::EVENTS as $e){$v=get_option(self::PREFIX.$e,null);$out[$e]=is_array($v)&&isset($v['at'])?['at'=>(string)$v['at'],'detail'=>$v['detail']??null]:['at'=>null,'detail'=>null];} return $out; }
}
