<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Gateway;
use SiteEngine\CallBridge\DB\CallLogRepository;
/** Strict authenticated handoff boundary; raw forwarding headers confer no trust. */
final class Receiver
{
    public static function verify(string $raw, string $signature, array $clients, string $nonceDir, string $webroot): array
    {
        Protocol::reject(strlen($raw) > Protocol::LIMIT, 413, 'handoff_too_large');
        try { $v = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { throw new Rejection(400, 'invalid_envelope'); }
        $fields = ['v','client','destination','event','call_sid','account_sid','number','gateway_base','binding','method','query','form','timestamp','nonce'];
        Protocol::reject(!is_array($v) || count($v) !== count($fields) || array_diff($fields, array_keys($v)) !== [] || $v['v'] !== 1, 400, 'invalid_envelope');
        Protocol::reject(!Protocol::identifier($v['client']) || !isset($clients[$v['client']]), 403, 'client_not_allowed');
        $c = $clients[$v['client']]; $key = $c['secret'] ?? null;
        Protocol::reject(!is_string($key) || strlen($key) < 32 || !hash_equals(hash_hmac('sha256', $raw, $key), $signature), 403, 'handoff_authentication_rejected');
        Protocol::reject(!is_int($v['timestamp']) || abs(time() - $v['timestamp']) > 60 || !is_string($v['nonce']) || !preg_match('/\A[a-f0-9]{64}\z/', $v['nonce']), 403, 'handoff_freshness_rejected');
        Protocol::reject(!in_array($v['event'], ['voice','whisper','status','recording'], true)
            || $v['method'] !== ($v['event'] === 'whisper' ? 'GET' : 'POST') || !Protocol::flat($v['form']) || !Protocol::flat($v['query']), 400, 'handoff_shape_rejected');
        Protocol::reject(!Protocol::callSid($v['call_sid']) || !Protocol::accountSid($v['account_sid'])
            || $v['destination'] !== ($c['destination'] ?? null) || $v['account_sid'] !== ($c['account_sid'] ?? null)
            || !in_array($v['number'], $c['numbers'] ?? [], true) || !Protocol::httpsUrl($v['gateway_base'])
            || $v['gateway_base'] !== ($c['gateway_base'] ?? null) || !is_string($v['binding']) || !preg_match('/\A[a-f0-9]{64}\z/', $v['binding']), 403, 'handoff_scope_rejected');
        $p = $v['method'] === 'POST' ? $v['form'] : $v['query'];
        Protocol::reject(($p['AccountSid'] ?? '') !== $v['account_sid'], 403, 'handoff_account_rejected');
        if ($v['event'] === 'whisper') {
            Protocol::reject($v['form'] !== [] || !Protocol::callSid($p['CallSid'] ?? null) || ($p['ParentCallSid'] ?? '') !== $v['call_sid'], 403, 'handoff_parent_rejected');
        } else { Protocol::reject(($p['CallSid'] ?? '') !== $v['call_sid'], 403, 'handoff_call_rejected'); }
        if ($v['event'] === 'voice') { Protocol::reject(($p['To'] ?? '') !== $v['number'], 403, 'handoff_number_rejected'); }
        {
            // Reject callbacks that the existing controllers would otherwise acknowledge
            // as unmatched. Never create a call or infer one from a current lease.
            try { $row = (new CallLogRepository())->findByCallSid($v['call_sid'], true); }
            catch (\Throwable $e) { throw new Rejection(503, 'handoff_call_lookup_failed'); }
            Protocol::reject($row === null && $v['event'] !== 'voice', 503, 'handoff_call_not_yet_persisted');
            Protocol::reject($row !== null && ($row['tracking_number'] !== $v['number'] || $row['transaction_id'] !== 'twilio:' . $v['call_sid']), 403, 'handoff_persisted_identity_rejected');
        }
        Protocol::nonce(Protocol::directory($nonceDir, $webroot), $v['client'] . ':' . $v['destination'] . ':' . $v['nonce']);
        return $v;
    }
}
