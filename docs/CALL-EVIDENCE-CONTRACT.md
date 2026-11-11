# CB call-evidence contract v1

This is a CB-owned, read-only same-site PHP boundary for trusted integrations.
It is not an AB API, a REST endpoint, an exporter or a new credential facility.
The current successor candidate is 0.1.6 / DB 1.0.0; the 0.1.5 evidence API is unchanged. Accepted installed development evidence remains
0.1.4 at `ccc7d96f6c9cbc8703fac34032aa2915c2ce3a1f`; this interface has not been
installed or verified with AB.

## Invocation and access

After the active CB plugin has registered its autoloader, on the correct WordPress
site/blog database context:

```php
use SiteEngine\CallBridge\Integration\CallEvidence;

if (class_exists(CallEvidence::class)) {
    $evidence = CallEvidence::read($canonicalCallIdentity);
}
```

`CallEvidence::CONTRACT` is `siteengine.call-evidence.v1`.
Input is exactly `twilio:CA` followed by 32 hexadecimal characters. The persisted
`transaction_id` and `twilio_call_sid` must match the requested identity exactly;
the interface never repairs, aliases or substitutes an inquiry identifier.
The existing current `$wpdb->prefix` scopes the lookup. No cross-site access or
remote transport is added. AB's exact callable wrapper, if different, can be
adapted at this boundary once its real contract is supplied.

This is trusted PHP code with the site's existing database authority, not a
capability-checking network service. AB must authorize its own caller before
exposing evidence in any action, UI, job or endpoint. Do not expose this function
through an unauthenticated REST wrapper or enable arbitrary call enumeration.
Treat click IDs, session IDs, landing-page URLs and number/forwarding snapshots
as private operational evidence. Redact public receipts and escape output at its
rendering boundary. Never log raw rows or automatically forward them to Google.

## Envelope and semantics

Every response has `contract`, `status`, `call` and `unavailable`. Status is one of:

| Status | Meaning |
| --- | --- |
| `found` | An identity-consistent persisted call snapshot is returned. |
| `invalid_identity` | Unsupported identity syntax; no SQL is executed. |
| `not_found` | The SELECT succeeded but no row exists. |
| `storage_error` | Reading failed; no call, SQL or exception details are exposed. |
| `identity_conflict` | Persisted identity disagrees; no call is returned. |

For non-`found` results `call` is null and `unavailable` is empty. This is a retrieval
result, never an advertising eligibility decision. Consumers must check `status`.
Reads are not cached; fresh reads reflect subsequently persisted lifecycle state.

The `found` call object contains:

| Group | Persisted fields returned |
| --- | --- |
| Identity | `identity` (transaction_id), `provider`, `provider_call_sid`, `record_id` |
| Timestamps | Raw `created_at`, `updated_at`; timezone is unavailable because it is not snapshotted on the call. |
| `acquisition` | `matched_session_id`, `gclid`, `gbraid`, `wbraid`, `fbclid`, `msclkid`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `landing_page` |
| `number_assignment` | Original `tracking_number_id`, `tracking_number`, `forwarded_to`, `source` copied onto the call. This `source` is distinct from acquisition `utm_source`. |
| `outcome` | Raw `status`, integer/null `duration_sec`, persisted `technical_qualification`, integer/null `is_repeat_caller` (0/1), `prior_qualifying_calls` |
| Consent reference | Raw `consent_receipt_id` if present; it does not establish authoritative consent linkage. |

Missing/empty persisted values are null with a dotted-field reason in
`unavailable`. Numeric zero remains zero, not missing. No caller contact details,
notes, recording identifiers/URLs, media access, credentials or headers are read
by the evidence SELECT or returned. The caller-facing `tracking_number` and
`forwarded_to` are intentionally distinct phone roles, not a public business-phone
sync. Assignment and acquisition always come from this call row; the query does
not join sessions, leases or current number settings.

The following fields are explicitly null with reasons:

- `number_assignment.lease_id`, `pool_group`, `leased_at`, `expires_at`:
  `not_snapshotted_on_call`.
- `timestamp_timezone`, `outcome.qualification_threshold_sec`:
  `not_snapshotted_on_call`; do not reconstruct from current settings.
- `authoritative_consent_linkage`, `durable_child_leg_mapping`: `not_implemented`.
- `inquiry_identity`, `business_qualification`, `advertising_eligibility`:
  `owned_by_ab_not_asserted_by_cb`.

`matched_session_id` is evidence of the persisted association at call ingestion,
not proof of a still-live reservation. No claim is made that the original pool,
lease timestamps or browser consent are available. CB currently cannot prove
durable child-leg mapping. Its existing first-successfully-persisted terminal /
first-completed-recording retention is bounded protection, not full conflicting
provider-event reconciliation. No callback behavior is changed by this candidate.

## Ownership of downstream outcomes

**AB owns all Google eligibility, conversion identity, deduplication and delivery
for form- and call-origin outcomes. CB supplies supported call evidence and does
not implement a Google exporter.** CB identity remains `twilio:{CallSid}`; AB's
durable inquiry/outcome identity is a separate AB concern. No Fluent Forms entry,
`ff:` identity, inquiry relationship, business outcome or consent is synthesized.

Attribution, technical duration qualification, business qualification and
advertising eligibility are separate facts. A technically `qualified` call is not
necessarily a qualified lead, won job, consented user or eligible conversion.
AB determines advertising holds when evidence is absent. CB does not infer a
Google conversion time/value, eligibility or delivery result. Call-origin Google
delivery remains pending unless AB actually implements and verifies it.

## Reproducible local demonstration

Use existing local PHP and its existing SQLite extension; install nothing.
From the canonical CB project in PowerShell:

```powershell
$php = 'D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Base\.local-tools\php\php.exe'
$sqlite = 'D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Base\.local-tools\php\ext\php_sqlite3.dll'
& $php -n tests/call-evidence.php $sqlite
```

The test creates one labelled synthetic call through the shipped repository in
a random local temporary SQLite database. It uses only reserved fictional
numbers, `.invalid` URLs, synthetic acquisition and an all-zero fixture CallSid.
It does not create a form entry, consent receipt or real provider account.
Recording is off. A trusted SQLite/wpdb adapter executes real local SQL; minimal
WordPress stubs supply the controller environment. An independently calculated
fixture HMAC authenticates the actual status controller in child processes.

Fresh PHP processes read the **same persisted call** through
`CallEvidence::read()`, first pending and then completed/qualified. Applicable
duplicate completed, late ringing and conflicting busy notifications preserve
identity, original acquisition/assignment and the accepted terminal outcome.
Changing current number source/pool/activation cannot change its evidence. Reads
use a read-only SQLite connection and leave database bytes unchanged. Missing,
invalid, injected read-error and identity-conflict cases are distinguished.
The negative `ff:` input is rejected without persistence; no form identity is
created. The single fixture/database and receipts are removed by the test's
`finally` block; cleanup owner is CB's local test runner.

SQLite is real local persistence, not a MariaDB/WordPress installation proof.
The wpdb/WordPress adapter and injected error are labelled test doubles. No
server, real provider, live call, media, installed AB or Google delivery test is
implied. Reuse retained 0.1.4 native callback acceptance; do not repeat Stage S,
full DNI, permission or synthetic callback suites for this boundary.

## Integration-site handoff (not executed)

TO: AB Helper / assigned installation owner, through Aubrey
FROM: Call Bridge Codex
SUBJECT: Supply exact callable and installation identity for call-evidence v1

Needed: AB's exact callable/consumer shape and version, assigned installer/executor,
and exact integration site/profile/plugin identity. No AB API is assumed. AB owns
its durable outcome interface and integration installation; AB source must not be
modified by CB and AB code must not write CB tables.

Once known, adapt only this narrow consumer boundary and coordinate the candidate
installation under the established shared ownership procedure. Any site fixture
requires a recorded bounded CB-owned writer, run marker, collision-checked exact
CallSid/row ID, before-state and cleanup ledger. The assigned installation owner
coordinates execution; AB reads only through the supported interface. Do not
borrow client numbers, provision test credentials, change callbacks or initiate
calls merely to demonstrate evidence. A persisted synthetic snapshot suffices.

Use one labelled owned synthetic row, recording off, read it through the actual
installed callable in separate requests/processes, compare original acquisition
and accepted lifecycle state, and inspect exact row counts. Delete only the owned
row after reference/ownership checks; if AB retains a durable relationship, agree
cleanup/retention rather than orphaning it. Report installed verification and
retained IDs/cleanup owner separately. No integration-site fixtures or controls
exist from this local assignment, and this handoff document is not proof of
delivery to another chat.
