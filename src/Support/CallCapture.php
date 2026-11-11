<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Support;
defined('ABSPATH') || exit;

/** First-insert observations only. Never used to enrich an existing call. */
final class CallCapture
{
    public static function encode(array $number, ?array $lease, ?array $session, string $identity): string
    {
        return json_encode([
            'version'=>1,
            'call_identity'=>$identity,
            'captured_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
            'matched_session_id'=>$session['session_id'] ?? null,
            'number'=>[
                'id'=>(int) $number['id'],
                'tracking_number'=>(string) $number['phone_number'],
                'forwarded_to'=>(string) $number['forward_to'],
                'source'=>(string) ($number['source'] ?? ''),
            ],
            'lease'=>$lease === null ? null : [
                'id'=>(int) $lease['id'],
                'session_id'=>(string) $lease['session_id'],
                'tracking_number_id'=>(int) $lease['tracking_number_id'],
                'tracking_number'=>(string) $lease['tracking_number'],
                'source'=>(string) $lease['source'],
                'pool_group'=>$lease['pool_group'] ?? null,
                'leased_at'=>(string) $lease['leased_at'],
                'expires_at'=>(string) $lease['expires_at'],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** Validate provenance against persisted call fields, never live tables. */
    public static function decode(array $row): ?array
    {
        $raw = $row['call_capture_json'] ?? null;
        if ($raw === null || $raw === '') return null;
        $c = json_decode((string) $raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($c) || ($c['version'] ?? null) !== 1 || ($c['call_identity'] ?? null) !== $row['transaction_id'] || !array_key_exists('matched_session_id', $c) || !is_array($c['number'] ?? null)
            || !isset($c['captured_at_utc']) || !is_string($c['captured_at_utc'])) throw new \UnexpectedValueException('invalid_capture');
        $time = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $c['captured_at_utc'], new \DateTimeZone('UTC'));
        if (!$time || $time->format('Y-m-d\TH:i:s\Z') !== $c['captured_at_utc']) throw new \UnexpectedValueException('invalid_capture_time');
        $n = $c['number'];
        if (($n['id'] ?? null) !== (int) $row['tracking_number_id']
            || ($n['tracking_number'] ?? null) !== $row['tracking_number']
            || ($n['forwarded_to'] ?? null) !== $row['forwarded_to']
            || ($n['source'] ?? null) !== $row['source']
            || ($c['matched_session_id'] ?? null) !== ($row['matched_session_id'] ?? null)) throw new \UnexpectedValueException('capture_identity_conflict');
        if (!array_key_exists('lease', $c)) throw new \UnexpectedValueException('invalid_capture_lease');
        if ($c['lease'] !== null) {
            $l = $c['lease'];
            if (!is_array($l) || !is_int($l['id'] ?? null) || $l['id'] < 1
                || ($l['tracking_number_id'] ?? null) !== (int) $row['tracking_number_id']
                || ($l['tracking_number'] ?? null) !== $row['tracking_number']
                || !is_string($l['session_id'] ?? null) || !is_string($l['source'] ?? null)
                || !is_string($l['leased_at'] ?? null) || !is_string($l['expires_at'] ?? null)
                || ($c['matched_session_id'] !== null && $l['session_id'] !== $c['matched_session_id'])
                || !array_key_exists('pool_group', $l) || ($l['pool_group'] !== null && !is_string($l['pool_group']))) throw new \UnexpectedValueException('capture_lease_conflict');
        }
        if ($c['lease'] !== null) foreach (['leased_at','expires_at'] as $key) {
            $v=$c['lease'][$key];$t=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$v,new \DateTimeZone('UTC'));
            if(!$t||$t->format('Y-m-d H:i:s')!==$v)throw new \UnexpectedValueException('invalid_lease_time');
        }
        return $c;
    }
}
