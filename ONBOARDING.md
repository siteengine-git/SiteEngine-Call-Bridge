# Call Bridge onboarding — one complete checklist

CB0.2.3/DB1.1.0. Direct operation is independent of the optional Gateway0.1.0.
No step below automatically buys a number,changes provider configuration or places
a call. Public business phone,owned tracking number and forwarding destination
are separate roles and are never synchronized automatically.

1. **Host prerequisites.** Use WordPress6.4+/PHP8.1+ with mysqli,mbstring,JSON,hash,
   OpenSSL and Sodium for Base credentials. Base must support `credentials-1`.
   The host owner provisions the existing protected Base installation key and its
   credential/audit tables using Base's supported host tool/protected UI. Missing
   keys must not be regenerated over existing encrypted data. Preserve matched
   key/data backups under the host owner's custody. This is a one-time host step,
   not a secret manager provided by CB.
2. **Required credential pair — WP.** Open SiteEngine → Credentials → Call Bridge.
   Enter **Twilio Account SID** and **Twilio Auth Token** from the SAME owning
   account/subaccount, then **Save credentials**. The Auth Token is masked; both fields are blank
   and write-only. Complete local validation and activation happen within Save;
   there is no resource/version selection or separate activation step in ordinary
   setup. Both blank retains the working pair. Partial, masked or invalid inputs
   fail without replacing it. Local validation is not provider authentication.
   For replacement, enter the complete pair. An Account SID change is an account
   change: review the on-screen explanation before applying it. Existing numbers,
   account ownership, media access and provider webhooks must match the new account;
   CB does not change them. Never put either value in chat, Git or screenshots.
   Optional playback API credentials are separate under **Advanced**.
3. **Existing external authority — deliberate owner migration only.** Nonempty
   SECB_TWILIO_ACCOUNT_SID or SECB_TWILIO_AUTH_TOKEN makes the external pair
   authoritative. Partial pairs fail closed; Base fields are never mixed in.
   A shadowed Save is rejected. Preserve working external installations without
   re-entry. For an intentional source migration, the installation owner keeps a
   private rollback backup, transfers the SAME complete pair through supported
   Base commands in a controlled process, verifies activation/equality, then retires
   both external overrides together and verifies fresh web-process resolution.
   No request may observe an incomplete source. Preserve all unrelated external
   settings and optional playback behavior. No automatic import or global authority
   bypass is supplied. Alma's explicit migration is already complete; do not repeat
   it. Base absence still supports external credentials, never plaintext WP storage.
4. **Choose routing guidance.** Direct is the default and remains supported. Its
   exact site-derived inbound Voice address is POST `/wp-json/siteengine-call/v1/twilio/voice`.
   CB generates POST Dial/status,optional POST recording and GET recipient whispers.
   Select Central only when the approved registry,matching owning account/number,
   destination version,handoff secret and private nonce storage are provisioned.
   Setup then displays exact registered central Voice/parent-status URLs. Call-bound
   recording/whisper URLs are generated,not copied manually. Saving the mode chooses
   guidance only;it never edits Twilio or disables direct signatures. Production
   `calls.siteengine.io` is proposed,not an existing target. Gateway deployment and
   provider activation require their own assignment;see gateway/README.md in source.
5. **Provider account/number — manual.** Confirm account ownership,Voice capability,
   number ownership,current webhook/fallback use and approved recipients. Purchase
   or change webhooks only with operator authorization. Review exact before/after
   Voice URL and HTTP POST method,including public HTTPS/proxy interpretation. Never
   disable signatures. Account SID/Auth Token entry alone does not configure Twilio.
6. **Tracking Numbers — WP.** Add the owned tracking number and approved forwarding
   destination. Verify that the recipient terminates without a forwarding loop.
   Select source `google_ads` for the intended Google Ads pool,and an explicit pool
   group. Choose active flag,qualification duration (default60sec),repeat window
   and new/repeat whisper text. Keep existing public business details unchanged.
   Lease defaults30minutes,session retention90days;external timing constants remain
   supported. Size the selected pool for concurrent attributed visits throughout
   the lease window,not for average call volume. A one-number dev test proves no
   client capacity. Exhaustion restores original contact details,not shared allocation.
7. **DNI and caching — verify before calls.** Ordinary matching telephone links use
   the configured forwarding destination as the match;unrelated telephone links and
   non-phone labels stay intact. Explicit opt-ins are `data-se-phone`,
   `data-se-call-tracking` or `.se-tracking-number`. Verify a no-attribution visit,
   then an eligible owned visit with synthetic campaign markers (no invented click
   ID/Consent). Verify the saved session/lease and visible number. Inspect actual
   GTM/Google website-call-number replacement and other DNI before enabling a
   competing swap;the PPC owner must resolve any conflict. Check real cached guest
   pages and HIT/MISS headers. DYNAMIC/MISS alone does not establish HIT behavior.
   Do not globally purge/disable optimizers or change shared policies as a test shortcut.
8. **Recording/playback — opt in separately.** Recording defaults OFF. Approve the
   participants,notice and recording use before enabling its per-number flag. The
   notice precedes recording from answer. Playback requires an administrator/fresh
   REST nonce and uses protected server-side retrieval. Optional playback API Key
   SID/Secret have a SEPARATE Base set;they must belong to the same owning account.
   They do not authenticate webhooks. With no complete API pair,the required
   account/token pair is the media fallback. Explicit complete invalid API material
   fails closed,not silently switched. Two-channel WAV is bounded to20MiB;legacy
   mono/oversized recordings are outside this proxy path. Define recording retention
   and access policy explicitly. Keep recording off unless a separate recording assignment authorizes it.
9. **Interpret checks accurately.** Configured means material/settings exist.
   Automatically checked means local formats,authority,readability,extensions and
   table/version/GC presence were inspected. Provider ownership/authentication and
   real calling/attribution/recording require separate evidence. Missing required
   configuration prevents Configuration complete. Live verification is never inferred
   from a badge,200 response,source label or duration threshold.
10. **Reuse acceptance evidence.** Configuration, synthetic storage and signature
   checks do not prove provider authentication. For this Alma onboarding update,
   reuse accepted routing/DNI/callback/recording evidence where bytes are unchanged;
   no new operator call or recording session is needed. A new client deployment's
   approved account/number, webhook/cached-page verification and any bounded operator
   call belong to that deployment assignment, not this Save workflow.

## Errors and safe recovery

- **External pair incomplete/invalid:** privately correct both external fields;
  Base fallback/shadowed saves are blocked. Do not re-enter into an ignored source.
- **Base key unavailable:** host owner checks/provisions the existing key mechanism.
  Never regenerate a lost key to “repair” encrypted records.
- **Storage unavailable:** the host owner checks Base storage and matching key
  readiness through Base’s supported host route. This is not an ordinary credential
  entry step. CB getters do not create Base schema.
- **Integrity failure:** stop replacement and reconcile installation/key/data with
  Base's owner. No secret/error body is reflected by CB.
- **Failed save:** follow the local validation, account-change or host-readiness message.
  Supply the complete matching pair; both blank retains it. Reload/reconcile an
  uncertain result before retrying. Do not expose secrets to diagnose a failure.
  Ordinary Save includes activation; local acceptance is not provider validation.
- **Central selected but unavailable:** correct registry/receiver/account/storage,
  or select Direct. Do not treat an unprovisioned gateway as required for direct use.

CRM delivery,authoritative call-bound Consent and Google call exports remain
separate integrations and are not prerequisites for basic CB. AB owns advertising
eligibility/conversion identity/deduplication/delivery. Technical duration qualification
is not business qualification,Consent or a won job. No new provider adapter or
competing credential facility is included.

Upgrade preserves external configuration,per-number settings,calls and original
acquisition evidence. DB remains1.1.0. Preserve Base key/data recovery custody too.
File rollback to0.2.0 is safe only for sites still using external credentials:
0.2.0 cannot read newly activated Base credentials. Reconcile source before rollback;
never copy plaintext secrets into options or restore unrelated site/database state.
