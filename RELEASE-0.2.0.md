# Call Bridge0.2.0 / Gateway0.1.0 — central routing review candidate

DB1.1.0 unchanged. Stable0.1.9 remains current delivery; no production gateway or
Climatrol deployment. Plugin and gateway are distinct packages. See gateway/README.md
for exact private configuration,wire contract,continuity/failure policies,retention,
rollback and production infrastructure requirements.

The recording release0.1.9 was already published/pushed/delivered. The latest CB
Helper handoff relays confirmation that both recorded channels are now accepted.
Carry that operator confirmation as later acceptance evidence; retain earlier
inconclusive saved-file measurements without rewriting or repeating the call.

Plugin adds an optional authenticated receiver and request-local central callback
URLs. The standalone gateway validates provider signatures and exact client/account/
number ownership,then sends one scoped HMAC envelope to an allowed destination.
Durable minimal bindings retain old installations across route switches. Duplicate
initial Voice receives cached TwiML;uncertain initial delivery is not automatically
resent. Callback persistence and all call/acquisition/recording evidence remain
client-CB-owned. No second call ledger,outage bypass,CRM,Consent authority or export.

Direct signatures/URLs remain supported. Handoff configuration is optional through
existing external configuration;no credentials are packaged. Recording flags/notices,
qualifications,leases/DNI,schema and evidence API remain unchanged.

Alma verification uses fictional numbers,synthetic clients and owned native clone
tables/HTTP receiver contexts. Inspect TwiML only;never execute it as a call. Native
results and exact candidate hashes are recorded separately after execution. No
stable promotion implied by package creation or Alma installation.
