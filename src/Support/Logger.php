<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Support;
defined('ABSPATH') || exit;
final class Logger
{
    private const MAX_BYTES = 5_242_880;
    public static function info(string $message,array $context=[]): void { if(Config::debugLog()) self::write('INFO',$message,$context); }
    public static function warn(string $message,array $context=[]): void { if(Config::debugLog()) self::write('WARN',$message,$context); }
    public static function error(string $message,array $context=[]): void
    {
        $line=self::format('ERROR',$message,$context);
        error_log('[siteengine-call-bridge] '.$line);
        if(Config::debugLog()) self::writeRaw($line);
    }
    private static function write(string $level,string $message,array $context): void { self::writeRaw(self::format($level,$message,$context)); }
    private static function format(string $level,string $message,array $context): string
    { $ctx=$context===[]?'':' '.wp_json_encode($context,JSON_UNESCAPED_SLASHES); return gmdate('Y-m-d\TH:i:s\Z')." {$level} {$message}{$ctx}"; }
    private static function writeRaw(string $line): void
    {
        $dir=self::ensureLogDir(); if($dir===null) return; $file=$dir.'/plugin.log';
        if(is_file($file) && filesize($file)>self::MAX_BYTES) @rename($file,$file.'.1');
        @file_put_contents($file,$line.PHP_EOL,FILE_APPEND|LOCK_EX);
    }
    private static function ensureLogDir(): ?string
    {
        $uploads=wp_upload_dir(null,false); if(!empty($uploads['error'])) return null;
        $dir=$uploads['basedir'].'/siteengine-call-bridge';
        if(!is_dir($dir) && !wp_mkdir_p($dir)) return null;
        if(!file_exists($dir.'/.htaccess')) @file_put_contents($dir.'/.htaccess',"Require all denied\n");
        if(!file_exists($dir.'/index.php')) @file_put_contents($dir.'/index.php',"<?php // silence is golden\n");
        return $dir;
    }
}
