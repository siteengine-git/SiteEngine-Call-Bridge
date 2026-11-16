# Call Bridge 0.1.9 — recording review candidate

DB remains 1.1.0; no schema migration. Not a stable publication or client upgrade.
Stable 0.1.8 delivery and prior evidence remain authoritative pending review.

Recording still follows each number's existing flag. A Say notice precedes Dial;
the existing record-from-answer-dual option records both bridged legs and sends a
signed POST completion notification associated with the parent canonical call.
First completed recording identity remains authoritative; late/duplicate events
cannot erase it or alter terminal qualification. Checked conditional persistence
uses at most three attempts plus winner readback. Unknown persistence returns503;
this does not establish whether the provider will retry.

Call Log playback now supplies a WordPress REST nonce. Both REST authentication
and the handler's administrator/nonce checks protect media. The server builds the
Twilio HTTPS URL from the configured account and persisted Recording SID, ignores
the callback URL as a fetch target, disables redirects and verifies TLS. It requests
two-channel WAV, validates RIFF/WAVE, limits retrieval to20MiB and returns private,
no-store audio. Provider URLs, authorization headers and credentials stay server-side.
Reload Call Log if its session nonce expires. This is a bounded full-file proxy,
not resumable/range streaming. Legacy mono recordings and larger files are outside
this two-channel bounded playback path and fail closed; no automatic downmix.

The existing credential resolver is unchanged: optional matching API key/secret,
otherwise account SID/Auth Token. No new storage or provider configuration.
Keep recording disabled until the explicitly approved test window. Audible notice
does not establish browser consent, advertising consent or legal consent evidence.

Focused local tests: actual controllers/renderer with process-isolated database,
WordPress and HTTP adapters. Installed checks and live recording/audio acceptance
are separate and recorded privately in STATUS.md. No synthetic WAV is live audio.

Official behavior references:
- https://www.twilio.com/docs/voice/twiml/dial
- https://www.twilio.com/docs/voice/api/recording

Install only the separately hash-verified candidate into the assigned Alma plugin
directory after existing execution ownership, targeted file/config/table backups
and affected-site maintenance. Preserve activation and external configuration;
no reactivation/migration/forced cron. Verify complete manifest, installed PHP lint,
prior data and GC, remove owned fixtures/controls and release ownership. File-only
rollback may restore the targeted plugin backup because the DB version is unchanged;
never restore unrelated database/site state. Climatrol is excluded.

Call-origin advertising, authoritative Consent, durable child-leg mapping and
Google delivery remain separate. No real call is initiated by installation.
