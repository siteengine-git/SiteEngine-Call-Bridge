<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Admin;
use SiteEngine\CallBridge\Support\Config;
defined('ABSPATH') || exit;
final class SettingsPage
{
    public static function render(): void
    {
        if(!current_user_can(Menu::CAP))wp_die('Insufficient permissions.');$base=Config::webhookBaseUrl().'/wp-json/'.SECB_REST_NAMESPACE;
        $items=['SECB_TWILIO_ACCOUNT_SID'=>Config::twilioAccountSid()!==null,'SECB_TWILIO_AUTH_TOKEN'=>Config::twilioAuthToken()!==null,'Recording credentials'=>Config::recordingCredentials()!==null,'SECB_WEBHOOK_BASE_URL'=>defined('SECB_WEBHOOK_BASE_URL'),'SECB_DEBUG_LOG'=>Config::debugLog(),'SECB_PASSTHROUGH_MODE'=>Config::passthroughMode()];
        ?>
        <div class="wrap"><h1>SiteEngine Call Bridge — Settings</h1>
        <h2>Twilio Webhook URLs</h2><table class="form-table"><tr><th>Voice</th><td><code><?php echo esc_html($base.'/twilio/voice'); ?></code> — POST</td></tr><tr><th>Status</th><td><code><?php echo esc_html($base.'/twilio/status'); ?></code> — set automatically by &lt;Dial&gt;</td></tr><tr><th>Recording</th><td><code><?php echo esc_html($base.'/twilio/recording'); ?></code></td></tr><tr><th>Health</th><td><code><?php echo esc_html($base.'/health'); ?></code></td></tr></table>
        <h2>Configuration</h2><table class="form-table"><?php foreach($items as $name=>$ok): ?><tr><th><code><?php echo esc_html($name); ?></code></th><td><?php echo $ok?'<span style="color:#3a3">● Ready</span>':'<span style="color:#a33">○ Not configured</span>'; ?></td></tr><?php endforeach; ?><tr><th>Number lease</th><td><?php echo (int)Config::leaseMinutes(); ?> minutes</td></tr><tr><th>Attribution retention</th><td><?php echo (int)Config::sessionTtlDays(); ?> days</td></tr></table>
        <p><strong>Call Bridge 0.1.0 intentionally contains no Fluent Forms, FluentCRM lifecycle, Won/Lost, Google Sheets, or Google Ads export code.</strong></p></div>
        <?php
    }
}
