# Isolated lease/DNI acceptance — PREPARED, NOT EXECUTED

This document and the PHP files are a review proposal. **Do not seed fixtures or
publish the temporary page under the current task.** The six historical failures remain documented in verification/lease-dni-local-results.md.
The 0.1.2 fixes and local results are recorded in verification/lease-dni-0.1.2-results.md;
native SQL/installed DNI acceptance is still pending. No provider credentials, number
purchases, calls, recordings, permissions changes or shared-plugin edits belong
to this run. Release/DB are 0.1.2/1.0.0; no main promotion.

## Pin, access, paths and ownership

Runtime pin: the unaccepted 0.1.2 candidate delivered with this runbook; package manifest SHA-256
`a8135e0e227eeee68f7cb436b9224ed2d918ad72e18b652ca1b361336e9ec977`.
The harness verifies every manifest payload before mutations. Test-tooling is
pinned by its separate Git checkpoint. A later core-fix candidate requires an
explicit new manifest pin/review; never bypass the gate.

Use the existing Alma owner, site root, PHP and canonical site-access.json from
local STATUS.md. Shared Connection-Check.ps1 is the capability gate;
Invoke-Site-Wp.ps1 is the website command runner. No project connection script,
new account/key, browser flag, debug port or cookie transfer.

Let `SITE_ROOT` be the registered WordPress root and `OWNER_HOME=dirname(SITE_ROOT)`.
Exact run directory: `OWNER_HOME/.secb-acceptance/cb-lease-dni-012-20261028-01/`.
All run/harness/subcase directories 0700, files 0600, existing site owner/group.
If any intended path already exists, preserve it; do not seed again or overwrite
evidence. The operator must review collisions, not delete them to proceed.

After new authorization only, place these exact pinned files under `RUN/harness/`:
`lease-sql.php`, `browser-fixtures.php` (together, since it requires its sibling).
Temporary protected page: `SITE_ROOT/secb-acceptance-20261027-01/index.php`, copied
from `dni-page.php`; directory 0700/file 0600, same site owner. No source plugin
replacement or second editable source home. Record copied hashes privately.

The PHP files have **no autorun**. A request file for the shared runner contains
PHP without an opening tag, for example (substitute the reviewed absolute RUN):

```php
require 'RUN/harness/lease-sql.php';
echo json_encode(secb_accept_sql('plan', 'RUN'), JSON_PRETTY_PRINT), "\n";
```

Invoke using the existing shared `Invoke-Site-Wp.ps1 -SiteProfile <registered
profile> -PhpFile <local request file>`. Only after fixture-write approval replace
the call with one of the explicit mutation calls below. `write=true` is a safety
gate, not authorization. Use only the public secb_accept_sql / secb_accept_browser
entry functions; their shared operation.lock refuses overlapping coordinator
operations. Workers join only their supervisor's barrier and do not take that
coordinator lock. Normal WP bootstrap retains Installer/GC side effects.
The harness was linted locally; its real WP/SQL/process privileges are untested.
Missing CREATE/ALTER/DROP/CHECKSUM/proc_open privilege must be reported exactly;
do not expand privileges, install a database or fall back to application exploits.

## Allocation lock and transition contract (0.1.2)

Lock name is secb_alloc_ plus the first 40 hex characters of SHA-1 of
DB_NAME + '|' + wpdb->prefix (51 bytes total). It is local to the SQL server
and shared by every source/session in that site prefix. Acquire uses GET_LOCK
with a two-second wait. Timeout, NULL/error or unknown result yields no number
and no attribution upsert. Release is attempted in finally after every acquired
path, including failures/exceptions; connection close also releases native locks.
No foreign lock is released. A native release failure remains a server diagnostic
and is not proven absent by mocks. This serializes allocations at a throughput
cost; it introduces no schema constraint or migration.

SQL clones use their own prefix-derived lock, so no test intentionally contends
with the real site's lock. The harness computes that name via the actual installed
repository under the clone prefix; no legacy per-source lock is tested. Other
writers (admin number edits, GC, legacy-version requests or direct SQL) do not
cooperate with this lock. Revalidate eligibility on readback; do not claim a SQL
constraint or universal protection from arbitrary external edits. Any future
deployment must avoid overlapping old/new allocator versions in flight.

Unexpired reservations remain source-pinned even when a renewal is rejected.
Different source, inactive/deleted/changed-number/source mismatch: no returned
number, no extension/release/relabel, no attribution overwrite. Another pool
becoming largest does not invalidate an otherwise valid existing lease. After
expiry, current source/pool rules apply. Empty/exhausted pools can still capture
attribution following successful checked reads, preserving prior capture behavior.
Persistence errors never yield a claimed lease; a write may have succeeded before
an uncertain readback, so keep its reservation rather than freeing it.

## Stage S: independent real SQL connections, shadow tables only

Resolve the actual `$wpdb->prefix` as `P`; do not guess it. The only new tables:

1. `Psecb_accept_20261027_01_se_cb_tracking_numbers`
2. `Psecb_accept_20261027_01_se_cb_number_leases`

`CREATE TABLE ... LIKE` copies the two installed schemas/indexes without touching
the originals. Both clones receive table comment `cbaccept:20261027:01`. State
must match exact names, run, owner, paths and comments before writes/cleanup.
An interrupted create lacking the marker is an orphan requiring scoped operator
review; the harness refuses to guess ownership or drop it automatically.

Preflight checks exact table-name absence, original-table fixture phone/source
collisions and server-wide IS_FREE_LOCK for the clone site allocation lock. An existing
lock is a collision, not permission to release somebody else's lock. Capture
original CB counts/checksums, CB option hashes and configuration-file hashes in
restricted state. No original row values are printed/exported.

Seed exactly five rows into the **tracking clone**, with generated IDs recorded:

| Logical row | Tracking phone | Source | Pool | Active |
|---|---|---|---|---|
| S1 | +12025550101 | cbaccept_20261027_alpha | cbaccept_last | 1 |
| S2 | +12025550102 | cbaccept_20261027_beta | cbaccept_beta | 1 |
| S3 | +12025550103 | cbaccept_20261027_pool | cbaccept_large | 1 |
| S4 | +12025550104 | cbaccept_20261027_pool | cbaccept_large | 1 |
| S5 | +12025550105 | cbaccept_20261027_pool | cbaccept_small | 1 |

All forward to fictional +12025550190; recording 0; label
`cbaccept-20261027-01-N`; notes `cbaccept-20261027-01`. Other columns use existing
schema defaults. No attribution/call rows are needed for direct repository tests.

Calls through the shared runner, after approval:

```php
secb_accept_sql('seed-clones', 'RUN', true); // refuses existing state/tables
secb_accept_sql('race', 'RUN', true, 'last-slot-01');
secb_accept_sql('inspect', 'RUN', true);
// Preserve case evidence; reset only the owned clone lease table between cases.
secb_accept_sql('reset-clone-leases', 'RUN', true);
secb_accept_sql('race', 'RUN', true, 'lock-timeout-01');
// reset, then same-session-01; distinct suffixed case labels preserve evidence.
```

Wrap each requested operation in JSON output, as in the plan example. For races,
explicitly require `$result['pass'] === true` and return a nonzero WP-CLI result
on false. A worker/process exit 0 alone is never a concurrency pass. Preserve
failed-case evidence, stop further cases and run only reviewed owned cleanup.

Run last-slot-01 through last-slot-20, resetting clone leases between cases.
The supervisor starts two independent PHP/WP-CLI processes with independent
native SQL connection IDs; both must reach a filesystem barrier before release.
Each runs the **installed** NumberLeaseRepository once against the clone prefix,
then restores `$wpdb->prefix` before WP shutdown. Direct repository allocation
still uses the real GET_LOCK implementation and real installed table indexes.
Different session IDs `cbaccept-20261027-01-CASE-a/b` compete for S1. Exactly one
winner and one null, one active row and no duplicate active number are required.
Capture start times, connection IDs, durations and SQL-state assertions privately.

For lock-timeout, the supervisor holds the clone site's real allocation lock on its own
connection; both workers must return null after their GET_LOCK(...,2) waits. The
supervisor releases only its own lock in finally. For same-session, both workers
use `cbaccept-20261027-01-CASE`, with alpha and beta: investigate duplicate active
leases across sources. Require exactly one winner and one null, exactly one
active reservation, matching source/session identity and no duplicate number or
session. Repeat -01..-20; one observation alone does not prove race freedom.

Timeouts: MariaDB max_statement_time=5 for acceptance connections only (no global
setting; unsupported variable stops the run), barrier readiness 10s, worker
barrier wait 10s, core lock wait 2s,
post-barrier process completion 8s. Abort/terminate only child processes spawned
by this supervisor; graceful stop then kill after 1s if still running. SQL locks
also release on connection close. Never kill unrelated PHP or SQL sessions.
Preserve stderr privately; it may include unrelated bootstrap diagnostics.

Sequential clone follow-ups (reviewed local request PHP): load the recorded
state, call `secb_accept_own_tables($state)`, then wrap each action in
`secb_accept_in_fixture($state, function(){ ... })` so prefix always restores:

- Renew the winner on another connection; same lease ID, persisted expiry moves
  forward and no insertion. Read back the actual stored expiry, not only return.
- Set **only that clone lease's captured ID** to expires_at=UTC_TIMESTAMP()-1s,
  then allocate an independent session; old row expires/released_at is populated.
- Fill source pool with three distinct sessions: S3/S4 each once, then null;
  S5 must not be borrowed as fallback. Beta must not borrow alpha/other pools.
- Source change, number deactivation and pool edits touch captured clone IDs only.
  Require rejection without renewal or early release; preserve exact original expiry/status/source. Valid sticky leases ignore a new largest pool. After original expiry, acquisition follows the current requested source and largest pool.
- Do not inject outages/SQL privilege changes into the live database. L1/L2/L5
  failed-query behavior remains mock evidence until a separately reviewed fault
  experiment is designed. A lock timeout is a real safe connection test.

Active scheduled GC still targets original tables; it cannot sweep these clones.
Direct clone expiry tests exercise actual repository expiry, **not scheduled-GC
acceptance**. Record GC registration before/after normally; never pause it, force
cron, migrate, activate or restore global cron to make snapshots match.

Cleanup after saving results: `secb_accept_sql('cleanup-clones','RUN',true)` verifies
markers then drops only the two exact shadow tables and writes sql-after.json.
Evidence/state remains. Compare original counts/checksums/config/CB-option hashes;
any unrelated GC/background delta must be explained, never globally restored.

## Stage B: actual HTTP DNI, separate authorized browser storage sessions

This stage is separate because the public REST controller uses real CB tables,
not a per-process shadow prefix. Do not claim Stage S accepts the browser path.
The browser seed **requires all four real CB tables empty**. If any existing row
has appeared, stop for a revised isolation plan; do not delete it. This guards
normal acquire/findActive expiry SQL, which is global rather than run-scoped.
Recheck immediately before starting and monitor for unrelated traffic throughout.

Load `RUN/harness/browser-fixtures.php`; `secb_accept_browser('plan','RUN')` prints
the proposal. After new approval only, `secb_accept_browser('seed','RUN',true)`
checks state/rate-key collisions and inserts these three exact real tracking rows:

| Logical row | Tracking phone | Source | Pool | Active |
|---|---|---|---|---|
| B6 | +12025550106 | cbaccept_20261027_browser | cbaccept_browser | 1 |
| B7 | +12025550107 | cbaccept_20261027_browser_alt | cbaccept_browser_alt | 1 |
| B8 | +12025550108 | cbaccept_20261027_browser | cbaccept_browser | 0 |

All forward to +12025550190; recording 0; label
`cbaccept-20261027-01-browser-N`; notes `cbaccept-20261027-01-browser`.
Generated IDs and the empty pre-state are journaled in browser-state.json.
Sessions are exactly `cbaccept-20261027-01-browser-a` and
`cbaccept-20261027-01-browser-b`. The page expires after 3600s and requires normal
logged-in manage_options access; no account/role creation or credential fixtures.

Use **two existing independent browser storage contexts**, e.g. supported in-app
browser plus operator-controlled existing Firefox/private window, with ordinary
authorized sign-in. Two in-app tabs sharing one storage context do not count.
Do not change Firefox profiles, copy cookies, expose debug ports or install tools.
If two contexts/normal logins are unavailable, leave this stage pending.

Open the following path on the approved site URL in each respective context:

```
/secb-acceptance-20261027-01/index.php?visitor=a&utm_source=cbaccept_20261027_browser
/secb-acceptance-20261027-01/index.php?visitor=b&utm_source=cbaccept_20261027_browser
```

The page refuses unrelated existing CB localStorage/click IDs instead of replacing
them. In a clean context it seeds only its known synthetic session and source.
Use neither click IDs nor real numbers. Source is explicit through utm_source,
which the actual controller recognizes; all original/global business facts stay
untouched. Do not copy actual user-agent/IP values from private session records
to public evidence; the real controller captures those operational fields.

The page loads only the actual CB snippet directly; it does **not** call wp_head,
wp_footer or the site's frontend template. Thus other plugins' usual frontend
DNI is excluded without editing/deactivating them. Check the rendered script IDs
and requests first; unexpected competing DNI or injection is a stop condition.
Do not call this a coexistence test. Actual site-theme/other-DNI coexistence needs
a later separately reviewed, non-dialing test surface.

Telephone anchors and late nodes are inside an `inert` container, excluded from
activation, with capture-phase click prevention as defense in depth. The page
refuses to start DNI without native inert support. Results appear as plain text
outside the inert region, with no clickable dial controls. CSP permits only
same-origin fetch and no form submissions. No provider endpoint is called.

Required observations:

1. Distinct seeded session IDs in separate contexts; actual POST /track/session
   responses, only one active B6 lease. Winner inserts B6, loser gets null/pool
   exhaustion and retains the fictional forward phone. Unrelated 0199 stays put.
2. Late-node button adds an inert ordinary phone link; actual MutationObserver
   swaps only eligible nodes. No calls/clicking telephone links.
3. Observe a real automatic renewal at roughly 15 minutes (config lease=30),
   timeout 16 minutes; check persisted expiry using exact fixture lease ID.
   No global timing settings changed. Respect controller's 3s session rate key;
   wait at least 4s between manual reloads/requests. HTTP deadline 10s per request.
4. For D1 reproduction, name winner X/loser Y from actual results. Expire only X's
   captured fixture lease ID; after 4s reload Y while B8 remains inactive so Y
   receives B6. Activate only B8's recorded ID, then let X's next real renewal
   acquire B8. Compare ordinary link versus explicit marker; 0.1.2 must refresh both to B8. Then force only a local fixture-network failure
   through a separately reviewed page override, or allow an owned pool-exhaustion
   response after expiry, and require original href/text restoration including late nodes. Never edit any global number/destination or unrelated row.
5. Before original expiry, snapshot the winner's exact owned lease row and hash
   its attribution fields privately. After the 4s rate window, POST track/session
   with the same SID and alternate source plus a different synthetic campaign.
   Require tracking_number=null, unchanged original lease/expiry/source and an
   identical attribution hash (do not print request IP/user-agent or row data).
   B7 must remain unassigned to that SID. Do not accept a new lease or a relabel.
   Deactivate only its journaled B6/B8 row, then after 4s request renewal with the
   original source: null, unchanged reservation/expiry and attribution. Restore
   only that fixture row's previous active flag; it is never freed early. After
   its original expiry, a new requested source may allocate its current pool and
   update attribution. Capture response and exact owned-ID SQL readback.
6. Source/deactivation and failure-restoration follow-ups require
   reviewed single-ID changes, exact ownership checks and saved responses. Do not
   disable database access, introduce bad provider credentials or alter others'
   DNI. Query/HTTP-error injection remains local evidence, not native acceptance.

GC stays active and may expire fixture leases/purge expired fixture sessions.
Observe/correlate timestamps and treat legitimately expired rows as expected;
never disable GC or reset timestamps on unrelated rows. Stop on any unexpected
nonfixture caller/call/session/number, source collision, SQL read error or scope
conflict. No call rows are expected, and this harness never deletes call records.

## Browser cleanup and before/after closure

1. Stop test activity. In **each** context use the fixture's own-storage cleanup
   button, then close its page/context to cancel renewal timers. It removes only
   secb_attribution with the exact approved synthetic session ID; no cookies,
   other storage or browser profiles are cleared. Do not run server cleanup while
   an old fixture page can still renew.
2. Call `secb_accept_browser('inspect','RUN',true)` and save private evidence.
   Call cleanup once: `secb_accept_browser('cleanup','RUN',true)`.
   It validates IDs+phone/source/pool/notes/label ownership, refuses foreign lease
   references, deletes only owned leases/session IDs and the three journaled
   numbers, and removes only `secb_rl_`+md5(owned SID) transient/cache keys.
   It preserves all unrelated rows/options/settings and never touches calls.
3. Preserve before/after checksums, counts, config hashes, options and GC evidence.
   Expected original empty tables return to empty. Auto-increment gaps are
   expected and must not be reset. No database/global options/cron restore.
4. Remove only the recorded temporary index.php after matching its copied hash;
   remove its dedicated directory only if empty. Retain private run evidence and
   harnesses. Verify page removal (404) **without utm_source or stored fixture
   context** so a WordPress fallback cannot create new attribution rows. Close
   that check tab and take the final counts after removal.
5. On any ownership/conflict check failure, preserve evidence and report exact
   remaining rows/files for scoped operator cleanup; never broaden DELETE/DROP.
   A state/table mismatch must not be "fixed" by overwriting it.

No successful local/mock/shadow-table result constitutes telephony or consent
acceptance. Authenticated non-administrator denial, installed production-theme
DNI, valid/invalid signed callbacks, live forwarding and secured playback remain
pending. Recommended next assignment: review the 0.1.2 candidate and this proposal, then separately authorize deployment and the bounded native SQL stage. Browser fixture writes require their own reviewed stage.
