<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Gateway;
final class Router
{
    private string $directory;
    public function __construct(private array $config)
    {
        Protocol::reject(!Protocol::httpsUrl($config['public_url'] ?? null), 503, 'invalid_public_url');
        $this->directory = Protocol::directory($config['state_dir'] ?? '', $config['document_root'] ?? '');
        $seen = []; $keys = [];
        foreach ($config['clients'] ?? [] as $id => $c) {
            Protocol::reject(!Protocol::identifier($id) || !Protocol::accountSid($c['account_sid'] ?? null) || !is_string($c['auth_token'] ?? null) || $c['auth_token'] === '', 503, 'invalid_registry');
            Protocol::reject(empty($c['numbers']) || !isset($c['destinations'][$c['current_destination'] ?? '']), 503, 'invalid_registry');
            foreach ($c['numbers'] as $n) {
                Protocol::reject(!is_string($n) || !preg_match('/\A\+[1-9][0-9]{7,14}\z/', $n) || isset($seen[$n]), 503, 'ambiguous_number_ownership');
                $seen[$n] = $id;
            }
            foreach ($c['destinations'] as $version => $d) {
                Protocol::reject(!Protocol::identifier($version) || !Protocol::httpsUrl($d['url'] ?? null) || !is_string($d['secret'] ?? null) || strlen($d['secret']) < 32, 503, 'invalid_destination');
                $fingerprint = hash('sha256', $d['secret']);
                Protocol::reject(isset($keys[$fingerprint]), 503, 'handoff_keys_must_be_unique'); $keys[$fingerprint] = true;
            }
        }
    }
    /** Exactly one transport invocation per request; never retry call control. */
    public function handle(array $server, string $raw, callable $transport): array
    {
        $uri = (string) ($server['REQUEST_URI'] ?? ''); $path = parse_url($uri, PHP_URL_PATH);
        Protocol::reject($path !== parse_url($this->config['public_url'], PHP_URL_PATH), 400, 'wrong_gateway_path');
        $query = Protocol::form((string) (parse_url($uri, PHP_URL_QUERY) ?? ''));
        $client = $query['client'] ?? ''; $event = $query['event'] ?? '';
        Protocol::reject(!Protocol::identifier($client) || !isset($this->config['clients'][$client]), 404, 'unknown_client');
        Protocol::reject(!in_array($event, ['voice','whisper','status','recording'], true), 404, 'unknown_event');
        $method = $server['REQUEST_METHOD'] ?? '';
        Protocol::reject($method !== ($event === 'whisper' ? 'GET' : 'POST'), 405, 'wrong_method');
        Protocol::reject($method === 'POST' && trim(strtolower(explode(';', $server['CONTENT_TYPE'] ?? '')[0])) !== 'application/x-www-form-urlencoded', 415, 'unsupported_body');
        Protocol::reject($method === 'GET' && $raw !== '', 400, 'unexpected_get_body');
        $form = $method === 'POST' ? Protocol::form($raw) : []; $params = $method === 'POST' ? $form : $query;
        $c = $this->config['clients'][$client];
        $base = $this->config['public_url']; $external = substr($base, 0, strlen($base) - strlen((string) parse_url($base, PHP_URL_PATH))) . $uri;
        $expected = Protocol::signature($external, $form, $c['auth_token']);
        Protocol::reject(!hash_equals($expected, (string) ($server['HTTP_X_TWILIO_SIGNATURE'] ?? '')), 403, 'provider_signature_rejected');
        Protocol::reject(($params['AccountSid'] ?? '') !== $c['account_sid'], 403, 'provider_account_rejected');
        $call = $event === 'whisper' ? ($query['call'] ?? '') : ($params['CallSid'] ?? '');
        Protocol::reject(!Protocol::callSid($call) || (isset($query['call']) && $query['call'] !== $call), 403, 'call_identity_rejected');
        if ($event === 'whisper') {
            Protocol::reject(!Protocol::callSid($params['CallSid'] ?? null) || ($params['ParentCallSid'] ?? '') !== $call, 403, 'whisper_parent_rejected');
        }
        if ($event === 'voice') { Protocol::reject(!in_array($params['To'] ?? '', $c['numbers'], true), 403, 'number_ownership_rejected'); }
        $key = hash('sha256', $client . ':' . $call); $file = $this->directory . '/' . $key . '.json';
        $lock = @fopen($this->directory . '/' . $key . '.lock', 'c'); Protocol::reject($lock === false, 503, 'binding_lock_unavailable');
        chmod($this->directory . '/' . $key . '.lock', 0600); $end = microtime(true) + 2;
        try {
            while (!flock($lock, LOCK_EX | LOCK_NB)) { Protocol::reject(microtime(true) >= $end, 503, 'binding_lock_timeout'); usleep(10000); }
            $binding = is_file($file) ? json_decode(file_get_contents($file), true, 32, JSON_THROW_ON_ERROR) : null;
            if ($binding === null) {
                Protocol::reject($event !== 'voice', 503, 'binding_not_yet_available');
                $destination = $c['current_destination']; $d = $c['destinations'][$destination];
                $binding = ['client'=>$client,'call'=>$call,'account'=>$c['account_sid'],'number'=>$params['To'],'destination'=>$destination,
                    'destination_fingerprint'=>hash('sha256', json_encode($d, JSON_THROW_ON_ERROR)), 'binding'=>bin2hex(random_bytes(32)), 'voice_state'=>'dispatching'];
                // Commit selected destination BEFORE sending the first request.
                Protocol::write($file, $binding); $first = true;
            } else { $first = false; }
            Protocol::reject($binding['client'] !== $client || $binding['call'] !== $call || $binding['account'] !== $c['account_sid'], 403, 'binding_identity_rejected');
            $d = $c['destinations'][$binding['destination']] ?? null;
            Protocol::reject($d === null || !hash_equals($binding['destination_fingerprint'], hash('sha256', json_encode($d, JSON_THROW_ON_ERROR))), 503, 'pinned_destination_unavailable');
            if ($event === 'voice') {
                Protocol::reject($params['To'] !== $binding['number'], 403, 'binding_number_rejected');
                if (!$first) {
                    Protocol::reject($binding['voice_state'] !== 'complete', 503, 'voice_delivery_uncertain_no_automatic_retry');
                    return [200, $binding['voice_response']];
                }
            } else {
                // Provider-configured parent status callbacks may omit a URL token;
                // generated callback URLs and all whispers carry the pinned token.
                Protocol::reject(isset($query['binding']) && !hash_equals($binding['binding'], $query['binding']), 403, 'binding_token_rejected');
                Protocol::reject($event === 'whisper' && !isset($query['binding']), 403, 'binding_token_required');
            }
            $envelope = ['v'=>1,'client'=>$client,'destination'=>$binding['destination'],'event'=>$event,'call_sid'=>$call,
                'account_sid'=>$binding['account'],'number'=>$binding['number'],'gateway_base'=>$base,'binding'=>$binding['binding'],
                'method'=>$method,'query'=>$query,'form'=>$form,'timestamp'=>time(),'nonce'=>bin2hex(random_bytes(32))];
            $body = json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            try { [$status, $response] = $transport($d['url'], $body, hash_hmac('sha256', $body, $d['secret'])); }
            catch (\Throwable $e) { throw new Rejection(503, 'destination_transport_failed_no_retry'); }
            Protocol::reject($status < 200 || $status >= 300 || !is_string($response) || strlen($response) > Protocol::LIMIT
                || preg_match('~\A\s*(?:<\?xml[^>]*\?>\s*)?<Response(?:\s[^>]*)?(?:/>|>.*</Response>)\s*\z~s', $response) !== 1, 503, 'destination_not_successful');
            if ($event === 'voice') { $binding['voice_state'] = 'complete'; $binding['voice_response'] = $response; Protocol::write($file, $binding); }
            return [200, $response];
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
    /** HTTPS only, bounded response/time, no redirect or implicit application retry. */
    public static function transport(string $url, string $body, string $signature): array
    {
        Protocol::reject(!function_exists('curl_init'), 503, 'curl_required');
        $response = ''; $h = curl_init($url);
        curl_setopt_array($h, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_TIMEOUT=>8,CURLOPT_CONNECTTIMEOUT=>4,
            CURLOPT_USERAGENT=>'SiteEngine-CB-Gateway/0.1.0',
            CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-SECB-Handoff: '.$signature],
            CURLOPT_WRITEFUNCTION=>static function ($handle, string $part) use (&$response): int {
                if (strlen($response) + strlen($part) > Protocol::LIMIT) { return 0; }
                $response .= $part; return strlen($part);
            }]);
        try {
            $ok = curl_exec($h); $status = (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE);
            Protocol::reject($ok === false, 503, 'destination_transport_failed');
            return [$status, $response];
        } finally { curl_close($h); }
    }
}
