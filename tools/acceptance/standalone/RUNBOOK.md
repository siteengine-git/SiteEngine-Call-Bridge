# Standalone candidate Stage S ? REVIEW PROPOSAL, NO SERVER EXECUTION

This intentionally replaces Stage S's installed-version dependency. Website
0.1.2 deployment remains paused at its separate quiescence/drain gate. Nothing
here grants staging/native execution approval. No browser Stage B, original-table
fixtures, provider work, calls, media, infrastructure or credential migration.

## Immutable inputs and execution boundary

Runtime commit: 0f39cebc7d521e02f686be2ee6016716bf738e03, release 0.1.2 / DB 1.0.0.
ZIP SHA-256: 6b240e1b7633cac208ad08834df76dc9f7d741168884560b8e0aae5ce161c5de.
Runtime manifest SHA-256: a8135e0e227eeee68f7cb436b9224ed2d918ad72e18b652ca1b361336e9ec977.
The original ZIP is neither rebuilt nor edited. All 51 extracted files must equal
its checked CRC/size/payload bytes, including all 50 manifest entries. No extras,
symlinks, changed/missing payloads or reused extraction directory are accepted.
Only Config, TrackingNumberRepository and NumberLeaseRepository are required.
Candidate bootstrap/header, Installer, Plugin, controllers and hook registration
are never included/invoked. All other CB classes, existing CB classes/autoloaders,
WP bootstrap APIs/files and PHP auto-prepend/append settings cause rejection.
Reflection binds each of the three loaded classes to the exact verified private
file/hash in supervisor AND both workers. Ready/result origin records must match.

This is a fresh PHP CLI process, NOT wp eval, SHORTINIT, wp-load, or skip-plugins.
No WordPress application bootstrap means no MU/drop-in/plugin/cron hooks execute.
The runner inventories wp-config/wp-settings, root MU PHP files and cache/DB/sunrise
drop-ins by hash, and counts include/require/eval tokens without executing them.
Audit JSON includes a deterministic review_sha256. Writes require an independently
reviewed matching --audit-pin and --execute. An inventory pin is a guard, NOT
operator authorization. Changed inventory stops. db.php/sunrise/custom content
routing requires further design review; do not override the gate during acceptance.

DB credentials and table_prefix are parsed from unique unconditional top-level
literal wp-config declarations in memory only. No eval, include, credentials in
argv/evidence, credential copy, permanent settings or new credential facility.
Dynamic/conditional/duplicate or complex literal config stops for review. The
existing SECB_CONFIG_FILE path must be the expected external file and readable;
its contents are NOT executed or copied. SQL experiment lease timing is explicitly
30 minutes; this does not inspect or certify effective website configuration.

The global wpdb-shaped class is a small acceptance-owned mysqli adapter, NOT WP's
wpdb. It implements only the methods used by these pinned repositories/harness.
Native adapter/mysqli/MariaDB compatibility remains unverified. Host syntax supports
hostname with optional numeric port; socket/IPv6/custom DB routing needs review.
No new privileges or fallback connection method. Session SQL bound is 5 seconds.
SQL errors expose generic messages, not SQL/credentials. Candidate backtraces require
an explicit clone scope. That scope validates the exact prefix/two tables and
permits only those tables and their prefix-derived lock; original tables/options
are supervisor-read-only. No multistatements, foreign tables/locks or global SQL.

## Proposed private staging ? after explicit server authorization only

Use existing shared profile and local STATUS owner/root/runtime. No new connection
script, key, connector, sudo grant or filesystem ACL change. Shared preflight first.
Direct owner-shell invocation is needed because Invoke-Site-Wp always bootstraps
WordPress and is therefore not this standalone runner. Preserve existing SSH strict
host-key and BatchMode settings; use the established owner PHP execution route.

SITE = registered WordPress root. OWNER_HOME = dirname(SITE).
RUN = OWNER_HOME/.secb-acceptance/cb-lease-dni-012-standalone-20261028-01
Owner/group = existing site owner/group. All directories 0700, files 0600.
Any existing RUN or target path: stop, preserve and review; never reseed/overwrite.

1. Create RUN and RUN/harness outside SITE after checking resolved paths/owner.
2. Stage ONLY the nine entries in this directory's STAGE-S-MANIFEST.sha256 into
   RUN/harness, plus the manifest itself. Verify its independently delivered
   SHA-256 BEFORE including any tooling, then every listed digest. Stage the
   unchanged candidate ZIP as RUN/candidate.zip and independently check its pin.
   Do not stage tests, historical harnesses, browser fixtures or operational docs.
3. Invoke runner prepare once. It verifies the ZIP BEFORE extracting the exact
   51-file artifact to RUN/candidate/siteengine-call-bridge; all restrictive modes
   checked. This is a temporary immutable test artifact, not an editable source
   home, plugin deployment, registration, activation or application boot.
4. Invoke audit, preserve the JSON privately, review the indicated files/effects
   without printing secrets, and record its review_sha256. If bootstrap/custom
   routing is unexpected, stop for Helper review. No SQL is opened by prepare/audit.
5. Lint ALL 47 packaged
   PHP files plus the eight staged PHP tooling files using the existing owner PHP
   8.3 executable before SQL work. Lint does not boot them. Stop on any failure.
6. Run plan, then the execution sequence below. Independent processes each verify
   package, tooling, audit and class origins before their own native connection.

The invocation shape, as the established site owner (not WP-CLI), is:

```text
PHP -d auto_prepend_file= -d auto_append_file= -d opcache.enable_cli=0 \
  RUN/harness/runner.php MODE RUN SITE TOOLING_MANIFEST_SHA256 CASE \
  --execute --audit-pin=REVIEWED_AUDIT_SHA256
```

For prepare/audit/plan omit CASE, --execute and --audit-pin. Runtime entry requires
Linux, fresh CLI state and owner-only tree. PHP normal extensions must include
mysqli, posix and zlib; do not use -n if that removes required Alma extensions.
No permanent PHP/OPcache configuration changes. Worker argv uses this same PHP
binary and flags, its own fresh runner and same independent tooling/audit pins.
Case names/modes/run/site are validated; never put credentials in argv.

## SQL scope, exact fixtures and assertions

Let P be the parsed existing root table prefix. Exactly two shadows:
Psecb_accept_20261027_01_se_cb_tracking_numbers
Psecb_accept_20261027_01_se_cb_number_leases
Clone schemas with CREATE TABLE LIKE, marker comment cbaccept:20261027:01.
Stop if either exists, state exists, a fixture phone/source collides with original
tracking rows, or the clone allocation lock is held. Actual generated IDs are
journaled. Clone prefix must fit native table-name limits; no schema alteration.

Five rows in the tracking clone only, all forward +12025550190, record_calls=0,
is_active=1, notes cbaccept-20261027-01, label cbaccept-20261027-01-N:

| N | Fictional tracking phone | Source | Pool |
|---|---|---|---|
| 1 | +12025550101 | cbaccept_20261027_alpha | cbaccept_last |
| 2 | +12025550102 | cbaccept_20261027_beta | cbaccept_beta |
| 3 | +12025550103 | cbaccept_20261027_pool | cbaccept_large |
| 4 | +12025550104 | cbaccept_20261027_pool | cbaccept_large |
| 5 | +12025550105 | cbaccept_20261027_pool | cbaccept_small |

No attribution/call rows, original-table fixtures or HTTP requests. These are
reserved fictional database strings; never dial or pass them to a provider.

After plan, invoke the exact modes (each as a separate bounded owner process):

```text
seed-clones
for kind = last-slot, same-session; round = 01..20:
    reset-clone-leases
    race kind-round
reset-clone-leases
race lock-timeout-01
for case = renewal, expiry, pool, source, deactivation, sticky:
    reset-clone-leases
    sequential case
inspect
cleanup-clones
```

Every nonzero exit or pass!==true stops further cases. No automatic retry over an
existing case/run, no enlarged timeouts or assertions changed during acceptance.
The six sequential cases retain the reviewed executable tests: independent renewal
with checked persisted expiry, captured-ID expiry/reacquisition, largest-pool and
source exhaustion, source rejection until expiry, deactivation retaining reservation,
and sticky lease/current-largest-pool transition after expiry. All edits are to
captured clone IDs; restore only owned fixture flags/pool fields with readback.
This direct repository stage does NOT accept controller attribution or browser DNI.

The prior pure verdict and process implementations are byte-identical copies.
Each race retains two independent native connection IDs, ready/go barrier, complete
worker records, matching ready/result/class origins, known exit 0, exact persisted
winner/status/expiry/fixture membership, duplicate checks, and open-connection
GET_LOCK/RELEASE_LOCK probe from an independent supervisor. Workers remain open
until finish; disappearance cannot hide an application lock leak. Timeout case
holds only the supervisor-owned clone lock and requires null/null, zero total/
active lease rows and actual bounded wait. Lock is secb_alloc_ + first 40 SHA-1
hex of DB_NAME + '|' + P + secb_accept_20261027_01_. No real-site lock is used.

Unchanged bounds: session statement 5s, ready/go wait 10s, allocator wait 2s,
post-go result/finish/exit deadline 8s, worker finish wait 10s; own-child termination
1s grace + 1s observation; 1MiB stdout/stderr and 64KiB JSON; sequential 30s.
No universal timeout guarantee for OS/kernel failures. Unknown cessation preserves
state and refuses reset/drop. Never kill another worker or fabricate receipts.

## Shutdown, cleanup and preservation

Each candidate scope restores its prior prefix in finally and clears its SQL
scope. Sequential peer scopes restore the original adapter object/prefix, then
close only the peer connection. The entry restores/unsets its temporary global
wpdb before exit and clears its private configuration/context globals; native
shutdown safety restoration repeats this without SQL.
No WP shutdown hook, installed GC handler or candidate boot is registered.
Constants are process-local and cannot migrate into website configuration.

Reset/drop retains operation.lock, exact state/table/comment checks and closed-
worker evidence guards. On a failure preserve private stdout/stderr/evidence; run
only ownership-verified cleanup when worker cessation is confirmed. Unknown
cessation requires a scoped operator recovery review. Cleanup drops only the two
exact marked shadows, verifies absence and preserves all evidence/packaged artifacts.
No recursive cleanup, fixture-ID guessing, original-table deletes, whole-site
restore, cache flush, GC changes or auto-increment reset.

Before/after snapshots read original four CB counts/checksums, CB option hashes,
wp-config/external-file hashes and the GC event directly from DB. The adapter's
wp_get_scheduled_event-shaped helper is a read-only serialized-cron decoder, not
WordPress and not a cron runner. Independent active website GC may legitimately
change original tables/scheduling; explain deltas, never restore old global state.
Audit/payload changes or unknown ownership stop acceptance. Stage S does not
prove scheduled GC, live website integration, installed wpdb/drop-in compatibility,
DNI, consent, signed callbacks, forwarding, recording or playback acceptance.

## Current local validation / next gate

Local tests use exact extracted candidate files, reflection and fresh PHP children;
SQL guard responses are tested without connecting to a database. Historical strict
process/SQL-verdict tests remain preserved and run separately. Linux private modes,
real mysqli semantics, native MariaDB connections/locks, PROCESSLIST visibility and
entry execution have NOT been exercised. Local success is not native acceptance.

Next: Helper review of this tooling checkpoint/staging hash and direct-DB adapter
boundary, then a separate authorization to stage, audit and execute standalone S.
Website deployment remains independently paused; no web drain is needed to test
private clone-only standalone code, but its deployment gate is not bypassed.
