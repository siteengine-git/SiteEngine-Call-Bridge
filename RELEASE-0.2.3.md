# CB 0.2.3 — ordinary credential onboarding review candidate

DB 1.1.0 unchanged. Requires Base's accepted `simple-save-1` contract for ordinary
WP Save; installed development dependency is Base 2.6.0. Base 2.5.0 remains its
stable published release. CB stable 0.1.9 delivery is preserved pending review.

SiteEngine → Credentials → Call Bridge presents Twilio Account SID, Twilio Auth
Token and Save credentials. Base validates and atomically activates the complete
pair. Both blank retains existing material; partial/masked/invalid input is rejected.
Definite precommit rejection preserves the active pair. Unconfirmed commits require
fresh status reconciliation and are never automatically retried or called unchanged.

Account SID changes require explicit confirmation of their account/number/webhook/
media consequences. Optional playback API credentials are under Advanced. No
credential is prefilled, exported or placed in a URL. Default resource and lifecycle
controls stay out of ordinary setup. Existing staged material requires explicit
Advanced resolution rather than silent discard. Host preparation stays Base-owned.

CB registers this supported opt-in presentation; no Base/AB code is changed.
Existing external authority and runtime resolver remain compatible. Alma's real
source migration was already completed and is not repeated. This candidate changes
no provider credential, routing, signature, lease/DNI, callback, recording transport,
schema or call-evidence behavior. Original calls/settings/acquisition are preserved.

ONBOARDING.md supplies one complete checklist and unavoidable manual provider/
number/cache steps. Direct routing remains independently usable; centralized
production activation, CRM/Consent/Google delivery remain separate. Prior accepted
live/recording evidence is reused. No new calls/provider requests are part of this
assignment. Installed native and browser results accompany the verification record.
