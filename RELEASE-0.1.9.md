# Call Bridge 0.1.9 — protected recording playback

Released November 16, 2026 under Aubrey's explicit “Version and ship” direction.
Database version **1.1.0**, unchanged. No schema migration.

## Exact release

- Tag: `v0.1.9`
- Runtime/package source: `01242130595edb3c56d51fa70ccb1c604cd6ef96`
- Installed/live verification record: `147a8257c9e423ff5b9346c8c7e410319db8b898`
- ZIP: `siteengine-call-bridge-0.1.9.zip`, 72,667 bytes
- ZIP SHA-256: `987d0de30425b8c029569cde3b4019628238233af8b8cff12c84c6422f158816`
- Runtime manifest SHA-256: `cb4337a60ecef30d2e15a83fba0bc182969daba4be09fde8fa3c0792c0c9f935`
- Package: 53 files, 52 manifest-listed payloads and 49 PHP files.

The stable ZIP is a renamed, byte-identical copy of the preserved recording review
archive. These notes supersede candidate/review-pending wording inside that exact
archive. Publication does not rebuild or reinstall the verified runtime. Historical
packages and acceptance records remain preserved.

## Changes

- Call Log Play links supply the WordPress REST nonce. REST authentication and
  explicit administrator/nonce checks protect recording retrieval.
- The proxy derives the HTTPS Twilio target from the configured account and saved
  Recording SID. It never fetches a callback-supplied URL, follows redirects or
  exposes provider credentials to the browser. TLS verification remains enabled.
- Playback requests two-channel WAV, validates RIFF/WAVE, bounds responses to
  20 MiB, and serves private, no-store audio. Reload Call Log if its nonce expires.
- Recording callbacks check conditional persistence with three bounded attempts
  and winner readback. First completed recording evidence stays authoritative;
  unknown read/write outcomes return 503. This does not guarantee provider retries.

Existing per-number enablement, notice before Dial, record-from-answer-dual,
signature validation, forwarding, whispers, qualification, acquisition evidence,
lease/DNI behavior and external credential resolver are preserved. Forty-six
payload files are byte-identical to stable 0.1.8.

## Verification and limits

Local PHP 8.5.12: 41 callback assertions, 18 proxy assertions, six renderer
assertions and eight affected/new PHP lints passed. Adapters are distinct from
installed native evidence.

Alma: exact 53-file equality and 49 installed PHP lints passed on PHP 8.3.36.
Installed signed callback/database tests and permission checks passed. One approved
real test call produced a completed provider recording associated with the correct
canonical call. The actual authenticated browser Play link retrieved and decoded
the two-channel WAV and advanced playback without media error. Original recording
settings were restored, fixtures/controls removed and execution ownership released.

**Two-participant recorded-audio acceptance remains unproven.** The saved test
recording has two channels, but its caller channel is effectively silent while its
forwarding channel contains audio. The operator's combined PC/phone setup did not
establish independent participants. This does not demonstrate a CB runtime defect
or prove both speakers were recorded. Direct listening by this Codex session was
unavailable; low-confidence offline recognition was not accepted as evidence.
The live pre-recording notice was configured/generated but not explicitly confirmed
audible. Do not describe this release as complete two-sided recording acceptance.

The proxy is a bounded full-file service, not range/resumable streaming. Legacy
mono recordings and files exceeding 20 MiB fail closed. Provider storage, account
access and recording retention remain operator responsibilities.

Recording defaults off and should remain off until participants, notice and the
specific recording use are approved. This notice mechanism establishes neither
authoritative browser/call consent nor advertising consent. No additional call is
authorized by publication.

## Installation and rollback

Use this ZIP with normal WordPress plugin update procedures after targeted file,
configuration and CB-data backups. Preserve the external configuration file,
activation and per-number settings. No reactivation, forced migration or cron
execution is required. Verify payload hashes, runtime, GC and retained records.
Alma already has these exact bytes; no publication-time reinstall is needed.

DB remains 1.1.0. A targeted file rollback to backed-up 0.1.8 is schema compatible;
it also removes these playback security/correctness improvements. Never restore
the whole site/database or delete real calls/recordings to roll back plugin files.
Publication does not deploy to Climatrol or any other client.

## Scope retained

Twilio is the only provider. Core routing, DNI, duration qualification, call history,
tabbed administration and supported read-only call evidence remain included.
Duration qualification is technical, not proof of a won job or business quality.

AB owns advertising eligibility, conversion identity, deduplication and delivery.
CB has no Google exporter. Authoritative call-bound Consent, call-advertising
integration and durable child-leg mapping remain separate, unaccepted capabilities.
No new Base credential API, FE synchronization, SMS or CRM workflow is introduced.

Provider references: [Dial recording](https://www.twilio.com/docs/voice/twiml/dial),
[recording media](https://www.twilio.com/docs/voice/api/recording).
