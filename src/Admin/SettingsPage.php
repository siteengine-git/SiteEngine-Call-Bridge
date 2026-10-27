<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Admin;
use SiteEngine\CallBridge\Integration\BaseIntegration;
use SiteEngine\CallBridge\Support\Readiness;
use SiteEngine\CallBridge\Support\SharedBusinessContext;
defined('ABSPATH') || exit;
final class SettingsPage
{
    public static function render(): void
    {
        if(!current_user_can(Menu::CAP)){wp_die('Insufficient permissions.');return;}
        $helpUrl=BaseIntegration::helpUrl();
        if(isset($_GET['view'])&&sanitize_key((string)wp_unslash($_GET['view']))==='help'){
            echo '<div class="wrap"><h1>Call Bridge Help &amp; Reference</h1>';
            include SECB_PATH.'readme-tabs/call-bridge.php';
            echo '</div>';return;
        }
        $readiness=Readiness::current();$business=SharedBusinessContext::read();
        $webhooks=Readiness::webhookUrls();
        include SECB_PATH.'templates/admin/setup-status.php';
    }
}
