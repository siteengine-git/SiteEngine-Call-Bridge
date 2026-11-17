<?php
defined('ABSPATH') || exit;
if(!current_user_can(\SiteEngine\CallBridge\Admin\Menu::CAP))return;
$embedded = $embedded ?? false;
?>
<?php if(!$embedded): ?><div class="wrap"><h1>Call Bridge — Setup &amp; Status</h1><?php else: ?><h2>Setup &amp; Status</h2><?php endif; ?>
<p><strong><?php echo esc_html($readiness['label']); ?></strong></p>
<p><?php echo esc_html($readiness['acceptance']); ?></p>
<p><a class="button" href="<?php echo esc_url(\SiteEngine\CallBridge\Admin\Menu::url('numbers')); ?>">Tracking Numbers</a>
<a class="button" href="<?php echo esc_url(\SiteEngine\CallBridge\Admin\Menu::url('calls')); ?>">Call Log</a>
<a class="button" href="<?php echo esc_url($helpUrl); ?>">Help &amp; Reference</a></p>
<p>Credential entry uses Base’s protected WP forms. This page can select onboarding guidance; it never purchases numbers, changes provider webhooks or places calls.</p>
<h2>1. Required credentials and authoritative source</h2>
<p><strong><?php echo esc_html($onboarding['credentials']['source']); ?></strong> — <?php echo esc_html($onboarding['credentials']['local']); ?>.</p>
<p><?php echo esc_html($onboarding['credentials']['action']); ?></p>
<?php if($onboarding['base_available']): ?>
<?php $credentialUrl = \SiteEngine\CallBridge\Integration\BaseCredentials::adminUrl(); if ($credentialUrl !== null): ?>
<p><a class="button button-primary" href="<?php echo esc_url($credentialUrl); ?>">Open protected WP credential entry</a></p>
<?php else: ?><p>Protected credential entry is unavailable for this selection or administrator. Resolve Base availability and permissions first.</p><?php endif; ?>
<ol><li>Find <strong>Call Bridge — required Twilio Account SID and Webhook Auth Token</strong>, resource <code>default</code>.</li>
<li>Use the Account SID and Auth Token from the same account/subaccount that owns the dedicated number. Account SID is <code>AC</code> plus 32 hexadecimal characters; Auth Token is 32 hexadecimal characters. Both fields stay masked and are never prefilled.</li>
<li>Stage the <strong>complete pair</strong>, then activate the exact staged version. Staging alone does not replace active credentials. Blank retains material; partial or masked placeholders are rejected. Local validation does not authenticate with Twilio.</li>
<li>If a save fails, recheck the complete pair, resource <code>default</code> and this page’s authority/error. Reload after a revision conflict; do not repeatedly resubmit uncertain operations. Host-key/storage failures need Base’s supported provisioning/initialization, not new CB storage.</li></ol>
<?php endif; ?>
<p><strong>Optional playback API credentials:</strong> <?php echo esc_html($onboarding['playback']['source'].' — '.$onboarding['playback']['local']); ?>.
They are a separate protected <strong>Call Bridge — optional Twilio playback API key</strong> set. They must belong to the owning account; API keys cannot validate webhook signatures. When absent, playback uses the required Account SID/Auth Token. They are not required while recording is disabled.</p>
<p>External nonempty fields take precedence as one complete pair. A partial external pair blocks Base fallback. CB neither imports nor overwrites that file. Explicit migration requires a backed-up maintenance change removing both external fields before staging/activating Base material; do not change sources during active calls.</p>
<h2>2. Routing mode and exact addresses</h2>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
<input type="hidden" name="action" value="secb_onboarding_mode">
<?php wp_nonce_field('secb_onboarding_mode'); ?>
<label for="secb-routing-mode">Onboarding mode</label>
<select id="secb-routing-mode" name="routing_mode"><option value="direct"<?php echo $onboarding['mode']==='direct'?' selected':''; ?>>Direct to this site</option><option value="central"<?php echo $onboarding['mode']==='central'?' selected':''; ?>>Optional centralized gateway</option></select>
<button class="button" type="submit">Save onboarding mode</button>
</form>
<p>This selects guidance only. Direct webhooks remain supported. It does not activate a gateway, edit secrets, change number settings or update Twilio.</p>
<?php if($onboarding['mode']==='central'): ?>
<?php if(!$onboarding['routes']): ?><p><strong>Central routing is not provisioned or its local configuration is incomplete.</strong> The gateway owner must check the approved client ID, HTTPS base, matching owning account, number list, immutable destination, handoff key and private nonce directory. Gateway registry and receiver must agree. Direct setup is usable independently; select Direct to proceed without central activation.</p><?php endif; ?>
<?php foreach($onboarding['routes'] as $route): ?>
<p>Client <?php echo esc_html($route['client']); ?> — inbound Voice: <strong>POST</strong></p>
<div class="secb-onboarding-url" tabindex="0" role="region" aria-label="Central inbound Voice URL"><code><?php echo esc_html($route['voice']); ?></code></div>
<p>Optional parent status: <strong>POST</strong></p><div class="secb-onboarding-url" tabindex="0" role="region" aria-label="Central parent status URL"><code><?php echo esc_html($route['status']); ?></code></div>
<p>CB generates Dial/status and recording callbacks as POST and recipient whispers as GET through this gateway, with call-specific binding parameters. Never manually substitute a fixed recording/whisper URL. Existing calls remain pinned to their original installation during route switches.</p>
<?php endforeach; ?>
<?php endif; ?>
<style>.secb-onboarding-url {max-width:100%;overflow-x:auto;border:1px solid #c3c4c7;padding:.75em}.secb-onboarding-url code {display:block;width:max-content;white-space:pre;word-break:normal;user-select:text}.secb-onboarding-url:focus {outline:2px solid #2271b1}</style>
<h2>3. Numbers, DNI and recording</h2>
<p>Use <strong>Tracking Numbers</strong> to add the owned tracking number, explicit forwarding destination, source/pool, active flag and duration threshold. Keep the public business phone unchanged. Ordinary telephone links swap only when they match the forwarding destination; otherwise use explicit CB opt-ins. Select the intended source, such as <code>google_ads</code>, and one adequately sized pool. A single development number does not prove client capacity.</p>
<p>For recording, explicitly approve participants and notice, then enable the per-number flag and notice in Tracking Numbers. The notice precedes recording from answer. Protected Call Log playback requires an administrator, fresh REST nonce and usable media credentials. Recording defaults off; leave it off until separately authorized. Files must be dual-channel WAV within the proxy’s 20 MiB limit.</p>
<h2>4. What is checked versus verified</h2>
<p><strong>Configured:</strong> required material and number settings exist. <strong>Automatically checked here:</strong> local credential shape/source, readable configuration, PHP extensions, table/version presence and GC registration. <strong>Live verified:</strong> requires separate provider and operator evidence; no badge on this page establishes it. Missing required configuration always prevents configuration-complete status.</p>
<p>CRM call delivery, authoritative call-bound Consent and Google call exports are separate and unavailable as basic setup features. They are not prerequisites for forwarding, DNI or recording. Do not infer advertising eligibility from source labels or duration qualification.</p>
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
<li><strong>Provision securely.</strong> Use Base’s protected WP credential entry for a new installation, after supported host-key/table initialization. Existing <code>SECB_CONFIG_FILE</code> remains authoritative where populated. Never paste secrets into Git, screenshots or support messages.</li>
<li><strong>Use the right credentials.</strong> <code>SECB_TWILIO_ACCOUNT_SID</code> identifies the account; <code>SECB_TWILIO_AUTH_TOKEN</code> validates incoming signatures. Optional <code>SECB_TWILIO_API_KEY_SID</code>/<code>SECB_TWILIO_API_KEY_SECRET</code> are preferred for later media playback; API keys do not replace the signature Auth Token. Nonempty example placeholders are not valid credentials. Presence checks do not contact Twilio.</li>
<li><strong>Register numbers locally.</strong> In Tracking Numbers, enter the owned number, approved forwarding destination, source/pool, active flag and duration threshold. Preserve existing notices/whispers/settings. Keep recording off unless separately approved. A CB row does not configure Twilio.</li>
<li><strong>Set the inbound webhook deliberately.</strong> After provider-change approval, use the exact Voice URL below with HTTP POST. CB generates status, optional recording and signed whisper callbacks in its TwiML. Match the actual public HTTPS URL/proxy; never bypass signatures to repair a mismatch.</li>
<li><strong>Verify in stages.</strong> Configuration checks come first. Separately authorize SQL lease concurrency, browser DNI and signed callbacks, then live forwarding and secured playback. A credential-presence check or health 200 proves none of these.</li>
</ol>
<h3>Direct-to-site URLs<?php echo $onboarding['mode']==='central'?' — alternate direct path, not the selected gateway addresses':''; ?></h3>
<?php if(!$webhooks): ?>
<p>The base is not a usable public HTTPS URL. Ask the administrator to review it privately; an unsafe value is not displayed.</p>
<?php else: ?>
<style>
.secb-webhooks .secb-webhook { box-sizing: border-box; min-width: 0; margin: 0 0 1em; padding: 1em; border: 1px solid #c3c4c7; background: #fff; }
.secb-webhooks .secb-webhook h4 { margin: 0 0 .75em; }
.secb-webhooks .secb-webhook-method { display: inline-block; margin-left: .5em; }
.secb-webhooks .secb-webhook-url { box-sizing: border-box; width: 100%; max-width: 100%; min-width: 0; overflow-x: auto; border: 1px solid #c3c4c7; }
.secb-webhooks .secb-webhook-url:focus { outline: 2px solid #2271b1; outline-offset: 2px; }
.secb-webhooks .secb-webhook-url code { display: block; box-sizing: border-box; width: max-content; min-width: 100%; margin: 0; padding: .75em; white-space: pre; overflow-wrap: normal; word-break: normal; -webkit-user-select: text; user-select: text; }
.secb-webhooks .secb-webhook p { margin: .75em 0 0; }
</style>
<div class="secb-webhooks">
<?php foreach($webhooks as $hook): ?>
<section class="secb-webhook">
<h4><?php echo esc_html($hook['label']); ?><code class="secb-webhook-method"><?php echo esc_html($hook['method']); ?></code></h4>
<div class="secb-webhook-url" role="region" tabindex="0" aria-label="<?php echo esc_attr($hook['label'].' URL'); ?>"><code><?php echo esc_html($hook['url']); ?></code></div>
<p><?php echo esc_html($hook['use']); ?></p>
</section>
<?php endforeach; ?>
</div>
<?php endif; ?>
<p>For pools, safe exhaustion/restore, diagnostic permissions and troubleshooting, read <a href="<?php echo esc_url($helpUrl); ?>">Call Bridge Help &amp; Reference</a>. Twilio is the only supported provider.</p>
<?php if(!$embedded): ?></div><?php endif; ?>
