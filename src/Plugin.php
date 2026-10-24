<?php
/**
 * Runtime wiring for SiteEngine Call Bridge.
 */

declare(strict_types=1);

namespace SiteEngine\CallBridge;

use SiteEngine\CallBridge\Admin\Menu;
use SiteEngine\CallBridge\Frontend\DniSnippet;
use SiteEngine\CallBridge\Webhook\RestRoutes;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Server;

defined('ABSPATH') || exit;

final class Plugin
{
    private static ?self $instance = null;
    private bool $booted = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct() {}

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        Installer::maybeUpgrade();
        add_action('rest_api_init', [new RestRoutes(), 'register']);

        if (is_admin()) {
            (new Menu())->register();
        } else {
            (new DniSnippet())->register();
        }

        add_filter('rest_post_dispatch', [$this, 'applyNoCacheHeaders'], 10, 3);
        add_action(Installer::CRON_GC, [Installer::class, 'runGc']);

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('se-call', \SiteEngine\CallBridge\CLI\Commands::class);
        }
    }

    public function applyNoCacheHeaders(
        WP_HTTP_Response $response,
        WP_REST_Server $server,
        WP_REST_Request $request
    ): WP_HTTP_Response {
        if (str_starts_with($request->get_route(), '/' . SECB_REST_NAMESPACE)) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
            $response->header('Pragma', 'no-cache', true);
            $response->header('X-LiteSpeed-Cache-Control', 'no-cache', true);
        }
        return $response;
    }
}
