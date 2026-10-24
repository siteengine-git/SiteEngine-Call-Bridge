<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Support;
defined('ABSPATH') || exit;
final class TrackingNumberResolver
{
    public static function formatForDisplay(string $e164): string
    {
        if (preg_match('/^\+1(\d{3})(\d{3})(\d{4})$/', $e164, $m) === 1) {
            return "({$m[1]}) {$m[2]}-{$m[3]}";
        }
        return $e164;
    }
}
