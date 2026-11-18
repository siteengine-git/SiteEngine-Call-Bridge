# Prospective capture native acceptance — isolated only

CB owns this harness/source; AB Codex owns its retained isolated installation and
existing shared execution guard. No independent CB executor touches that site.
Main Alma, provider configuration, real calls and frozen evidence stay unchanged.

## Candidate and migration

Candidate 0.1.7 / DB 1.1.0. Verify the delivered ZIP SHA-256 and runtime manifest
before replacement. Normal WordPress bootstrap runs existing maybeUpgrade/dbDelta;
no forced activation or cron is required. Expected additive nullable columns only:

- `se_cb_calls.call_capture_json LONGTEXT DEFAULT NULL`
- `se_cb_calls.qualification_threshold_sec INT UNSIGNED DEFAULT NULL`
- `se_cb_number_leases.pool_group VARCHAR(50) DEFAULT NULL`

Before migration retain complete schemas, DB version, original column projections,
CB options/GC and exact plugin files. After migration require original old-column
values unchanged and all new fields NULL on old rows; raw SELECT-star hashes gain
keys and therefore are not an adequate migration preservation comparison. No
UPDATE/backfill is part of installation. Verify every installed payload and lint
packaged PHP. Do not install this candidate on main Alma.

## Exact target/ownership gates

Use the existing AB private site-access profile and actual retained root/prefix.
Create one fresh, restricted, owner-only run directory outside that web root under
the existing private allocation. Preserve it on any failure. Execute every phase
under AB's existing recorded shared-resource ownership; the harness does not
create another locking system or grant authority to acquire foreign resources.

The harness requires WordPress CLI, exact realpath root and prefix, loopback home
with nonstandard port, expected runtime manifest, 0.1.7 and stored DB1.1.0, private
0700 run directory and real loaded class origins/hashes. Main Alma's root is
explicitly rejected. No secret belongs in arguments, receipts or source.

Use `wp eval` with a require wrapper (not eval-file, whose wrapper can conflict
with strict_types). Set the five strings in `$args` from the approved private
identity and delivered manifest, never copied guesses:

```php
global $wpdb;
$args = [$phase, $privateRun, $exactIsolatedRoot, $expectedManifestSha256, $exactPrefix];
require $stagedPinnedHarnessPath;
```

Invoke through the established shared owner/WP execution procedure. Pin the
staged `call-capture.php` SHA-256 from the source checkpoint. Each operation is a
fresh process with the real installed plugin; do not substitute the class or DB.
Controller calls emit/exit, so require both worker exit 0 and the phase's private
JSON `status: PASS`. XML/HTTP200 alone is not a pass. Bound each process to 30s
in AB's existing supervisor; do not kill unrelated workers or silently rerun an
uncertain operation. Stop on any failure and preserve first evidence for CB.

## Ceilings and ordered phases

Exactly **one new fictional tracking number, one session, one lease and one
synthetic prospective call**. Phone values are reserved fictional strings
`+10000000071`, `+10000000072`, `+10000000079`. Marker and canonical CallSid derive
from the private run identity. Collision checks precede inserts. Recording is 0.
No page, provider request, purchase, media or actual HTTP callback is used.

1. `prepare`: requires absent real SID/Auth Token and no collisions or foreign
   expired-active lease that allocator expiry could touch. Snapshot original CB
   rows and the two callback diagnostic options. Transactionally create the number
   and source-isolated session/lease through actual CB repositories/allocator.
   Require the new lease's pool metadata to equal the selected original pool.
2. `voice`: process-only synthetic signing token, independently computed HMAC,
   actual Voice controller. Persist exactly one call; capture UTC, original lease
   ID/bounds/pool/session and flat acquisition. Nothing contacts Twilio.
3. `change`: alter only the owned number pool/threshold (120s) and session campaign.
   Require the original call evidence unchanged; no current-context reconstruction.
4. `voice-replay`: same canonical call, actual controller; no replacement capture.
5. `terminal`: completed90s callback; persist unqualified_short and actual applied
   threshold120 atomically. Preserve original UTC/assignment/acquisition.
6. `after-terminal-change`: change only the owned number threshold to5s.
7. `terminal-replay`, `late-progress`, `late-terminal`: actual duplicate/late
   controllers must preserve exact first terminal outcome/threshold and capture.
8. `read-a`, `read-b`: two fresh-process supported API reads; exact terminal
   evidence must match. Old records remain untouched, including unavailable fields.
9. **`retain` (normal passing disposition)**: deactivate the owned number, release
   its owned active lease, preserve call/session/lease/number history and exact API
   evidence. Restore only the two owned callback event options with current-value
   guards and verify every original row unchanged. Save retained IDs and owner.

There are at most four new CB rows, two owned number context updates before
retention, one owned session update, one terminal call update, two retention
updates and six callback diagnostic invocations. No schema change occurs inside
the fixture harness. Allocation is a single bounded existing allocator operation,
not a repeated concurrency campaign. Old native Stage S proof is not repeated.

## Retained graph and reference-safe cleanup

AB requested that the new evidence graph remain for explicit call-origin inquiry
binding. Do **not** run cleanup after passing tests. `retained.json` records exact
IDs and disposition; the number is inactive and lease released, so no live test
allocation/worker is retained. CB owns fixture provenance; AB Codex's assigned
installation executor owns retained-site stewardship and reference checks.

Only when its owner explicitly disposes of the graph may `cleanup` run. For a
persisted call it additionally requires an AB-owner supplied callable in
`$GLOBALS['secb_capture_can_delete']` with signature
`function(string $canonicalIdentity, int $cbRowId): bool`. This is a harness
parameter, **not an invented AB runtime API**. AB implements it using its actual
read-only relationship checks; return strict true only when no durable AB/other
reference remains. Missing/unknown/false refuses deletion. Never pass unconditional
true for a linked graph. No CB routine queries or edits guessed AB table schemas.

Cleanup verifies marker/IDs/phone/session/source and deletes only those four owned
rows in a transaction. Diagnostic options are guarded; after retain their expected
state is the original baseline. Original CB rows/events must match. Foreign or
concurrent changes stop cleanup; never wipe tables, reset sequences, globally
restore cron/options, or remove the run directory to conceal a failure.

## Honest coverage

Native WP controllers plus real MariaDB persistence are distinct from actual
provider HTTP delivery. Existing real-call/callback evidence remains separate.
Source/medium/campaign here are explicitly synthetic, all click IDs/Consent are
absent, no Fluent Forms or business outcome is invented. Capture UTC is server
receipt observation, not provider start, Consent grant or advertising permission.
Historical terminal/capture fields are not enriched; raw legacy timestamp timezone
stays unavailable. AB owns its explicit inquiry, business/advertising eligibility,
current DNC, Google identities/deduplication and delivery. Missing Consent remains
held. Return actual installation/migration, phase verdicts, retention and pins to
CB Codex; no source runtime correction by AB outside its owned boundary.
