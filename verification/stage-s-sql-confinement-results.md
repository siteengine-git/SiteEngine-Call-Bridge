# Standalone Stage S SQL confinement correction - local results, 2026-10-28

Starting checkpoint: 9084efb8fa8c2125d47e9f3e8bbc98e91428e869, preserved.
Branch: codex/cb-standalone-stage-s-20261028. Tooling correction only.
Runtime remains 0f39cebc7d521e02f686be2ee6016716bf738e03, 0.1.2 / DB 1.0.0.
No server request, staging, deployment, native SQL, credential migration, runtime
edit, package rebuild, main merge or release promotion occurred.

## Focused diff

The initial-table mutation shortcut is replaced by whole-statement matching for
literal single-row INSERT and single-table literal UPDATE/AND equality predicates.
The exact repository expiry sweep, two supervisor expiry-time edits and exact
supervisor lease-clone reset are the only additional mutation forms. No nested
reads, joins, aliases, extra targets/rows, INSERT SELECT or unsupported tails.
No SQL-mode-dependent escaped literals: quotes/backslashes inside values reject;
all pinned fixtures and generated identifiers fit this deliberate restriction.
Original-table supervisor reads, clone locks, clone DDL checks, ownership/cessation
protections, strict verdicts, workers and candidate loader remain unchanged.

The old standalone suite's synthetic unbounded UPDATE positive is replaced with
the actual repository expiry sweep; unbounded UPDATE now has a negative regression.
No supported candidate/harness mutation is removed. New focused tests use the
actual adapter insert/update serialization with an explicit in-memory mysqli mock,
including five fixture rows, lease creation/renewal and every fixture-edit shape.
Direct sweep, expiry edits, reset and DDL positives also run. Required foreign
JOIN UPDATE, INSERT SELECT, multi-target DELETE and subquery negatives run in both
candidate and supervisor scopes. Additional tests cover expression/identifier/tail
forms, original writes, scope restrictions, original reads and clone-only locks.

## Tests actually executed

Local existing PHP 8.5.12, -n; no extension installation or native DB connection.

| Check | Actual result | Limit |
|---|---|---|
| tests/stage-s-sql-confinement.php | 103 PASS / 0 FAIL | Actual serializer/gate; explicit mysqli mock, no SQL execution |
| Same regression suite against preserved 9084efb database.php | 46 PASS / 57 FAIL, exit 1 expected | Reproduces prior gap and unsupported forms accepted by prior policy; not 57 distinct production bugs |
| tests/stage-s-standalone.php | 59 PASS / 0 FAIL | Real fresh local PHP children, ZIP/reflection/audit/gate; no DB |
| tests/stage-s-tooling.php | 67 PASS / 0 FAIL | Historical mock verdicts and real local subprocess exit/timeout checks |
| PHP lint | 10/10 PASS | Eight standalone PHP dependencies and both standalone/confinement tests, local PHP 8.5.12 |
| node tools/verify-candidate.cjs | PASS | Original ZIP, CRC/size, 50 manifest hashes, 51 payload byte comparisons and historical archive preservation |
| New staging inventory | 9/9 hashes match | Eight PHP files plus RUNBOOK; manifest is separate metadata |

Prior test evidence and checkpoint remain intact. Native Linux/mysqli/MariaDB,
locks, independent SQL connections and installed WordPress behavior remain
unverified. No browser, non-administrator, consent or telephony acceptance.

## Independent pins and proposed next operations

New standalone STAGE-S-MANIFEST.sha256 SHA-256:
4324d5c70db1d9db9f5306c2f6b8b377795b33be08c0304441dd9d1d3e3d5bee
Previous staging pin remains historical:
865645a0d018e7122a9e719706571601a3d04fdc61b335df4d0fa1aa94692158

Unchanged runtime ZIP SHA-256:
6b240e1b7633cac208ad08834df76dc9f7d741168884560b8e0aae5ce161c5de
Unchanged runtime manifest SHA-256:
a8135e0e227eeee68f7cb436b9224ed2d918ad72e18b652ca1b361336e9ec977

After final Helper review and separate server authorization, follow the updated
standalone RUNBOOK exactly: independently verify this staging pin and nine listed
files; use the existing restricted private run outside the web root; verify the
unchanged candidate ZIP and extraction; read-only startup inventory and reviewed
audit pin; plan/collision checks; two marked clones/five fictional rows; 20+20
races, bounded lock timeout, six sequential cases; preserve strict verdict/process/
connection/lock evidence; ownership/cessation-verified cleanup and original-state
comparison. No expansion or retry over failed evidence. No website deployment;
its separate drain gate remains unresolved. These operations are PROPOSED ONLY.
