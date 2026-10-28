<?php
/**
 * Plugin Name:       SiteEngine Call Bridge
 * Plugin URI:        https://siteengine.io
 * Description:       SiteEngine call tracking companion plugin: DNI, Twilio routing, call recording, attribution, and call diagnostics.
 * Version:           0.1.2
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            SiteEngine
 * Author URI:        https://siteengine.io
 * License:           GPL-2.0-or-later
 * Text Domain:       siteengine-call-bridge
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('SECB_VERSION', '0.1.2');
define('SECB_FILE', __FILE__);
define('SECB_PATH', plugin_dir_path(__FILE__));
define('SECB_URL', plugin_dir_url(__FILE__));
define('SECB_REST_NAMESPACE', 'siteengine-call/v1');
define('SECB_DB_VERSION', '1.0.0');

// Optional client config outside the replaceable plugin directory. The file
// may define SECB_* constants; no credentials are packaged with the plugin.
if (defined('SECB_CONFIG_FILE') && is_string(SECB_CONFIG_FILE) && SECB_CONFIG_FILE !== '') {
    if (is_readable(SECB_CONFIG_FILE)) {
        require_once SECB_CONFIG_FILE;
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'SiteEngine\\CallBridge\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = SECB_PATH . 'src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($path)) {
        require_once $path;
    }
});

register_activation_hook(__FILE__, [\SiteEngine\CallBridge\Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [\SiteEngine\CallBridge\Installer::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    \SiteEngine\CallBridge\Plugin::instance()->boot();
});
