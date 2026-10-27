<?php
defined('ABSPATH') || exit;
if(!current_user_can(\SiteEngine\CallBridge\Admin\Menu::CAP))return;
?>
<div class="wrap">
<h1>Call Bridge — Setup &amp; Status</h1>
<p><strong><?php echo esc_html($readiness['label']); ?></strong></p>
<p><?php echo esc_html($readiness['acceptance']); ?></p>
<p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=secb-numbers')); ?>">Tracking Numbers</a>
<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=secb-calls')); ?>">Call Log</a>
<a class="button" href="<?php echo esc_url($helpUrl); ?>">Help &amp; Reference</a></p>
<p>This page is read-only. It does not save settings, provision numbers, change webhooks or place calls.</p>
<h2>Configuration checklist</h2>
<table class="widefat striped"><thead><tr><th scope="col">Check</th><th scope="col">State</th><th scope="col">Meaning / next step</th></tr></thead><tbody>
<?php foreach($readiness['rows'] as $row): ?>
<tr><th scope="row"><?php echo esc_html($row['name']); ?></th><td><?php echo esc_html($row['state']); ?></td><td><?php echo esc_html($row['detail']); ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p>Lease: <?php echo (int)$readiness['facts']['lease_minutes']; ?> minutes. Attribution-session retention: <?php echo (int)$readiness['facts']['session_days']; ?> days. Neither is a complete call-data retention policy.</p>
<h2>Business facts and three different phone roles</h2>
<p>Shared facts are reference information. They are never copied into call routing automatically.</p>
<dl>
<dt><strong>Business name</strong></dt><dd><?php echo esc_html($business['business_name']['value'] ?: 'Not available from Base'); ?></dd>
<dt><strong>Public business phone</strong></dt><dd><?php echo esc_html($business['phone']['value'] ?: 'Not available from Base'); ?> — the ordinary advertised contact, not an automatically chosen recipient.</dd>
<dt><strong>Shared forwarding / tracking field</strong></dt><dd><?php echo esc_html($business['forwarding_phone']['value'] ?: 'Not available from Base'); ?> — unconfirmed reference only; its legacy label does not establish the intended recipient.</dd>
<dt><strong>Per-number forwarding destination</strong></dt><dd>Each Tracking Numbers row's existing <code>forward_to</code> is authoritative. Choose an approved recipient explicitly.</dd>
<dt><strong>Tracking numbers</strong></dt><dd>Owned Twilio numbers in CB's pools; different from the public phone and recipient destination.</dd>
</dl>
<?php if($business['privacy_policy_url']['value']!==''): ?>
<p><a href="<?php echo esc_url($business['privacy_policy_url']['value']); ?>">Shared privacy policy reference</a> — this does not establish call or advertising consent.</p>
<?php endif; ?>
<?php if(function_exists('siteengine_get')): ?>
<p><a href="<?php echo esc_url(admin_url('admin.php?page=business-data')); ?>">Review Business Data</a> for missing shared facts. Existing number settings remain unchanged.</p>
<?php endif; ?>
<h2>Set up Twilio</h2>
<ol>
<li><strong>Establish ownership.</strong> Use an approved account/subaccount and dedicated Voice-capable number. Confirm recipient, budget and test window. Obtain/purchase numbers in Twilio only under separate authorization; CB does not provision them.</li>
<li><strong>Provision securely.</strong> Ask the administrator to preserve the existing <code>SECB_CONFIG_FILE</code> outside the web root and plugin folder, readable by the actual PHP owner. Define its reference before plugin loading. Do not paste secrets into this page, Git, screenshots or support messages.</li>
<li><strong>Use the right credentials.</strong> <code>SECB_TWILIO_ACCOUNT_SID</code> identifies the account; <code>SECB_TWILIO_AUTH_TOKEN</code> validates incoming signatures. Optional <code>SECB_TWILIO_API_KEY_SID</code>/<code>SECB_TWILIO_API_KEY_SECRET</code> are preferred for later media playback; API keys do not replace the signature Auth Token. Nonempty example placeholders are not valid credentials. Presence checks do not contact Twilio.</li>
<li><strong>Register numbers locally.</strong> In Tracking Numbers, enter the owned number, approved forwarding destination, source/pool, active flag and duration threshold. Preserve existing notices/whispers/settings. Keep recording off unless separately approved. A CB row does not configure Twilio.</li>
<li><strong>Set the inbound webhook deliberately.</strong> After provider-change approval, use the exact Voice URL below with HTTP POST. CB generates status, optional recording and signed whisper callbacks in its TwiML. Match the actual public HTTPS URL/proxy; never bypass signatures to repair a mismatch.</li>
<li><strong>Verify in stages.</strong> Configuration checks come first. Separately authorize SQL lease concurrency, browser DNI and signed callbacks, then live forwarding and secured playback. A credential-presence check or health 200 proves none of these.</li>
</ol>
<h3>URLs generated from this site's webhook base</h3>
<?php if(!$webhooks): ?>
<p>The base is not a usable public HTTPS URL. Ask the administrator to review it privately; an unsafe value is not displayed.</p>
<?php else: ?>
<table class="widefat striped"><thead><tr><th scope="col">Purpose</th><th scope="col">Method</th><th scope="col">URL</th><th scope="col">Use</th></tr></thead><tbody>
<?php foreach($webhooks as $hook): ?>
<tr><th scope="row"><?php echo esc_html($hook['label']); ?></th><td><?php echo esc_html($hook['method']); ?></td><td><code><?php echo esc_html($hook['url']); ?></code></td><td><?php echo esc_html($hook['use']); ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<p>For pools, safe exhaustion/restore, diagnostic permissions and troubleshooting, read <a href="<?php echo esc_url($helpUrl); ?>">Call Bridge Help &amp; Reference</a>. Twilio is the only supported provider.</p>
</div>
