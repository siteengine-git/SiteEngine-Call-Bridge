# SiteEngine Call Bridge 0.1.1 — unaccepted candidate

Webhook layout review revision: not deployed or accepted. Database version stays 1.0.0;
release metadata remains 0.1.1. The previous candidate ZIP is preserved separately.

Endpoint sections keep URLs selectable on one line, with horizontal scrolling when
needed. Styling is scoped to CB. URL generation, methods and escaping are unchanged.
The earlier installed candidate passed administrator card/Open, status-label,
Numbers/Call Log, Help tab/direct-link and duplicate-menu checks. This revision still
needs installed layout verification after a separately authorized deployment.
Authenticated non-administrator denial and all telephony acceptance remain pending.

## Base integration and read-only setup

The original Base 2.2.2 module/Help APIs are supported without its prepared routing patch.
SiteEngine → Call Bridge opens Setup & Status. Tracking Numbers and Call Log retain
secb-numbers and secb-calls; Setup retains secb-settings. Without Base, standalone
navigation and the full Help view remain available. Every page requires manage_options.
Existing save/delete actions and per-number settings are unchanged.

Setup separates required omissions, optional features switched off, local configuration
completeness and live acceptance still pending. Base uses warning/Open so a CB-specific
issue does not send the operator to Business Data. Dashboard/page inspection reads only
booleans and aggregate counts: no credential values, call rows, recording URLs, provider
requests, schema changes, schedules or settings writes. Normal WordPress bootstrap
retains its existing installer/GC side effects; this is not a global no-write guarantee.

Shared facts use the Base facade, with safe absence handling. Public phone, shared
forwarding reference, per-number forward_to and Twilio tracking number roles remain
separate; nothing synchronizes or overwrites them. External credentials stay outside the
replaceable plugin and are never edited by this UI. Only Twilio is supported.

Help & Reference → Call Bridge covers provisioning, routing roles, exact runtime-derived
webhook methods/URLs, pools/exhaustion, DNI, whispers, qualification, recording/playback,
diagnostic side effects, troubleshooting, retention and uninstall. Authoritative browser
consent linkage, CRM inquiry integration and call export remain absent.

## Candidate validation and packaging

Run tests/run.ps1 with -Php pointing to an existing PHP 8.1+ executable and -BaseArchive
pointing to the original Base 2.2.2 ZIP (the runner checks its SHA-256). It loads original
Base classes into isolated WordPress/DB fixtures, not an installed website.
Run tools/package-candidate.ps1 once to build the allow-listed installable artifact,
then node tools/verify-candidate.cjs. Existing archives are never overwritten.
The original tools/verify-baseline.cjs intentionally targets the unchanged 0.1.0 source
snapshot; use the candidate verifier for this checkout. Tests/tools/reports are excluded
from the installable ZIP.

See verification/0.1.1-candidate.md for executed checks and limits. No server/browser,
SQL concurrency, real Twilio, call, recording or consent acceptance is implied.

## Retained runtime documentation


The retained runtime extracts and modernizes the production-proven Twilio/DNI subsystem from LeadEngine 1.3.0 into a call-only companion plugin.

## Scope

Call Bridge 0.1.0 owns:

- Dynamic number insertion (DNI)
- Attribution sessions (GCLID, GBRAID, WBRAID, FBCLID, MSCLKID, UTM)
- Exclusive tracking-number leases
- Twilio inbound routing and signature validation
- Fail-safe call forwarding
- New/repeat caller metadata and whispers
- Call duration qualification
- Dual-channel recording and recording callbacks
- Admin-only recording playback
- Tracking-number administration
- Call log administration
- REST health diagnostics
- WP-CLI doctor / inspect-call / webhook-status

It intentionally does **not** contain:

- Fluent Forms ingestion
- FluentCRM lifecycle management
- Won/Lost workflow
- Lead CPTs
- Google Sheets exporting
- Google Ads conversion uploading
- Ads Bridge compatibility shims

Those concerns are not silently retained from LeadEngine.

## Identity and tables

REST namespace: `siteengine-call/v1`

Canonical call transaction ID: `twilio:{CallSid}`

Tables:

- `{prefix}se_cb_tracking_numbers`
- `{prefix}se_cb_attribution_sessions`
- `{prefix}se_cb_number_leases`
- `{prefix}se_cb_calls`

The new table names intentionally avoid the historical LeadEngine tables so Call Bridge can be tested alongside an existing LeadEngine installation without modifying its data.

## Number leasing

LeadEngine attributed inbound calls by asking which browser session most recently saw a tracking number. That can misattribute callers when a pool number is reused by overlapping visitors.

Call Bridge leases one tracking number exclusively to one active browser session. Default lease: 30 minutes. If the selected source pool has no free number, DNI fails closed and leaves the site's ordinary business number in place rather than sharing a tracking number and guessing attribution.

Browser attribution retention is separate and defaults to 90 days.

## Repeat callers

Repeat caller state is metadata only. A repeat caller can still represent a new opportunity and is not automatically suppressed. `qualification_status` is based on Twilio call outcome and configured minimum duration.

## DNI safety

Ordinary `tel:` links are swapped only when the original number matches an active tracking number's configured `forward_to` business number. Explicit opt-in elements may also use:

- `[data-se-phone]`
- `[data-se-call-tracking]`
- `.se-tracking-number`

## Configuration

Recommended layout:

1. Copy `config/call-bridge.example.php` to a secrets directory outside the web root.
2. Define `SECB_CONFIG_FILE` in `wp-config.php` before WordPress loads plugins.
3. Put Twilio credentials and Call Bridge constants in that external file.

Example:

    define('SECB_CONFIG_FILE', '/home/account/.secrets/siteengine-call-bridge.php');

Never package real credentials in the plugin ZIP.

## Twilio webhooks

After activation, open **Call Bridge > Settings**. Configure each Twilio tracking number's inbound Voice webhook to the displayed `/twilio/voice` URL using HTTP POST. Status, recording, and whisper callback URLs are generated by Call Bridge in TwiML.

## WP-CLI

Read-only diagnostics:

    wp se-call doctor
    wp se-call inspect-call --id=123
    wp se-call inspect-call --sid=CAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
    wp se-call webhook-status

## Recording

Recording is disabled per tracking number unless explicitly enabled. When enabled, Call Bridge plays the configured recording notice before `<Dial record="record-from-answer-dual">` begins.

Recording-consent requirements vary by jurisdiction. The plugin provides the mechanism; the client/operator is responsible for approved notice language and legal compliance.

Recording playback is restricted to logged-in WordPress administrators. API Key SID/Secret are preferred for media retrieval when configured; Account SID/Auth Token are the fallback.

## Fail-safe routing

Once Call Bridge has resolved the tracking number and its `forward_to`, an unexpected application/database error falls back to bare TwiML `<Dial>` where possible. Tracking may fail; the prospective customer's call should still ring.

## Upgrade / uninstall

Call Bridge preserves its tables on uninstall by default. To deliberately drop them, define `SECB_ALLOW_UNINSTALL_DROP` as `true` before deleting the plugin.

## Deferred roadmap

Call-level Google Sheets/Data Manager export belongs in Call Bridge, not Ads Bridge, but remains deferred until DNI/Twilio/recording behavior is accepted on an approved isolated test site. No call export API is implemented.
