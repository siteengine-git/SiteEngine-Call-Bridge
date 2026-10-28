# 0.1.2 lease/DNI candidate — local results, not accepted

Starting tooling commit: 8a81528336271b365e251b3c2338de22c91bc170.
Installed runtime reference remains f763b3cd4ce1e73416a73c5802b35d88469571ab.
Candidate checkpoint: review-0.1.2-lease-dni-20261028. No deployment, Alma
request, native SQL execution, provider action or release promotion in this task.

## Bounded changes

- NumberLeaseRepository uses one SQL-server/database/site-prefix lock for all
  sessions and sources: secb_alloc_ + first 40 SHA-1 hex characters of
  DB_NAME + '|' + wpdb->prefix. GET_LOCK waits at most 2 seconds; non-success or
  error returns no number. Acquired locks release in finally, including exceptions.
- Expiry, session, availability and eligibility uncertainty fails closed. Renewal
  writes check the original row/status/expiry; checked readback proves persisted
  expiry and eligibility. Zero changed rows can be legitimate; they never suffice
  without readback. An uncertain successful write retains its reservation.
- Existing leases remain source-pinned and sticky across changes to the largest
  pool. Different source or inactive/deleted/source/phone-mismatched number cannot
  renew; the original reservation stays until its original expiry. After expiry,
  current source/pool selection applies. Preexisting duplicate reservations reject.
- TrackSessionController saves attribution under that same lock, after source and
  eligibility validation. Rejected transitions preserve old attribution exactly.
  Session persistence is checked. Successful empty/exhausted pool reads retain
  attribution capture, while failed reads do not authorize allocation/renewal.
- DNI eligibility uses remembered original telephone href when refreshing a
  previously swapped link. Original href/text, non-phone labels, unrelated links,
  late DOM insertion and failure restoration remain protected.
- Release 0.1.2, DB 1.0.0; no schema migration. Twilio controllers/signature/routing,
  call identity, recording, Base UI and external configuration remain unchanged.

## Executed checks — 2026-10-28

| Check | Result | Limit |
|---|---|---|
| Actual lease/tracking repositories, PHP 8.5.12 -n | 61 PASS / 0 FAIL | Fake wpdb, clock and connection locks; no native SQL |
| Actual controller/session/lease integration | 14 PASS / 0 FAIL | REST/database doubles; deterministic clock, no WP installation |
| Extracted shipped DNI, Node VM | 31 PASS / 0 FAIL | DOM/network/storage/timer doubles |
| Shipped DNI, supported in-app browser | 9 PASS / 0 FAIL | Real DOM/MutationObserver; injected fetch/storage/timing, loopback only |
| Original Base 2.2.2 integration fixtures | 77 / 73 / 71 PASS | Base present / absent / unsafe URL; local adapters, no deployed browser acceptance |
| Existing webhook layout rendering fixtures | 19 PASS | Local template, no installed UI check repeated |
| PHP lint | 54 files PASS | 47 packaged PHP plus 7 test/prepared PHP; local PHP 8.5.12, not Alma PHP 8.3 |
| Candidate ZIP verification | PASS | 50 manifest hashes, 51 source/ZIP byte comparisons, CRC/size, metadata, JS parse and publication scans |

The original L1-L5/D1 assertions are retained (L3 is strengthened to require null).
Historical red results remain in lease-dni-local-results.md and Git history;
private historical evidence was not overwritten. The old expiry-error
characterization now explicitly requires false, distinct from successful zero.
The fake SQL layer was extended for new queries, including actual selected column
identity; it does not simulate database isolation. Both suites return nonzero on
failure. Initial iterative harness mismatches were corrected; final results above
are the completed rerun, not accepted expected failures.

Added cases cover same-session cross-source interleaving, controller attribution
preservation, failed expiry/eligibility/readback/persistence, zero-row renewal,
source/deactivation/deletion/phone/pool transitions, retained reservations,
uncertain insert readback, duplicate state and replacement/late-node/restoration.
The real browser replacement case checks labels/unrelated links and a later
failed renewal restoring originals. All fixture numbers are fictional 202-555-01xx;
telephone links are inert, no dialing or provider access. Browser access initially
timed out at its automatic security check; the permitted single retry succeeded.
The temporary tab was closed and loopback server stopped. Existing admin session
was not used to make Alma requests.

## Package and prepared Alma proposal

ZIP: siteengine-call-bridge-0.1.2-lease-dni-review-20261028.zip (63,152 bytes).
SHA-256: 6b240e1b7633cac208ad08834df76dc9f7d741168884560b8e0aae5ce161c5de
Manifest: RELEASE-MANIFEST.sha256 (50 payload entries).
Manifest SHA-256: a8135e0e227eeee68f7cb436b9224ed2d918ad72e18b652ca1b361336e9ec977

Verifier additionally confirms 42 files byte-identical to the installed f763b3c
review archive, and preserves the original archive. Only the five lease/session/
DNI runtime files and four release metadata/document files differ from that package.
No test tooling, private evidence or runbook is bundled in the installable ZIP.

RUNBOOK.md and lease-sql.php are repinned to this manifest. New private run ID is
cb-lease-dni-012-20261028-01. The original fixture namespace remains collision-checked
and requires absence; old runs are never reused. SQL still uses exactly two
owned shadow tables/five fictional tracking rows, with independent SQL processes
and bounded barriers. Clone lock is computed by the candidate repository under
its clone prefix. Cross-source same-session race now requires exactly one winner,
one null and one active reservation. Browser stage remains separate: three owned
real tracking rows, two independent browser contexts and an expiring protected,
non-dialing page. Source rejection must preserve private attribution hashes and
original expiry. Active GC stays unchanged; no forced cron. Exact-ID/marker cleanup,
config/data comparisons and conflict stop conditions remain mandatory.

## Remaining risks / next gate

No native SQL concurrency, installed DNI, production-theme/competing-DNI coexistence,
Alma PHP 8.3 execution, non-administrator browser denial, signed callbacks, live
forwarding, playback or consent linkage is accepted. No Base credential API or
permanent credential storage was added. Existing external configuration is authoritative.

The site lock trades throughput for bounded serialization. Allocation scans active
reservations; performance at large pools remains unmeasured. Admin edits, GC,
legacy-version requests and arbitrary SQL do not share it. Native release errors,
process/SQL privileges and prepared harness execution remain unverified. A future
deployment must avoid overlapping old/new allocator requests; no schema constraint
is claimed. Session/lease writes are not an atomic cross-table transaction; on an
uncertain write/readback, no number is returned and persisted reservations remain.

Next: review this candidate and separately authorize a quiescent Alma deployment,
then the isolated native SQL stage. Do not execute fixture writes under this task.
