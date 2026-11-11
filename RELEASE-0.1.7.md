# Call Bridge 0.1.7 — core stable release

Released November 11, 2026. Database version: **1.1.0**.

This release promotes the exact accepted development artifact without rebuilding
or changing any installed plugin file. Its earlier candidate filename and the
candidate-status wording retained inside the archive describe its preparation
history; this release record supersedes those acceptance labels. The external
stable filename is `siteengine-call-bridge-0.1.7.zip`.

## Exact identity

- Tag: `v0.1.7`
- Runtime/package source: `480b4ca2def17687a4db80e36891d03bf05ed003`
- ZIP: `siteengine-call-bridge-0.1.7.zip`, 70,902 bytes
- ZIP SHA-256: `537700ab86592e6ceeabe0693be9f7e700e75f5fac6274a9628de0885d4e79ef`
- Runtime manifest SHA-256: `67c982e3e01ec59369542b7c69d593cc37dc5aa8f34d21c440aa59a37170e9c4`
- Package: 53 files, including 52 manifest-listed payloads and the manifest itself
- Requires WordPress 6.4+ and PHP 8.1+; focused installed verification used PHP 8.3.36.

The original `siteengine-call-bridge-0.1.7-forward-capture-review-r2-20261111.zip`
and earlier archives remain preserved. Renaming the published copy changes no ZIP
bytes. Use the installable ZIP asset, not GitHub's automatic source-code archive.

## Supported core scope

- Twilio inbound routing and fail-safe forwarding, signed callbacks, new/repeat
  caller whispers, canonical `twilio:{CallSid}` identity and persisted lifecycle.
- Exclusive source-pinned number reservations, fail-closed allocation/renewal,
  and DNI swapping/restoration with original acquisition evidence preserved.
- Duration-based technical qualification and protected administrator controls.
- Read-only Setup & Status, existing shared-Base module/help integration and
  standalone navigation. No new Base adapter is introduced by this release.
- Accurate Call Log `Time (stored)` label without rewriting or converting dates.
- Trusted same-site PHP evidence API:
  `SiteEngine\CallBridge\Integration\CallEvidence::read($canonicalIdentity)`.
  It returns stored call evidence and explicit unavailable fields, not current
  lease reconstruction or advertising permission. It exposes no caller contact,
  credentials, media access or unauthenticated network endpoint.
- Forward-only capture of observed original reservation context and the actual
  threshold applied by the first successfully persisted terminal decision.

## Migration and recovery

DB 1.1.0 adds three nullable columns: calls.call_capture_json,
calls.qualification_threshold_sec and number_leases.pool_group. No historical
backfill, timestamp rewrite or new indexes are introduced. Normal upgrade boot
performs the additive migration; reactivation and forced cron are unnecessary.

Back up plugin files, configuration and the affected database state before an
upgrade. MariaDB DDL is not transactionally reversible. Targeted file rollback
does not remove additive columns; preserve new call evidence and concurrent data.
Do not restore a whole database, drop columns or overwrite unrelated configuration
as routine recovery. An older plugin may record its older DB version on boot;
that is not a full schema rollback.

## Verification carried forward

Completed native allocator Stage S, installed DNI and permission checks,
synthetic callback/native persistence checks, and operator-observed live routing,
audio, whispers, qualification and controlled synthetic-campaign attribution are
retained evidence. This publication did not repeat those campaigns or place calls.

Focused 0.1.7 verification established complete installed package equality, all 49
PHP lints, additive schema/index preservation, unchanged historical column values,
retained-call evidence reads and unchanged raw timestamp rendering. Controlled
installation restored configuration/temporary controls and preserved activation
and GC registration. Ordinary scheduled next-run timestamps advanced; this is not
evidence that all background activity was absent.

Local tests and a separate 12-phase native isolated capture run covered fresh-
process reads, original evidence retention and duplicate/late callbacks. Test
doubles, SQLite and injected errors remain distinct from native installed checks.

## Excluded capabilities and remaining limits

- Recording creation and secured playback are **outside release acceptance**.
  Recording remains off in the verified development configuration. Dormant
  recording code is not a claim of playback/media acceptance; do not enable it
  without a separate reviewed assignment and notice/access requirements.
- No authoritative call-bound Consent, Google exporter or call-advertising
  eligibility/delivery. A synthetic campaign label is not a real Google click,
  business qualification, consent or export permission. AB owns downstream inquiry,
  business outcomes, Google identity, eligibility, deduplication and delivery.
- No durable child-leg mapping or complete conflicting-event reconciliation.
  First persisted terminal/first completed recording retention is bounded behavior.
  HTTP 503 does not prove provider retry.
- Raw historical timestamps have no asserted timezone. UTC capture time is a
  server observation, not provider call start, consent time or conversion time.
- Independent-browser-context exclusivity remains a documented coverage limit;
  native SQL allocator proof is not a substitute. Cache coverage was DYNAMIC-only
  and competing-DNI coverage restricted. No broad cache/browser campaign is implied.
- No client/production rollout, provider changes, recording/media retrieval,
  customer sending or advertising submission accompanies publication.

## Delivery

The authoritative latest-only delivery contains this one installable ZIP plus
its matching SHA-256, runtime manifest and these release notes. Earlier delivery
files are archived outside that folder. Stable publication does not authorize
client deployment or enable excluded integrations.
