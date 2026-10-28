# Stage S acceptance-tooling correction — local results, 2026-10-28

Runtime candidate remains 0f39cebc7d521e02f686be2ee6016716bf738e03, release 0.1.2 /
DB 1.0.0. No runtime, package, package manifest or historical finding changed.
Deployment and Stage S remain PAUSED for Helper re-review; this is tooling only.

## Focused change

- Pure complete-evidence verdict: strict timeout/no-row and one-winner/one-active-row
  requirements, exact persisted/returned identity and expiry, five owned fixture
  definitions/IDs, no duplicate live number/session ownership. Missing, malformed,
  denied or incomplete evidence fails. Both historical false positives are retained
  and rejected by the new verdict while reproduced by the historical predicate.
- Process helper retains first real status and checks proc_close without normalizing
  unknown to zero. Two successful exits are mandatory even after result publication;
  nonzero, unknown, signal, timeout or requested termination fails. Ready/result
  connection IDs must be consistent, unchanged and independent.
- Workers retain allocating connections at a finish barrier. App lock-release check
  occurs before result publication. Supervisor checks those IDs are still open,
  verifies its own timeout-lock acquire/release, and acquires/releases the clone lock
  from another connection before allowing worker exit. Final verdict is published
  after exits, SQL readback, final release and owned-process cleanup checks. Foreign
  locks are never released. Ready/result JSON is published atomically within the
  restricted case directory; partial/colliding evidence blocks.
- Cleanup restrictions preserved/strengthened: exact marker/state/table guards plus
  closed-worker evidence before reset/drop and independent table-absence readback.
  Missing cessation evidence after interruption requires scoped review, not a
  cleanup bypass. No original table or runtime behavior was changed.
- Executable clone-only renewal, expiry, pool, source, deactivation and sticky cases
  with explicit assertions, saved summaries, independent-connection probes, exact
  captured-ID edits and ordinary finally restoration of original wpdb/prefix.
  The runbook provides complete PowerShell/shared-runner commands with nonzero
  assertion propagation. These native paths are prepared, not executed.

## Actual validation

- PHP 8.3.36: 67 focused checks PASS / 0 FAIL; six changed/new PHP files lint PASS.
- PHP 8.5.12: 67 focused checks PASS / 0 FAIL; same six lint PASS.
- Pure SQL row/evidence arrays and lock-helper SQL responses are SIMULATED. Actual
  local PHP subprocesses cover result-file-then-exit 0/7, repeated polls/close,
  timeout/termination; pre-8.3 exit-cache behavior is simulated, not a third runtime.
- Runbook PowerShell blocks parse without execution. No command in them ran.
- Existing candidate verifier PASS: ZIP CRC/size, 50 manifest hashes, 51 payload
  comparisons, version coherence, publication scan, JS syntax and old archives.
- ZIP SHA-256 remains
  6b240e1b7633cac208ad08834df76dc9f7d741168884560b8e0aae5ce161c5de;
  package-manifest SHA-256 remains
  a8135e0e227eeee68f7cb436b9224ed2d918ad72e18b652ca1b361336e9ec977.

Reproduce using an existing local PHP executable:

```text
php -n tests/stage-s-tooling.php
node tools/verify-candidate.cjs
```

Staging hashes: tools/acceptance/STAGE-S-MANIFEST.sha256 lists all five PHP harness
files plus RUNBOOK.md. Compare its independently delivered hash before checking
file entries. No local tests/private evidence are server runtime dependencies.

## Remaining review concerns / acceptance limits

No Alma requests, fixture/SQL writes, deployment, native lock/process races or
Linux process behavior were tested. Independent live connection checks, schema
representations, native peer-wpdb routing/probes and sequential SQL cases remain
unverified. PROCESSLIST needs visibility of only these same-account connection IDs;
if unavailable, stop and report rather than expanding grants. No data outage or
permission injection is proposed. Ordinary finally is not a universal fatal/kill/
shutdown-hook guarantee. Bounds fail acceptance rather than extending time or
assuming a stopped child; unknown cessation blocks cleanup.

Local subprocess tests used existing Windows PHP 8.3.36/8.5.12. PHP 8.1/8.2 and
Alma/Linux were not executed. An early local test used an absolute Unicode path in
child code and failed to write its result; it was corrected to use the owned cwd
and ASCII relative name. Final tests use actual files/exits, not suppressed errors.

No provider/browser/call/media/credential/AWS/other-plugin operation, main merge,
release promotion, GC change, transport or isolation release. A later exact tooling
pin and Helper review are required before any server resumption. No runtime repair
is implied by this tooling correction.
