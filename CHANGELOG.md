# Changelog

## 0.1.2 - 2026-10-28 (unaccepted candidate; not deployed)

- Fail closed on allocation-critical read/write errors and verify persisted renewal expiry, including legitimate zero-row updates.
- Serialize all sources/sessions within a database/site-prefix allocation lock; wait at most two seconds and release in finally. No schema change; DB stays 1.0.0.
- Revalidate current number eligibility; preserve source-pinned reservations until expiry. Valid leases remain sticky across pool-size changes.
- Guard attribution upsert under the allocation lock so rejected source transitions preserve attribution; check session persistence before issuing/renewing a lease.
- Refresh ordinary DNI telephone links after replacement renewal while retaining labels, unrelated-link protection and restoration.
- Preserve historical reproducers, extend local contract/controller/browser fixtures, and repin the prepared-only Alma plan.

## 0.1.1 - 2026-10-27 (webhook layout review revision; not deployed)

- Webhook URLs use stacked, CB-scoped endpoint sections with selectable single-line text and keyboard-focusable horizontal scrolling at narrow widths. Endpoint values, methods, escaping and navigation are unchanged.
- Optional original Base 2.2.2 module navigation and CB-owned Help tab; no dependency on its unaccepted routing patch.
- Read-only guided Setup & Status distinguishes missing prerequisites, optional features off, configuration presence and pending live acceptance.
- Shared business context keeps phone roles separate; existing external configuration and per-number settings remain authoritative.
- Twilio-only onboarding and comprehensive operations/reference guidance without provider actions or credential editing.
- Isolated Base-present/absent, status, security/redaction, Help and mutation-trap fixtures.
- No controller/signature/DNI/lease/schema/recording-transport changes. Database version remains 1.0.0.

## 0.1.0 - 2026-10-20

Initial SiteEngine Call Bridge extraction from the production LeadEngine 1.3.0 call subsystem.

### Preserved / modernized

- Twilio request signature validation
- Inbound call routing with fail-safe forwarding
- Dual-channel recordings and completion callbacks
- Admin-only recording playback
- New/repeat caller whispering
- Tracking-number and Call Log admin screens
- DNI attribution capture
- GCLID / GBRAID / WBRAID / UTM support
- Added FBCLID / MSCLKID capture

### Corrected architecture

- New `SiteEngine\\CallBridge` namespace and `siteengine-call/v1` REST namespace
- New isolated `se_cb_*` tables; no mutation of LeadEngine tables
- Exclusive short-lived number leases replace "most recent session shown this number" attribution
- Pool exhaustion fails closed rather than sharing an actively leased number
- Repeat callers remain metadata and are not automatically suppressed
- Canonical call identity is `twilio:{CallSid}`
- DNI swaps ordinary `tel:` links only when they match the configured business `forward_to` number
- Stored browser numbers are no longer reused when lease renewal fails
- Optional Twilio API key credentials for recording playback

### Removed from Call Bridge

- Fluent Forms ingestion
- FluentCRM integration
- Lead CPT and lifecycle workflow
- Won/Lost logic
- Google Sheets exporter
- Google Ads conversion exporter state
- LeadEngine conversion queue/status machinery

No data migration from LeadEngine is performed by 0.1.0.
