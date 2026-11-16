<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Gateway;
/** Request-local, established only after authenticated receiver validation. */
final class Context
{
    private static ?array $value = null;
    public static function active(): bool { return self::$value !== null; }
    public static function enter(array $verified): void
    {
        if (self::active()) { throw new Rejection(409, 'nested_handoff'); }
        self::$value = $verified;
    }
    public static function clear(): void { self::$value = null; }
    public static function callbackUrl(string $event, array $extra): string
    {
        $v = self::$value;
        if ($v === null) { throw new Rejection(503, 'missing_gateway_context'); }
        return Protocol::callbackUrl($v['gateway_base'], $v['client'], $event, $v['call_sid'], $v['binding'], $extra);
    }
}
