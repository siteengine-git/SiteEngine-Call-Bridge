# Call Bridge 0.1.8 — stable tabbed administration

Released November 16, 2026. Database version: **1.1.0**, unchanged.
Aubrey's installed UI review and CB Helper's release acceptance are complete.

## Exact accepted artifact

- Tag: `v0.1.8`
- Runtime/package source: `6e116cd1088aa90acbfc64901fa610b3afd74212`
- Installed verification record: `5bbbd22e05a82d83b5d99b0ab7f8e9bbb4cbb8c8`
- Installable ZIP: `siteengine-call-bridge-0.1.8.zip`, 72,484 bytes
- ZIP SHA-256: `cca787722ddcae47e15249f932dd15ff4ce894b05607ac6c09a66fcfc5de5e53`
- Runtime manifest SHA-256: `d48481392d87837392f527cc89e07a0dd7dedcbc7cc2eac1c1409f4f2d7ae3e3`
- Package: 53 files, including 52 manifest-listed payloads; 49 PHP files

The stable ZIP is a byte-identical renamed copy of the accepted
`siteengine-call-bridge-0.1.8-admin-tabs-review-r4-20261116.zip`. No rebuild,
reinstallation or runtime edit accompanies publication. Candidate-status wording
retained inside the archive describes its preparation history; this release
record and UI acceptance supersede those labels. Use the installable ZIP asset,
not GitHub's automatically generated source-code archive.

## Changes

One Call Bridge entry under SiteEngine opens four native WordPress section links:
Setup & Status (default), Tracking Numbers, Call Log and Help & Reference. The
selected section has native active styling and aria-current. Existing renderers,
number controls, validation, capabilities, nonces and action names are retained.

Legacy Numbers and Call Log URLs safely redirect before WordPress menu
authorization to their corresponding tabs, retaining edit, notice and pagination
arguments. Number save/delete returns to Tracking Numbers. Shared Base Help and
other modules' navigation remain intact. With Base absent, standalone Call Bridge
uses the same four sections. No new call-log pagination algorithm is introduced.

All 41 unaffected payloads match stable 0.1.7, including routing, signature
validation, callbacks, allocator/DNI, schema, credentials and call-evidence code.
No migration from DB 1.1.0, provider change, integration change or new calling
behavior is introduced.

## Accepted verification carried forward

- Installed equality for all 53 files and all 49 installed PHP lints passed.
  Actual development web runtime: PHP 8.3.36 / LiteSpeed, OPcache disabled.
- Forty-nine focused installed HTTPS assertions passed for four sections/default,
  one visible menu, other-module navigation, shared Help, legacy links, normal
  inactive fictional-number create/edit/save/delete, permission denial and nonce
  rejection. Denied requests preserved the owned row exactly.
- Existing authenticated in-app browser checks verified all four sections, selected
  labels, number controls, stored-time column, Help guide and one CB menu entry.
  Aubrey subsequently confirmed the installed tabs function properly.
- Original call/acquisition/number/settings/configuration data, activation, schema,
  indexes and GC definition were preserved. Owned test account, sessions, number,
  probe and maintenance controls were removed; execution ownership was released.
  Fixture counters and ordinary cron next-run timestamps advanced and were not reset.
- Focused local fixtures passed against Base 2.4.0 and original Base 2.2.2, Base
  absent and unsafe URL configurations, plus action/redirect, capability/nonce,
  timestamp and webhook-layout regressions. Local PHP was 8.5.12; installed
  acceptance above is separate from those adapters. Standalone is fixture-tested.
- Earlier installed routing, allocator, DNI and controlled live-call evidence is
  preserved. Publication repeats no calls or acceptance campaign.

Keyboard eligibility (native links/tabIndex0) was inspected; automated end-to-end
keyboard activation was inconclusive and is not certified. Number form submission
evidence is actual installed HTTPS, distinct from browser-form input.

## Scope and recovery

Supported core remains Twilio forwarding/whispers, signed callbacks, canonical call
identity, exclusive source-pinned reservations, DNI and persisted acquisition,
duration-based technical qualification, protected administration and trusted
read-only CallEvidence. Technical qualification is not business qualification,
consent, a won job or advertising eligibility.

Recording creation/playback remains outside acceptance; recording stays off.
Authoritative call-bound Consent, Google call delivery/export, durable child-leg
mapping and further client/integration milestones remain separate. No provider,
credential-storage, SignalWire, Base adapter or client rollout is included.

Back up affected files/configuration and preserve concurrent calls/data before any
future update. This release adds no schema change; targeted file rollback to core
0.1.7 retains DB 1.1.0. Do not uninstall/drop tables, restore a whole database or
overwrite unrelated settings. The existing additive-schema limits from 0.1.7 still
apply when rolling back farther than that version.

The authoritative latest-only delivery contains this one installable ZIP plus its
matching checksum, runtime manifest and these notes. Superseded delivery files and
historical GitHub releases are preserved outside the current delivery folder.
