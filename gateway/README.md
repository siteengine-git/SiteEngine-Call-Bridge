# Call Bridge Gateway 0.1.0 — unaccepted Alma routing candidate

Optional standalone PHP gateway; CB client receiver requires plugin0.2.0/DB1.1.0.
Direct-to-site Twilio validation and existing credentials remain supported. This
gateway is not a second call ledger, CRM, consent authority or Google exporter.

## Install and configure

PHP8.1+,curl,OpenSSL,JSON,hash, reliable local filesystem locks,atomic rename/fsync
and private persistent disk are required. Extract the DISTINCT gateway package
outside the webroot. Its gateway/entry.php loads gateway/Router.php and the shared
src/Gateway/Protocol.php. Publish only a small shim:

```php
<?php
define('SECB_ROUTER_CONFIG', '/private/approved-site/gateway-config.php');
require '/private/approved-site/gateway-package/gateway/entry.php';
```

The private configuration returns:

```php
return [
 'public_url' => 'https://gateway.example.invalid/callback.php',
 'document_root' => '/approved/site/webroot',
 'state_dir' => '/private/approved-site/bindings',
 'clients' => [
  'immutable-client-id' => [
   'account_sid' => $owningAccountSid, 'auth_token' => $matchingAuthToken,
   'numbers' => $ownedTrackingNumbers, 'current_destination' => 'installation-a',
   'destinations' => [
    'installation-a' => ['url'=>$approvedReceiverUrl, 'secret'=>$uniqueHandoffSecret],
   ],
  ],
 ],
];
```

Variables represent securely provisioned values,not literal examples/credentials.
Use the SAME owning account/subaccount as the registered number. Registry client
IDs are immutable; each number has exactly one client. Each installation/version
gets a unique random handoff secret of at least32bytes. Reject reused keys. Keep
code/config/bindings/backups outside the webroot; directories0700/files0600,owned
by the actual PHP worker. No secret editor or new credential service is provided.

In CB's EXISTING external configuration, optionally define:

```php
define('SECB_GATEWAY_NONCE_DIR', '/private/approved-client/gateway-nonces');
define('SECB_GATEWAY_CLIENTS', [
 'immutable-client-id' => [
  'account_sid'=>$owningAccountSid, 'numbers'=>$ownedTrackingNumbers,
  'gateway_base'=>'https://gateway.example.invalid/callback.php',
  'destination'=>'installation-a', 'secret'=>$uniqueHandoffSecret,
 ],
]);
```

Create the private nonce directory beforehand. Receiver URL is the designated
installation's `/wp-json/siteengine-call/v1/gateway/receive`. Omitting these
constants leaves handoffs unavailable and direct operation unchanged. Existing
SECB_TWILIO_* resolver remains authoritative for direct signatures/media. Central
receiver client/account/number lists are explicit trust boundaries,not routing or
per-number setting editors. Do not overwrite existing configuration when provisioning.

## Addresses and authentication

Permanent inbound POST address:
`https://gateway.example.invalid/callback.php?client=immutable-client-id&event=voice`.
Optional provider parent status POST uses `event=status`; it still requires a known
binding. CB-generated Dial/status/recording POST and whisper GET URLs include the
client,canonical parent CallSid and random call-binding capability. Whisper verifies
the signed child's ParentCallSid. Child identity is not persisted as a durable map.

Gateway verifies Twilio HMAC-SHA1 against its CONFIGURED HTTPS origin plus exact
request URI and all flat POST values. GET signs the entire original query. It
never trusts Host/X-Forwarded-* to reconstruct the public URL. Query/body collisions
remain separately signed. Duplicate,nested or ambiguous form/query keys,JSON provider
bodies,unsupported methods and payloads over64KiB are rejected explicitly.

Gateway sends one POST JSON v1 envelope to the exact allowed receiver URL,with
`X-SECB-Handoff: HMAC-SHA256(exact JSON bytes, unique installation secret)`.
Envelope binds client,destination,account,number,event,parentCallSid,public gateway,
call capability,original method/query/form,Unix timestamp and random64hex nonce.
Receiver verifies scope,HMAC,±60sec freshness and one-use nonce. Both hosts require
synchronized clocks. Raw forwarding headers cannot bypass direct validation.
Receiver checks persisted canonical call/number on callbacks,never current leases.
Unknown/unpersisted callbacks fail503 instead of an unmatched success. Temporary
request globals/context restore at the beginning of WordPress shutdown and on normal
exception paths. No browser credential or recording-media handoff is implemented.

## Continuity, failures and retention

Before the FIRST Voice transport,the gateway durably pins client/account/number,
destination version and fingerprint,random binding token and dispatch state. One
per-call lock has a2sec acquisition bound. Calls sharing a client but distinct
CallSids use independent locks. A successful Voice TwiML response is cached to
avoid redispatching duplicate provider Voice requests. State stores routing facts
and that call-control response only,not outcomes,durations,attribution or media.

Change only current_destination to switch NEW calls. Retain the old destination
entry/key and installation for ALL retained bindings. Existing callbacks use the
saved destination and its fingerprint,never the current route. Reusing a destination
ID with changed URL/key or deleting it fails503;it never redirects an existing call.
Callbacks arriving before any binding also fail503 without inventing a destination.

Transport verifies TLS,allows HTTPS only,no redirects,one attempt,4sec connect/8sec
TOTAL timeout and64KiB response cap. Non2xx,oversized/non-TwiML responses and transport
errors fail503. Do not retry Voice/whisper automatically. An uncertain initial Voice
delivery remains blocked for operator reconciliation; do not delete its state and
resend speculatively. Even a failed downstream response may have persisted data.
Provider callback retries use a fresh handoff nonce and existing CB idempotence;
the same internal nonce rejects409. A503 DOES NOT prove Twilio will retry. There is
no queue,background delivery,event framework or independent emergency forwarding.
The gateway still depends on a reachable working destination website.

Nonce files contain only expiry markers. Under an explicitly assigned maintenance
job,remove only `^[a-f0-9]{64}\.nonce$` files older than120sec from the EXACT configured
nonce directory,after timestamp/freshness windows have elapsed. No global cron edit
or automatic shared cleanup is installed. Monitor private disk capacity. Bindings
are not automatically expired: late recording/status continuity may outlive the
call. Archive/purge only after an explicit retention decision and preserve old
destination versions through that retention. Keep state backed up and access private.

## Alma verification and rollback

Local tests distinguish file/lock behavior from transport/database adapters. Native
Alma harness uses synthetic accounts,fictional numbers,owned schema clones and two
HTTP receiver contexts running the installed CB classes. No provider request or
real call is needed. Evidence must show persisted call/recording state,route switch,
denials,destination failure,direct behavior and owned cleanup;HTTP200 alone is not
acceptance. Fixture TwiML is inspected,NEVER submitted to execute a call.

Keep stable0.1.9 delivery unchanged until successor review. Before installation back
up CB files/config/tables and acquire existing shared execution ownership. DB stays
1.1.0. Optional receiver configuration can be removed to restore direct-only mode;
keep old gateway bindings/installations accessible until drained. File-only CB
rollback must not remove support while pending central calls still depend on it.
Never restore unrelated databases,cron,plugins or real call records. Clean only
marker-verified clone tables/fixtures/shims and retain private evidence.

## Production requirements — NOT deployed by this milestone

`calls.siteengine.io` is a PROPOSAL only. Its owner must separately assign DNS,TLS,
hosting/web-PHP/process/cache policy,private configuration/secrets,persistent storage,
backups,clock synchronization,monitoring,nonce cleanup and deployment ownership.
Approve exact client identities,number ownership,destination versions and existing
provider webhook before/after plans,with rollback and per-client activation. Verify
web OPcache/config reload behavior for registry changes. No production DNS,provider
settings,Climatrol resources,purchases or client traffic changes are included here.

Primary behavior references:
- https://www.twilio.com/docs/usage/security
- https://www.twilio.com/docs/voice/twiml
- https://www.twilio.com/docs/voice/twiml/number
