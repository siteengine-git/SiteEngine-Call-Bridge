# Call Bridge working rules

Read D:\SiteEngine\AGENTS.md and D:\SiteEngine\Agency\AI-Workspace\START-HERE.md, then local STATUS.md before work. STATUS.md is deliberately local-only because it includes environment inventory; preserve it and the original handoffs/ZIP.

Canonical source is this existing project root, D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Call Bridge. Do not create another editable source home. One active writer. Alma is the development target; server access alone does not authorize writes. Use the task-specific alma-agent route and site owner/runtime recorded in STATUS.md. Preserve remote history and the original installable ZIP.

Neutral retained baseline: release 0.1.0, database 1.0.0. Baseline import is not installed/live acceptance. Run node tools/verify-baseline.cjs for package/source integrity and DNI syntax; it does not perform PHP or live acceptance. Preserve exact package bytes and RELEASE-MANIFEST.sha256. Review explicitly staged paths and secrets before every checkpoint/push. No blanket staging, force-push or visibility changes.

Before WordPress/WP-CLI/HTTP diagnostics inspect bootstrap effects: Plugin::boot calls Installer::maybeUpgrade, which can create/update tables, update se_cb_db_version and schedule GC even with a current version. GET /track/number expires leases. Do not classify these probes as read-only automatically. Never expose credentials, caller data, recordings or operational diagnostics in public Git.

Preserve canonical identity twilio:{CallSid}, signature validation, routing, exclusive leases and fail-safe forwarding. Do not add form Ads inquiry adapters, CRM workflows or call export. Do not edit other plugins. Consent linkage and live DNI/Twilio/playback acceptance remain pending. Base setup/status/help and workflow UI is a separate proposed tranche, requiring its own concrete assignment.
