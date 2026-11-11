<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Integration;

use SiteEngine\CallBridge\DB\CallLogRepository;
use SiteEngine\CallBridge\Support\CallCapture;

defined('ABSPATH') || exit;

/** Trusted, same-site PHP boundary. No HTTP route, hooks, writes or lease lookup. */
final class CallEvidence
{
    public const CONTRACT = 'siteengine.call-evidence.v1';

    public static function read(string $identity): array
    {
        $result = ['contract'=>self::CONTRACT, 'status'=>'invalid_identity', 'call'=>null, 'unavailable'=>[]];
        if (!preg_match('/\Atwilio:(CA[0-9a-fA-F]{32})\z/', $identity, $match)) {
            return $result;
        }
        try {
            $row = (new CallLogRepository())->findEvidenceByCallSid($match[1]);
        } catch (\Throwable $e) {
            // Never expose SQL, connection information or exception text to callers.
            $result['status'] = 'storage_error';
            return $result;
        }
        if ($row === null) {
            $result['status'] = 'not_found';
            return $result;
        }
        if (($row['transaction_id'] ?? null) !== $identity || ($row['twilio_call_sid'] ?? null) !== $match[1]) {
            $result['status'] = 'identity_conflict';
            return $result;
        }
        try { $capture = CallCapture::decode($row); }
        catch (\Throwable $e) { $result['status'] = 'capture_invalid'; return $result; }

        $missing = [];
        $field = static function (string $column, string $path, bool $integer = false) use ($row, &$missing) {
            $value = $row[$column] ?? null;
            if ($value === null || $value === '') {
                $missing[$path] = 'not_persisted';
                return null;
            }
            return $integer ? (int) $value : (string) $value;
        };
        $acquisition = [];
        foreach (['matched_session_id','gclid','gbraid','wbraid','fbclid','msclkid','utm_source','utm_medium','utm_campaign','utm_term','utm_content','landing_page'] as $key) {
            $acquisition[$key] = $field($key, 'acquisition.'.$key);
        }
        $assignment = [];
        foreach (['tracking_number_id','tracking_number','forwarded_to','source'] as $key) {
            $assignment[$key] = $field($key, 'number_assignment.'.$key, $key === 'tracking_number_id');
        }
        foreach (['lease_id','pool_group','leased_at','expires_at'] as $key) {
            $assignment[$key] = null;
            $missing['number_assignment.'.$key] = 'not_snapshotted_on_call';
        }
        if ($capture !== null && $capture['lease'] !== null) {
            $lease = $capture['lease'];
            foreach (['lease_id'=>'id','pool_group'=>'pool_group','leased_at'=>'leased_at','expires_at'=>'expires_at'] as $key=>$column) {
                $assignment[$key] = $lease[$column];
                if ($lease[$column] !== null) unset($missing['number_assignment.'.$key]);
                else $missing['number_assignment.'.$key]='not_snapshotted_on_lease';
            }
        }
        $call = [
            'identity'=>$row['transaction_id'],
            'provider'=>'twilio',
            'provider_call_sid'=>$row['twilio_call_sid'],
            'record_id'=>(int) $row['id'],
            'created_at'=>$field('created_at', 'created_at'),
            'updated_at'=>$field('updated_at', 'updated_at'),
            'timestamp_timezone'=>null,
            'capture'=>[
                'version'=>$capture['version'] ?? null,
                'captured_at_utc'=>$capture['captured_at_utc'] ?? null,
                'lease_timestamp_timezone'=>$capture !== null && $capture['lease'] !== null ? 'UTC' : null,
            ],
            'acquisition'=>$acquisition,
            'number_assignment'=>$assignment,
            'outcome'=>[
                'status'=>$field('call_status', 'outcome.status'),
                'duration_sec'=>$field('call_duration_sec', 'outcome.duration_sec', true),
                'technical_qualification'=>$field('qualification_status', 'outcome.technical_qualification'),
                'qualification_threshold_sec'=>$field('qualification_threshold_sec', 'outcome.qualification_threshold_sec', true),
                'is_repeat_caller'=>$field('is_repeat_caller', 'outcome.is_repeat_caller', true),
                'prior_qualifying_calls'=>$field('prior_qualifying_calls', 'outcome.prior_qualifying_calls', true),
            ],
            'consent_receipt_id'=>$field('consent_receipt_id', 'consent_receipt_id'),
            'authoritative_consent_linkage'=>null,
            'inquiry_identity'=>null,
            'business_qualification'=>null,
            'advertising_eligibility'=>null,
            'durable_child_leg_mapping'=>null,
        ];
        $missing += [
            'capture.captured_at_utc'=>'not_snapshotted_on_call',
            'timestamp_timezone'=>'not_snapshotted_on_call',
            'authoritative_consent_linkage'=>'not_implemented',
            'inquiry_identity'=>'owned_by_ab_not_asserted_by_cb',
            'business_qualification'=>'owned_by_ab_not_asserted_by_cb',
            'advertising_eligibility'=>'owned_by_ab_not_asserted_by_cb',
            'durable_child_leg_mapping'=>'not_implemented',
        ];
        if ($capture !== null) unset($missing['capture.captured_at_utc']);
        if ($call['outcome']['qualification_threshold_sec'] === null) $missing['outcome.qualification_threshold_sec']='not_snapshotted_on_call';
        return ['contract'=>self::CONTRACT, 'status'=>'found', 'call'=>$call, 'unavailable'=>$missing];
    }
}
