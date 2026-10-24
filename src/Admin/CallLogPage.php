<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Admin;
use SiteEngine\CallBridge\DB\CallLogRepository;
defined('ABSPATH') || exit;
final class CallLogPage
{
    public static function render(): void
    {
        if(!current_user_can(Menu::CAP))wp_die('Insufficient permissions.');$repo=new CallLogRepository();$rows=$repo->listRecent(50,0);$total=$repo->countAll();
        ?>
        <div class="wrap"><h1>SiteEngine Call Bridge — Call Log</h1><p>Showing most recent 50 of <strong><?php echo (int)$total; ?></strong> calls.</p>
        <table class="wp-list-table widefat fixed striped"><thead><tr><th>Time (UTC)</th><th>Caller</th><th>Tracking #</th><th>Status</th><th>Duration</th><th>Quality</th><th>Repeat</th><th>Click ID</th><th>Source</th><th>Campaign</th><th>Recording</th></tr></thead><tbody>
        <?php if(!$rows): ?><tr><td colspan="11"><em>No calls logged yet.</em></td></tr><?php else: foreach($rows as $r):
            $click='—'; if(!empty($r['gclid']))$click='gclid'; elseif(!empty($r['gbraid']))$click='gbraid'; elseif(!empty($r['wbraid']))$click='wbraid'; elseif(!empty($r['msclkid']))$click='msclkid'; elseif(!empty($r['fbclid']))$click='fbclid';
            $play=!empty($r['recording_url'])?rest_url(SECB_REST_NAMESPACE.'/recording/'.(int)$r['id']):null; ?>
            <tr><td><?php echo esc_html($r['created_at']); ?></td><td><code><?php echo esc_html($r['caller_number']); ?></code><br><small><?php echo esc_html(trim(($r['caller_city']??'').', '.($r['caller_state']??''),', ')); ?></small></td><td><code><?php echo esc_html($r['tracking_number']); ?></code></td><td><?php echo esc_html($r['call_status']??'—'); ?></td><td><?php echo $r['call_duration_sec']!==null?(int)$r['call_duration_sec'].'s':'—'; ?></td><td><?php echo esc_html($r['qualification_status']); ?></td><td><?php echo !empty($r['is_repeat_caller'])?'YES ('.(int)$r['prior_qualifying_calls'].')':'—'; ?></td><td><?php echo esc_html($click); ?></td><td><?php echo esc_html($r['utm_source']?:($r['source']??'—')); ?></td><td><?php echo esc_html($r['utm_campaign']??'—'); ?></td><td><?php echo $play?'<a href="'.esc_url($play).'" target="_blank" rel="noopener">▶ Play</a>':'—'; ?></td></tr>
        <?php endforeach; endif; ?></tbody></table></div>
        <?php
    }
}
