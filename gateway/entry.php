<?php
/** Thin public shim defines SECB_ROUTER_CONFIG pointing outside its document root. */
declare(strict_types=1);
use SiteEngine\CallBridge\Gateway\Protocol;
use SiteEngine\CallBridge\Gateway\Router;
use SiteEngine\CallBridge\Gateway\Rejection;
require_once dirname(__DIR__) . '/src/Gateway/Protocol.php';
require_once __DIR__ . '/Router.php';
umask(0077);
header('Cache-Control: no-store, no-cache, max-age=0');
try {
    Protocol::reject(!defined('SECB_ROUTER_CONFIG') || !is_readable(SECB_ROUTER_CONFIG), 503, 'gateway_not_configured');
    $config = require SECB_ROUTER_CONFIG;
    $raw = file_get_contents('php://input', false, null, 0, Protocol::LIMIT + 1);
    [$status, $body] = (new Router($config))->handle($_SERVER, $raw, [Router::class, 'transport']);
    http_response_code($status); header('Content-Type: text/xml; charset=utf-8'); echo $body;
} catch (\Throwable $e) {
    http_response_code($e instanceof Rejection ? $e->status : 503);
    header('Content-Type: text/plain; charset=utf-8'); echo 'Callback routing unavailable';
}
