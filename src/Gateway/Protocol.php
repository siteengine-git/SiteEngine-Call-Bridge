<?php
/** Shared wire primitives only; no WordPress bootstrap, hooks or call ledger. */
declare(strict_types=1);
namespace SiteEngine\CallBridge\Gateway;
final class Rejection extends \RuntimeException
{
    public function __construct(public readonly int $status, string $reason) { parent::__construct($reason); }
}
final class Protocol
{
    public const LIMIT = 65536;
    public static function reject(bool $condition, int $status, string $reason): void
    { if ($condition) { throw new Rejection($status, $reason); } }
    public static function identifier(mixed $v): bool
    { return is_string($v) && preg_match('/\A[a-z0-9][a-z0-9-]{0,39}\z/', $v) === 1; }
    public static function callSid(mixed $v): bool
    { return is_string($v) && preg_match('/\ACA[a-fA-F0-9]{32}\z/', $v) === 1; }
    public static function accountSid(mixed $v): bool
    { return is_string($v) && preg_match('/\AAC[a-fA-F0-9]{32}\z/', $v) === 1; }
    public static function flat(mixed $v): bool
    {
        if (!is_array($v)) { return false; }
        foreach ($v as $k => $value) { if (!is_string($k) || !is_string($value)) { return false; } }
        return true;
    }
    /** Flat form grammar: preserve values; reject ambiguous duplicate/nested keys. */
    public static function form(string $raw): array
    {
        self::reject(strlen($raw) > self::LIMIT, 413, 'request_too_large');
        $out = [];
        if ($raw === '') { return $out; }
        foreach (explode('&', $raw) as $pair) {
            $kv = explode('=', $pair, 2); $key = urldecode($kv[0]); $value = urldecode($kv[1] ?? '');
            self::reject($key === '' || preg_match('/[\[\]\x00]/', $key) || array_key_exists($key, $out), 400, 'ambiguous_parameters');
            $out[$key] = $value;
        }
        return $out;
    }
    public static function httpsUrl(mixed $url): bool
    {
        if (!is_string($url) || preg_match('/[\x00-\x20\x7f]/', $url)) { return false; }
        $p = parse_url($url);
        return is_array($p) && ($p['scheme'] ?? '') === 'https' && !empty($p['host'])
            && !isset($p['user'], $p['pass']) && !isset($p['user']) && !isset($p['fragment'])
            && !isset($p['query']) && (!isset($p['port']) || $p['port'] === 443);
    }
    public static function signature(string $url, array $form, string $secret): string
    {
        ksort($form, SORT_STRING);
        foreach ($form as $k => $v) { $url .= $k . $v; }
        return base64_encode(hash_hmac('sha1', $url, $secret, true));
    }
    public static function callbackUrl(string $base, string $client, string $event, string $call, string $binding, array $extra = []): string
    {
        return $base . '?' . http_build_query(['client'=>$client,'event'=>$event,'call'=>$call,'binding'=>$binding] + $extra, '', '&', PHP_QUERY_RFC3986);
    }
    public static function directory(string $path, string $webroot): string
    {
        $real = realpath($path); $web = realpath($webroot);
        self::reject($real === false || $web === false || is_link($path) || !is_dir($real) || !is_writable($real), 503, 'private_storage_unavailable');
        $p = str_replace('\\', '/', $real) . '/'; $w = str_replace('\\', '/', $web) . '/';
        self::reject(str_starts_with(strtolower($p), strtolower($w)), 503, 'storage_inside_webroot');
        return $real;
    }
    public static function write(string $file, array $value): void
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $tmp = $file . '.' . bin2hex(random_bytes(8)) . '.tmp'; $h = @fopen($tmp, 'xb');
        self::reject($h === false, 503, 'storage_write_failed');
        try {
            chmod($tmp, 0600);
            self::reject(fwrite($h, $json) !== strlen($json) || !fflush($h), 503, 'storage_write_failed');
            if (function_exists('fsync')) { self::reject(!fsync($h), 503, 'storage_sync_failed'); }
        } finally { fclose($h); }
        if (!rename($tmp, $file)) { @unlink($tmp); throw new Rejection(503, 'storage_replace_failed'); }
    }
    public static function nonce(string $dir, string $identity): void
    {
        $path = $dir . '/' . hash('sha256', $identity) . '.nonce';
        $h = @fopen($path, 'xb');
        if ($h === false) { throw new Rejection(is_file($path) ? 409 : 503, 'handoff_replayed_or_storage_unavailable'); }
        try { chmod($path, 0600); self::reject(fwrite($h, (string) (time() + 120)) === false, 503, 'nonce_write_failed'); }
        finally { fclose($h); }
    }
}
