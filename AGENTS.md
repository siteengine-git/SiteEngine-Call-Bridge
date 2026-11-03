<!-- SITEENGINE-ROLES-AUTHORITY-BEGIN -->
## Roles and authority

Effective November 2, 2026. This section explicitly supersedes conflicting older workflow, ownership, helper-role, retry-approval and deployment-executor instructions in this file and historical project documents. Preserve historical evidence and all nonconflicting technical, privacy and access requirements. Later explicit task authorizations remain applicable; this governance update itself authorizes no deployment or real delivery.

- Controlling Helper: _(CB) Calls-Helper - 10.
- Canonical source / working home: D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Call Bridge.
- Permitted server role: Plugin source/build/test owner. No independent shared-server changes; Alma Codex installs/tests unless Master explicitly delegates a bounded server task.
- Aubrey remains final authority. The Master server chat sets priorities, owns shared-server coordination and decides server/resource conflicts. Each named plugin Helper owns its product requirements, implementation decisions, acceptance criteria and delivery; its dedicated Codex owns its canonical source, tests, GitHub changes and packages. A coordination request does not transfer plugin ownership to Master or Alma Codex.
- Shared Project context is reference material, not delegated execution authority. Treat each chat according to its name. Return plugin-source corrections to its owning Helper/Codex; do not create a competing source master.
- Plugin Helpers continue independent authorized implementation while awaiting a server slot. Existing authorization covers routine invocation and test-tool corrections within scope; inspect actual state before retrying, never blindly replay uncertain writes, and do not create repeated approval loops.
- Only one executor changes overlapping server resources at a time. Alma Codex owns assigned dev-server installation, backups, scoped controls, installed verification and cleanup; other execution requires an explicit bounded delegation. AWS production operations require their own concrete scope.
- Preserve completed evidence. Expand testing only for a demonstrated failure or concrete unresolved requirement. Diagnose first and use the smallest adequate correction; do not start another general tooling or infrastructure campaign.
- Messages intended for another chat start with TO / FROM / SUBJECT. Preparing a handoff is not delivery or acknowledgment. Do not claim another chat received or acted on a message without evidence.
- Report implemented, packaged, installed, verified and released separately; none implies the next. PC/GitHub remain source authority. The live Alma dev site is the continuing test/integration destination; keep its approved verified plugin versions current under assigned installation scope, without inferring client-production release.
<!-- SITEENGINE-ROLES-AUTHORITY-END -->
# Call Bridge working rules

Read D:\SiteEngine\AGENTS.md and D:\SiteEngine\Agency\AI-Workspace\START-HERE.md, then local STATUS.md before work. STATUS.md is deliberately local-only because it includes environment inventory; preserve it and the original handoffs/ZIP.

Canonical source is this existing project root, D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Call Bridge. Do not create another editable source home. One active writer. Alma is the development target; server access alone does not authorize writes. Use the task-specific alma-agent route and site owner/runtime recorded in STATUS.md. Preserve remote history and the original installable ZIP.

Neutral retained baseline: release 0.1.0, database 1.0.0. Baseline import is not installed/live acceptance. Run node tools/verify-baseline.cjs for package/source integrity and DNI syntax; it does not perform PHP or live acceptance. Preserve exact package bytes and RELEASE-MANIFEST.sha256. Review explicitly staged paths and secrets before every checkpoint/push. No blanket staging, force-push or visibility changes.

Before WordPress/WP-CLI/HTTP diagnostics inspect bootstrap effects: Plugin::boot calls Installer::maybeUpgrade, which can create/update tables, update se_cb_db_version and schedule GC even with a current version. GET /track/number expires leases. Do not classify these probes as read-only automatically. Never expose credentials, caller data, recordings or operational diagnostics in public Git.

Preserve canonical identity twilio:{CallSid}, signature validation, routing, exclusive leases and fail-safe forwarding. Do not add form Ads inquiry adapters, CRM workflows or call export. Do not edit other plugins. Consent linkage and live DNI/Twilio/playback acceptance remain pending. Base setup/status/help and workflow UI is a separate proposed tranche, requiring its own concrete assignment.

## Milestone 1 explicit execution delegation - November 3, 2026

Aubrey relayed _(CB) Calls-Helper - 10's Execute Milestone 1 assignment. It
explicitly delegates native standalone Stage S to this Call Bridge Codex, including
its two owned shadow tables, private staging/evidence, bounded worker processes
and ownership-verified cleanup. This supersedes conflicting older Alma plugin-
approval or deployment-executor gates for THIS test run. Call Bridge Helper owns
plugin requirements, implementation, acceptance and release decisions; Alma
infrastructure is not a plugin approval gate. Aubrey remains final authority.
Routine invocation/test-tool corrections within the assigned isolation boundary
are authorized after actual-state reconciliation, with preserved failure evidence
and regenerated/checkpointed tooling pins. No blind write replay or relaxed tests.
Only one executor may mutate overlapping resources. No website deployment, shared
service/access change, original-table fixtures, provider actions or runtime repair
is authorized by this delegation. Retain all nonconflicting privacy/access rules.
