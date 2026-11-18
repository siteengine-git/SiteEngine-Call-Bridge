# Standalone Stage S tooling ? local review results, 2026-10-28

Runtime candidate remains 0f39cebc7d521e02f686be2ee6016716bf738e03,
release 0.1.2 / DB 1.0.0. Historical tooling bd135897e25c08bec126e36bafeaf2c140203b6b
and all its files/evidence are preserved. New tooling is additive under
tools/acceptance/standalone; runtime, ZIP and original manifest are unchanged.
This task explicitly authorizes decoupling Stage S from installed CB. The old
reconciliation's absence of that instruction was true at that earlier point.

## Focused change

- Fresh standalone CLI loader checks pinned ZIP, CRC/size, all 50 payload hashes,
  all 51 extracted bytes, missing/extra files and symlinks before requiring only
  Config, TrackingNumberRepository and NumberLeaseRepository.
- Reflection checks exact class paths and hashes in supervisor and workers;
  ready/result origin records must match. Already loaded/mixed/wrong CB classes,
  Installer/Plugin, WP APIs/includes/autoloaders and startup files reject.
- No WordPress bootstrap, candidate entrypoint, activation, Installer, Plugin::boot
  or WP hook registration. Read-only startup/MU/drop-in audit is pinned before
  execution; custom DB/content routing and dynamic config require review.
- Existing DB connection literals are read as data into process memory. No eval,
  include, credential copy/editor/storage or Base contract. The standalone-owned
  minimal mysqli adapter is explicitly NOT WordPress wpdb. Its native compatibility
  is pending; no broad replacement of production behavior is claimed.
- Candidate backtraces require a validated clone scope. SQL gate restricts candidate
  reads/writes to exactly two shadow tables and the clone-prefix allocation lock.
  Original CB tables/options are supervisor-read-only; DDL only exact owned clones.
- Original strict verdict/process implementations are byte-identical. Race identity,
  checked exits, open-connection lock probes, deadlines, output limits, ownership,
  operation lock, sequential assertions and unknown-cessation cleanup guards remain.
- Temporary prefix/adapter globals and private context are restored/cleared before
  exit; native shutdown restoration repeats this without SQL. Peer connection only
  is closed by sequential scope. Constants are private-process-only, not website edits.

## Actual executed local results

| Check | Result | Boundary |
|---|---|---|
| New standalone suite, PHP 8.5.12 -n | 59 PASS / 0 FAIL | Fresh PHP children, real package/loader/reflection/config parser and SQL gate; NO DB connection |
| Existing corrected-tooling suite, PHP 8.5.12 -n | 67 PASS / 0 FAIL | Simulated SQL evidence, actual local subprocess exit/timeout tests |
| New PHP lint | 9/9 PASS | Eight staged PHP files plus new test; local PHP 8.5.12 |
| Candidate ZIP/source verifier | PASS | Original candidate and historical archives intact; 50 hashes/51 byte comparisons |
| Historical/runtime diff | Empty | No existing harness/runtime/ZIP/manifest changes |

New tests reject preloaded, mixed and wrong origins; missing/mismatched worker
origin evidence; Installer/Plugin/hook/autoloader/bootstrap presence; modified ZIP,
class/entry bytes; conditional/duplicate/dynamic config; wrong prefixes, original
mutation/read targets, foreign locks and unreviewed SQL; missing/changed audit pins,
custom routing, changed tooling/dependencies and extra staged files. Trap bootstrap,
MU and object-cache code is inventoried but never executed. Prefix/global restoration
is tested after an injected exception. Local tests leave private synthetic evidence
only; no operational/credential material is publication content.

## Independent staging pins

New tools/acceptance/standalone/STAGE-S-MANIFEST.sha256:
865645a0d018e7122a9e719706571601a3d04fdc61b335df4d0fa1aa94692158
Nine entries: eight PHP dependencies plus standalone RUNBOOK.md. Verify this
independent digest first, then each entry before including any PHP. The manifest
itself is staged as metadata; only listed dependencies belong in harness/.

Unchanged runtime ZIP SHA-256:
6b240e1b7633cac208ad08834df76dc9f7d741168884560b8e0aae5ce161c5de
Unchanged runtime manifest SHA-256:
a8135e0e227eeee68f7cb436b9224ed2d918ad72e18b652ca1b361336e9ec977

## Proposed Alma operations ? not executed

Use the existing owner/profile/PHP/SSH route only. New owner-only run ID:
cb-lease-dni-012-standalone-20261028-01 outside the web root. After separate approval:
stage nine dependencies + manifest and unchanged candidate.zip; prepare a fresh
verified 51-file private extraction; audit startup files, review/pin it; lint;
plan, seed two marked clones/five fictional rows, run 20 last-slot and 20
cross-source races, one lock-timeout and six sequential cases; inspect; remove
only marker/state/cessation-verified shadow tables and confirm absence. Compare
original counts/checksums, CB option/config hashes and GC; report background deltas.
Exact paths, fixtures, invocation, limits and failure recovery are in the new runbook.
Do not use WP-CLI/Invoke-Site-Wp to run this entry; it would load application code.
Use existing direct owner PHP execution after the canonical shared preflight.

No server access/preflight, staging, SQL fixture, deployment, infrastructure change,
provider/browser/media action or package rebuild occurred. Website remains last
recorded 0.1.1, not freshly inspected in this task. Deployment remains paused at
its independent drain gate. No new Base API or competing credential facility.

## Remaining limitations / next action

Linux entry/ownership modes, mysqli handshake/query/error/affected-row semantics,
MariaDB locks/PROCESSLIST/DDL/checksums, process lifecycle and native sequential
execution are UNVERIFIED. This tests candidate code through a documented minimal
adapter, not installed WordPress wpdb, object-cache/drop-in integration or browser
attribution. No SQL exclusivity, website/DNI, consent, signed callbacks, live calls,
recording or playback acceptance is claimed. Stop on native failure; no relaxed
guards, privilege expansion or retry over existing evidence. Unknown worker
cessation requires operator review before cleanup.

Next: Helper review of the standalone boundary, staging pin and runbook; then a
separate server authorization for standalone Stage S. Website deployment stays paused.
