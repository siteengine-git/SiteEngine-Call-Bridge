# Lease/DNI local findings — test tooling only

Runtime under test: f763b3cd4ce1e73416a73c5802b35d88469571ab, release 0.1.1,
DB 1.0.0. No runtime file or package changed. New tests intentionally retain red
contracts; they must not be reported as an accepted suite.

## Executed results

| Execution | Result | What it establishes |
|---|---|---|
| Actual PHP repositories, local PHP 8.5.12 `-n` | 29 pass / 5 fail, exit 1 | Application control flow against explicit fake wpdb/clock/locks; no SQL server |
| Actual extracted DNI JS, Node VM | 25 pass / 1 fail, exit 1 | DOM/fetch/storage/clock doubles; no browser or network |
| Actual DNI JS, supported in-app browser | 8 pass / 1 fail | Real DOM/MutationObserver in local iframes; fixture fetch/storage/timers, no live backend |
| New PHP lint | 4 files pass | Test plus three prepared acceptance PHP files, local PHP 8.5.12 only |

Repository tests cover distinct sessions competing for one number, a modelled
interleaving while a source lock is held, exhaustion, pool sizing/standalone
selection, renewal/expiry, lock release including exceptions, source edits,
deactivation, failed reads/writes and modelled lock timeout. SQL text is handled
by an explicit narrow fake; unmodelled queries fail the harness. This is not a
database lock/isolation test and cannot prove real SQL exclusivity.

DNI cases cover no attribution, malformed/expired/unavailable storage, stored
sessions, eligible insertion, unrelated links, non-phone labels, same-number
renewal, failed/exhausted allocation, HTTP/JSON/network failures, restoration,
anti-flicker on stalled requests and late nodes. The real-browser scenarios are
no-attribution, insertion/unrelated links, unavailable storage, malformed storage,
allocation network failure, stalled network, renewal failure, late nodes and the
replacement-number regression. Storage denial and HTTP behavior are injected,
not actual browser policy denial or server responses. Timers are driven rather
than waiting fifteen minutes. No separate-session storage isolation was accepted.

## Reproduced failures and proposed separate fixes

| ID | Reproducer and actual consequence | Cause / proposed direction, not implemented |
|---|---|---|
| L1 | Acquire, advance 60s, make renewal update return false. Repository returns a lease with a new expiry that was never persisted. | Renewal ignores update result. Return a safe failure unless a checked write/readback establishes renewal. |
| L2 | A owns the only number; B's used-number read returns an error/empty column result. B inserts another active lease for A's number. | Error is treated as empty availability. Distinguish a successful empty result from wpdb failure and fail closed; real SQL validation still required. |
| L3 | Acquire under alpha, request beta for the same session. Old alpha lease is renewed/returned. | Existing-session fast path ignores requested source. Define and enforce source transition/release policy under appropriate locks. |
| L4 | Acquire, deactivate that number, renew. Inactive number is still returned. | Fast path skips current number validity. Revalidate active/source/pool policy before renewal; retire stale ownership safely. |
| L5 | Existing-session read fails while two numbers exist. Same session receives a second active lease. | Read error appears to mean no lease. Fail closed on read error; decide whether session-wide serialization/constraints are also needed across sources. |
| D1 | Initial response gives 0101; successful renewal gives 0102. Explicit marker updates to 0102 but ordinary business link still points to 0101. Reproduced in both VM and real browser. | Eligibility checks the now-swapped href against original forwarding numbers. Include remembered original href/already-owned swapped elements when refreshing; retain unrelated-link guards. |

Replacement on renewal is a meaningful case: a delayed/backgrounded browser can
renew after expiry and receive another available number while the old number is
leased to someone else. The browser reproduction supplies those two successful
responses; it does not claim the database transition itself was executed.

Pool-edit characterization: an existing lease remains sticky after a different
pool becomes largest, but a new allocation after expiry follows that new pool.
This requires a policy decision; it is not silently classified as a defect.
Source-wide locks also do not serialize a shared session across two sources;
the prepared real two-connection same-session case investigates this possibility,
not an already-demonstrated native SQL race.

## Reproduction

Use the existing local PHP executable; install nothing:

```powershell
./tests/run-lease-dni.ps1 -Php <existing-php.exe>
# Exit 1 is expected on the pinned runtime: six preserved failing contracts.
node tests/dni/browser-server.cjs
# Open its printed loopback URL in the supported in-app browser.
# Expected: 9/9 complete, 8 PASS / 1 FAIL (replacement-renewal).
```

The local browser server binds only 127.0.0.1, blocks real connect requests with
CSP, serves no repository directory listing and stops after fifteen minutes.
All phones are fictional +1 202-555-01xx. Test links are inert/non-dialing. No
Twilio credentials, provider requests, accounts or real phone numbers are used.
Raw results/operational evidence stay in ignored `.secrets/lease-dni/`; this
document contains only sanitized local findings. The local test tab was closed,
server stopped and the authorized administration tab preserved.

## Acceptance carried forward

Installed administrator layout checks are completed on f763b3c: deployed file
identity, changed-file PHP 8.3 lint, real desktop/narrow URL readability, exact
selection, Tab focus/arrow scrolling and navigation passed, with unchanged CB
configuration/data. Those are historical installed results, not rerun here.
Authenticated non-administrator denial, real SQL concurrency, installed browser
DNI, signed callbacks, live forwarding and secured playback remain pending.
Consent linkage remains a separate implementation gap. No release promotion.
