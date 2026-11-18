# Native standalone Stage S - completed November 3, 2026

Runtime pin0f39cebc7d521e02f686be2ee6016716bf738e03, release0.1.2 / DB1.0.0,
original ZIP and runtime manifest unchanged. No runtime source edit, website
installation, main promotion or release occurred in this task.

Starting tooling06b7bc533f7b215cd5469a5f426a97ac0a720425; corrected executable
toolingc536d05a2f8d5d4aa1dd15d7e965a4dd085a37a1. Staging-manifest SHA-256:
cf52b7e05e97aacb44f50b4b55d5cee5661c931f53f2ba0cdd836c61b502a1d9
All prior checkpoints, failed attempts and evidence preserved.

## Actual results

Existing PHP8.3.36 CLI/mysqlnd8.3.36 and MariaDB10.11.19; fresh standalone PHP
processes, direct pinned candidate repositories and minimal acceptance-owned mysqli
adapter. No WordPress/plugin bootstrap, activation, Installer or application hooks.
Class paths/hashes verified in supervisor and every worker.

| Native case | Required/completed | Outcome |
| Last-slot contention | 20/20 | One persisted/returned winner; no duplicate ownership |
| Same-session cross-source contention | 20/20 | One source-pinned winner; no relabel |
| Bounded clone-lock timeout | 1/1 | Null/null, no lease rows; approximately2.001s per worker |
| Renewal | 1/1 | PASS,6 assertions |
| Expiry/reacquisition | 1/1 | PASS,4 assertions |
| Pool/exhaustion/source isolation | 1/1 | PASS,6 assertions |
| Source transition | 1/1 | PASS,5 assertions |
| Deactivation | 1/1 | PASS,4 assertions |
| Sticky lease/current largest pool | 1/1 | PASS,5 assertions |

All41 races have strict identity/persistence/verdict checks, independent supervisor
connections,82 distinct worker connections,82 known clean exits, and independent
acquire/release probes while worker connections are demonstrably open. Sequential
cases add30 assertions and17 open-connection lock probes. No missing/uncertain
evidence was accepted. No native allocator assertion failed.

All47 packaged and eight tooling PHP lint PASS; affected loader/runner relinted
after correction. Local PHP8.5.12 standalone suite62 PASS/0 FAIL. Prior SQL-gate
103/103 and historical-tooling67/67 evidence retained, not rerun as native tests.

## Bounded tooling corrections

- Private tree uses execution effective group, rather than the unrelated public
  document-root group. Exact owner/mode protections remain; no access change.
- Literal config parser accepts an unconditional declaration after a completed
  top-level block. New positive plus nested/else rejection tests; no config eval.
- One diagnostic-file mode error was rejected by the existing guard and tightened
  on that owned file. First failure retained. All issues preceded DB connection.

Only two collision-checked, marked shadow tables and five fictional number rows
were used. Ownership/worker-cessation cleanup verified both tables absent. Original
four table snapshots, option hashes, config hashes and GC registration matched
exactly. No GC execution/scheduler-health claim. No matching workers or active
controls remain. Private evidence/staging intentionally retained by task executor.
Operational receipts, IDs, configuration inventories and detailed deployment
procedure remain local/private, excluded from publication.

## Limits / next action

Native allocator behavior through this adapter is verified. Installed WordPress
wpdb/drop-ins, controller attribution, browser DNI, consent, authenticated non-admin
access, signed callbacks, telephony and playback remain independent acceptance.
Base CRED-1 is separately deferred. Website remains the previous installed version;
this run is not website deployment or client-production release.

The executable runbook at the pinned tooling commit retains its historical review
wording; this result records the subsequently authorized native execution. Next:
Helper review of completed Milestone1 and bounded infrastructure disposition for
Milestone2's actual allocator ingress fence, old-request drain and web PHP cache.
No deployment procedure was executed or infrastructure privileges expanded.
