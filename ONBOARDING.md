# Call Bridge onboarding — one complete checklist

CB0.2.1/DB1.1.0. Direct operation is independent of the optional Gateway0.1.0.
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
2. **Required credential pair.** Open CB Setup & Status,then Open protected WP
   credential entry. Find “Call Bridge — required Twilio Account SID and Webhook
   Auth Token”,resource `default`. Use the SAME account/subaccount that owns the
   dedicated number. Both inputs are masked and never prefilled. Stage the complete
   pair,then activate the exact staged version. Staging does not replace active
   credentials. Blank retains;partial,masked placeholders and invalid formats fail.
   Replacement is explicit and atomically handled by Base. No provider authentication
   is claimed by local validation. Never send these values in chat/screenshots/logs.
3. **Existing external authority.** Nonempty SECB_TWILIO_ACCOUNT_SID or
   SECB_TWILIO_AUTH_TOKEN makes the external pair authoritative. A partial pair fails
   closed;CB never combines it with Base. Base rejects shadowed saves rather than
   pretending newly entered values are in use. Existing valid external deployments
   need no re-entry. To intentionally adopt Base,first arrange a backed-up maintenance
   change removing BOTH external fields,then stage/activate a complete Base pair
   before reopening traffic. CB neither imports nor edits the external file. Do not
   change source/account during active calls. Base absence still permits external
   configuration;it does not permit plaintext fallback storage.
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
   and access policy explicitly. Keep recording off for the final basic-call check.
9. **Interpret checks accurately.** Configured means material/settings exist.
   Automatically checked means local formats,authority,readability,extensions and
   table/version/GC presence were inspected. Provider ownership/authentication and
   real calling/attribution/recording require separate evidence. Missing required
   configuration prevents Configuration complete. Live verification is never inferred
   from a badge,200 response,source label or duration threshold.
10. **One final operator test — only after separate authorization.** Reuse accepted
   allocator,DNI,callback,recording and permission evidence where bytes are unchanged.
   Once real account/number and approved provider routing are configured,one eligible
   synthetic-campaign visit plus ONE two-participant inbound call can verify the
   newly selected credential source with real provider requests and its reservation.
   Suggested bound:65–75sec after answer,max90sec,$1operatorbudget,recording OFF.
   Check visible DNI,notice/whisper as applicable,forwarding/two-way audio,canonical
   call/session evidence,terminal duration/technical qualification and restoration.
   Do not repeat already accepted recording tests. No such call/provider change is
   performed or authorized by the onboarding implementation assignment.

## Errors and safe recovery

- **External pair incomplete/invalid:** privately correct both external fields;
  Base fallback/shadowed saves are blocked. Do not re-enter into an ignored source.
- **Base key unavailable:** host owner checks/provisions the existing key mechanism.
  Never regenerate a lost key to “repair” encrypted records.
- **Storage unavailable:** initialize/check Base tables through its protected action
  after host key readiness. CB getters do not create Base schema.
- **Integrity failure:** stop replacement and reconcile installation/key/data with
  Base's owner. No secret/error body is reflected by CB.
- **Failed save:** check complete formats,resource `default`,capability/nonce and
  current revision. Reload/reconcile uncertain commits;do not blindly replay saves.
- **Staged but not configured:** activate the exact staged version. Active material
  remains authoritative until activation;local acceptance is not provider validation.
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
