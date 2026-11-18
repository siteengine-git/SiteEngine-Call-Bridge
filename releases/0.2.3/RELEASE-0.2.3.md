# Call Bridge 0.2.3 — ordinary credential onboarding

Stable promotion authorized by Integration Control, November 18, 2026, after
independent confirmation of Base 2.6.1 stable publication. DB **1.1.0**, unchanged.

## Exact release

- Tag: `v0.2.3`.
- Accepted runtime/package source: `e6debd83b78f0a9584be7a9a58bc9b9437118d7d`.
- Installed acceptance checkpoint: `d931ee7fc697f70bdaacd819c8f9a5ff8c449db0`.
- ZIP: `siteengine-call-bridge-0.2.3.zip`, **91,999 bytes**.
- ZIP SHA-256: `2998609f4743735b54a02e176113553d58e6cd26a221fe69db863b8fa6e7e03f`.
- Manifest SHA-256: `8af487c6009639077a4a7514f783cf7aae626137939e8cefa684994d41944fc8`.
- Package: 60 files, 59 manifest payload entries, 55 PHP files.

The stable ZIP is a renamed, byte-identical copy of the accepted review ZIP. No
runtime was rebuilt or reinstalled for publication. These external release notes
supersede historical candidate/pending/stable-version wording inside the exact
archive. Historical packages, evidence and releases remain preserved.

## Changes since stable 0.1.9

- SiteEngine → Credentials → Call Bridge now presents **Twilio Account SID**,
  **Twilio Auth Token** and one **Save credentials** action. Optional playback API
  credentials are under closed **Advanced**. Inputs are empty/write-only; the
  token is masked. Resource/version/staging controls stay out of ordinary setup.
- Base's supported `simple-save-1` set operation validates and atomically commits
  the complete encrypted pair. Both blank retains it. Partial/masked/invalid input,
  nonce/capability denial and definite precommit failure preserve the active pair.
  An ambiguous COMMIT is **UNCONFIRMED** and requires fresh receipt reconciliation;
  no automatic retry or false unchanged claim. Account SID changes require explicit
  intent after an explanation of account/number/webhook/media consequences.
- Existing complete external configuration remains authoritative. Partial external
  required pairs fail closed; sources/fields are never mixed. No automatic import,
  competing secret store or provider authentication request.
- Setup & Status and Help provide one complete checklist for credentials, phone
  roles, pools, duration qualification, direct/central guidance, recording/playback
  prerequisites and manual provider/cache steps. Local configuration is not labeled
  Connected or live accepted.
- Optional authenticated central receiver support is included, default off. It
  verifies scoped HMAC/freshness/replay/account/number/call ownership before using
  request-local context. Direct signatures and callback URLs remain supported.
  The separately packaged gateway service's production hosting, DNS, provider
  activation and real central-route calling remain a separate deployment milestone.

DB/schema/installer, lease/DNI algorithms, qualification, call identity/evidence and
recording transport are unchanged from 0.1.9. Direct Voice uses the shared callback
URL helper; the signature guard's gateway exception requires verified receiver
context, not forwarding headers. No provider picker, SMS, CRM workflow or exporter.

## Compatibility and upgrade

WordPress 6.4+, PHP 8.1+, existing documented extensions. Ordinary WP Save requires
Base's `simple-save-1`; this release is verified with **stable Base 2.6.1** at source
`cdab9f1c9cb29e600b3cf492500f982a854711e4`. CB uses set/runtime/navigation contracts,
not the additional document-save API. Base 2.6.1 preserves the existing set contract.
Externally configured core calling and standalone CB navigation still work without
Base. Older Base/runtime-only installations receive an ordinary-Save upgrade action.

A normal WordPress ZIP update from stable 0.1.9 to this exact archive was executed
with native WordPress/PHP/MariaDB in an owned disposable fixture. **80 assertions**
(including 59 payload digests) passed: four seeded rows and schema, original
acquisition bytes, activation, CB options, GC and external/playback resolution
preserved; Base-absent fallback and default-off central receiver retained. This was
native WP-CLI's WordPress ZIP installer/overwrite path, not a browser-upload test.

Actual main-Alma upgrade preservation across 0.1.9→0.2.0→0.2.1→0.2.2→0.2.3 is also
retained. DB remains 1.1.0: no new schema migration, reactivation or forced cron is
needed. Preserve targeted files, external settings, Base host key/encrypted-store
recovery custody, number configuration and historical acquisition/call records.

The prior deliberate Alma credential migration is complete and was not repeated.
No external-authority restoration or real credential re-save occurs in release work.
For file rollback, prefer the backed-up 0.2.2 payload while retaining current Base
credentials. **0.1.9 cannot read Base-managed credentials**: rolling back that far
requires separately planned source reconciliation, not a whole database restore.

## Verification and limits

Retained installed acceptance: **60/60 payload equality, 55/55 PHP lints** on Alma
PHP 8.3.36; **62 native authenticated HTTP assertions** for real rendered forms and
handlers with isolated synthetic material. Existing authenticated browser verified
labels, one Save, blank fields, account notice, Advanced and navigation; synthetic
mutations used native authenticated HTTP, not that browser's real credential form.

Focused release compatibility checks against actual Base 2.6.1: 21 CB Save, 23
credential and seven navigation assertions passed using real Base/Sodium with
modeled WordPress/storage adapters. These are distinct from native SQL evidence.
Earlier gateway acceptance includes 31 installed synthetic HTTPS assertions;
central production outage/independent emergency forwarding is not claimed.

All 14 main calls, original CB rows/schema, number/session/lease data, options,
configuration and GC were inspected unchanged. A later protected credential Save
advanced the stored pair after the retained onboarding snapshot; release work
preserves the **current** Base-managed pair/store/key byte-for-byte. Provider
authentication of a changed pair is not inferred from older live-call evidence.
No credential values or call identifiers are exposed. No provider request, new
call, message, recording session or media fetch was performed for this release.

Accepted prior forwarding/whisper/duration/callback/DNI/permission evidence is reused
where code is unchanged. Later operator/Helper confirmation accepted both recorded
channels; Codex did not independently listen, and earlier channel metrics remain
historical evidence. This release does not create new live/recording acceptance.

Recording remains off on Alma. Enabling it requires appropriate participant/notice
authorization. Protected playback remains administrator/REST-nonce guarded, fixed
Twilio-host TLS/no-redirect retrieval, two-channel WAV, 20 MiB maximum and private
no-store response; no range/resumable streaming or broad legacy-media compatibility.
Provider storage/retention and account ownership remain operator responsibilities.

Authoritative browser/call Consent, durable child-leg mapping and call-origin CRM/
Google delivery remain separate. AB owns advertising eligibility, conversion
identity, deduplication and delivery; CB supplies persisted read-only call evidence
and has no Google exporter. Duration qualification is technical, not business
qualification, Consent or a won job. No client/Climatrol/production rollout.

Owned fixture tables/files/users were removed and execution ownership released.
Private backups/evidence remain under CB custody. Prior stable 0.1.9 delivery is
archived and hash-verified outside the authoritative latest-only folder before
replacement. Public downloads and final delivery checksums are independently checked.
