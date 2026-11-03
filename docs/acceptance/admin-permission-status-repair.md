# Unaccepted permission-status correction

Installed 0.1.2 denies a subscriber's Tracking Numbers save/delete actions before
nonce processing or repository access. Native HTTPS testing nevertheless observed
HTTP 500 with the fixed `Insufficient permissions.` message. WordPress defaults
`wp_die()` to 500 when no response code is supplied. Protected menu pages and the
protected REST controller already deny access correctly; no authorization bypass
was demonstrated.

Reproduce with a disposable subscriber on an isolated development installation:
POST `action=secb_save_number&id=0` or `action=secb_delete_number&id=0` to
`/wp-admin/admin-post.php`. Missing or invalid nonces do not change the capability
denial. A valid save nonce also cannot bypass it. Expect 403; the accepted 0.1.2
runtime returns 500. Never use genuine tracking IDs for the denial reproduction.

The local correction supplies `['response'=>403]` to the existing capability
denials in `TrackingNumbersPage::render()`, `handleSave()` and `handleDelete()`.
It changes only HTTP status. The capability, fixed message, nonce ordering,
repository operations, redirects, routing and provider behavior remain intact.

`php -n tests/admin-permissions.php` preserves the regression: the prior source
produces 14 PASS / 6 FAIL; the corrected source produces 20 PASS / 0 FAIL. These
are isolated PHP stubs, not installed WordPress acceptance. They exercise all
three direct entry points, rejection before nonce/repository access and both
administrator nonce boundaries. Existing original-Base 2.2.2, Base-absent and
unsafe-URL integration fixtures remain 77 / 73 / 71 PASS respectively. Changed
source and new test lint on the existing local PHP 8.5.12 executable.

The repair is implemented locally only. It has not been packaged, installed,
accepted or released. Runtime release 0.1.2 / DB 1.0.0 and its original archive
and manifest remain unchanged. The retained release manifest describes that
archive; it is not a manifest for this unpackaged source correction.

An installed update requires a separately reviewed package/manifest/version and
a bounded Alma installation assignment, followed by the affected native
subscriber-action checks. Do not silently modify the verified installed
candidate or claim local fixture success as native acceptance. Operational
browser, database and cleanup evidence stays in private project status/storage.
