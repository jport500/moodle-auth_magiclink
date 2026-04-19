# Magic Link Authentication (auth_magiclink)

Passwordless authentication for Moodle. Users enter their email
address and receive a one-time login link. No password required.

**Version:** 3.3
**Requires:** Moodle 4.1+ (2024040100)
**License:** GPL v3+

## Features

- One-click passwordless login via email
- SHA-256 hashed token storage (plaintext never stored)
- CSRF-protected login form
- Configurable token TTL (default 15 minutes)
- Per-email and per-IP rate limiting
- Domain allowlist restriction
- Configurable auth-method allowlist (v3.3+) — admins choose which
  auth methods may request magic links; admins with `moodle/site:config`
  are always excluded regardless of the allowlist
- Admin management page for token and audit log review
- Privacy API (GDPR) compliant
- Public API for external plugins to generate login URLs
- Event observers invalidate tokens on user state changes

## Installation

1. Copy the `magiclink` directory to `auth/magiclink/` in your
   Moodle installation.
2. Visit Site Administration > Notifications to trigger the install.
3. Enable the plugin at Site Administration > Plugins >
   Authentication > Manage authentication.
4. Set user accounts to use `auth=magiclink` via user profile or
   bulk upload.

## Configuration

Settings are at Site Administration > Plugins > Authentication >
Magic Link:

- **Email Subject / Body** — customise the magic link email template.
  Placeholders: `{$a->firstname}`, `{$a->lastname}`, `{$a->link}`,
  `{$a->loginlink}`, `{$a->sitename}`, `{$a->expiration}`.
- **Allowed auth methods** (v3.3+) — multiselect of enabled auth
  plugins. Users whose `auth` field matches one of these may request
  a magic link. Administrators (users with `moodle/site:config`) are
  always excluded regardless of this setting. See *Who can use
  magic link login* below for default behavior on fresh install and
  upgrade.
- **Allowed Domains** — comma-separated list of permitted email
  domains. Leave empty to allow all.
- **Token lifetime** — how long a link remains valid (default 15 min).
- **Rate limits** — per-email and per-IP request limits within a
  configurable time window.
- **Token retention** — expired tokens are pruned after this many
  days (default 30).

## How it works

1. On the Moodle login page, the magic link form appears (injected
   via AMD JavaScript for progressive enhancement).
2. User enters their email and submits. A one-time token is generated,
   hashed, and stored. The plaintext token is emailed as a link.
3. User clicks the link (`/auth/magiclink/verify.php?token=...`).
4. The plugin hashes the incoming token, looks it up, validates it
   (unused, unexpired, user active), and calls `complete_user_login()`.
5. All outcomes (valid email, invalid email, rate-limited, domain-
   blocked) produce the same user-visible message to prevent email
   enumeration.

## Admin management

Visit the management page (linked from the settings page) to:

- View active and used tokens with user, email, created/expires dates
- Revoke, extend, or delete tokens
- Review the audit log of all authentication events

Requires the `auth/magiclink:manage` capability (granted to the
manager archetype by default).

## Troubleshooting

### Why didn't they get the email?

By design, every magic link request produces the same user-visible
message ("If that email address is registered, a login link has been
sent") regardless of whether the email was actually sent. This
prevents attackers from discovering which emails are registered.

It also means the UI will not tell you why a request failed. **The
audit log is the primary diagnostic tool.**

### Reading the audit log

Via the admin UI: visit the management page at
`/auth/magiclink/manage.php` (requires `auth/magiclink:manage`
capability). The audit log table is at the bottom.

Via CLI:

```bash
php -r '
define("CLI_SCRIPT", true);
require("config.php");
global $DB;
$rows = $DB->get_records("auth_magiclink_audit", null,
                         "timecreated DESC", "*", 0, 10);
foreach ($rows as $r) {
    echo date("H:i:s", $r->timecreated) . " | " .
         str_pad($r->action, 15) . " | " .
         str_pad($r->email, 40) . " | " .
         ($r->info ?: "-") . "\n";
}
'
```

### Audit actions reference

| Action | Meaning | Typical fix |
|--------|---------|-------------|
| `send_link` | Token generated and email sent | Success — no fix needed |
| `login_success` | User verified token and logged in | Success — no fix needed |
| `login_failed` | Token verification failed (check `info` column for reason: expired, already used, user inactive) | User requests a new link |
| `wrong_auth` | User exists but their `auth` method is not on the allowlist (v3.3+: emitted at both login request and token verify) | Either add the user's auth method to `allowed_auth_methods`, or change the user's auth method to one already on the allowlist |
| `admin_blocked` (v3.3+) | User has `moodle/site:config` capability — admins are never eligible for magic-link login regardless of allowlist | Admin accounts should use their password; there is no fix to make an admin account magic-link-eligible. See *Admin exclusion* below |
| `no_user` | No active user found with that email | Check spelling, or user may be deleted/suspended |
| `domain_blocked` | Email domain not in the allowed list | Add the domain to the `alloweddomains` setting |
| `rate_limited` | Too many requests from this email or IP | Wait for the rate limit window to expire, or purge caches (see below) |
| `send_failed` | Token created but email delivery failed (check `info` for exception) | Check Moodle email configuration and mail server logs |

**Action vocabulary summary** (operator-oriented):
- `send_link`, `login_success` — happy paths.
- `no_user`, `rate_limited`, `domain_blocked`, `wrong_auth`, `admin_blocked` — request-time rejections (login page).
- `login_failed` — token-state failure at verify time (invalid, expired, already used).
- `wrong_auth` and `admin_blocked` also appear at verify time in v3.3+ (tokens issued under a wider allowlist must be rejected if the allowlist narrows, or if the user gains admin capability, before the token is used).
- `send_failed` — delivery exception after the token was created.

### Who can use magic link login

Magic link login is available to users whose `auth` field is on the
**Allowed auth methods** allowlist (v3.3+). The allowlist is admin-
configured at Site Administration > Plugins > Authentication > Magic
Link. Users whose auth method is not on the allowlist see the uniform
"if that email address is registered" message and get a `wrong_auth`
row in the audit log.

**Admin exclusion is hardcoded.** Users with the `moodle/site:config`
capability at the system context are always excluded from magic-link
login, regardless of whether their auth method is on the allowlist.
This is a non-configurable safety measure: an attacker who compromises
an admin's email inbox must not be able to convert that into an admin
session. Admin accounts must use passwords.

Admin attempts show as `admin_blocked` in the audit log (not
`wrong_auth`), so operators can filter for admin-magic-link attempts
as a distinct security-sensitive signal. See `docs/DECISIONS.md`
entries 2 and 3 for the full threat-model reasoning.

### Allowlist defaults

**Fresh install:** the allowlist includes all currently-enabled auth
plugins PLUS `magiclink` (the plugin's own auth method). Permissive
by default for new deployments. An admin who installs the plugin on
a site with `auth='email,manual'` gets an allowlist of
`nologin,manual,email,magiclink`.

**Upgrade from v3.2 or earlier:** the allowlist is set to `magiclink`
only, preserving pre-v3.3 behavior. Existing deployments see no
change until an admin widens the allowlist deliberately.

**New auth plugins enabled after install:** do NOT automatically join
the allowlist. If an admin installs and enables `auth_oauth2` on a
site where auth_magiclink was previously installed, oauth2 users will
get `wrong_auth` rejections until an admin explicitly adds `oauth2`
to the allowlist. This is intentional — new auth plugins require
opt-in, not silent enrollment.

**Empty allowlist:** honored as a deliberate lockdown. If an admin
saves an allowlist with no auth methods selected, no one can request
a magic link. Useful as a temporary disable without uninstalling the
plugin.

### Diagnosing "I didn't get the email"

Check the audit log for the user's email address:

- `wrong_auth` → user's auth method isn't on the allowlist. Either
  add their auth method to the allowlist, or change the user's auth
  to one already on the list.
- `admin_blocked` → user has `moodle/site:config`. They cannot use
  magic-link login. (Correct behavior; see *Admin exclusion* above.)
- `no_user` → no active user matches the email. Check spelling, or
  the account may be deleted or suspended.
- `rate_limited` → too many recent requests. Wait for the window to
  expire or purge MUC caches.
- `domain_blocked` → the email domain isn't in `alloweddomains`.
- `send_failed` → token was created, email delivery failed. Check
  Moodle's mail config and the `info` column for the SMTP error.

If there's no audit row at all, the request didn't reach the plugin
— check web server access logs and the front-end form.

### Rate limiter and testing

Repeated requests (including against non-existent emails) count
against the per-email and per-IP rate limits. During testing, if you
hit the limit, purge the MUC cache to reset counters:

```bash
php admin/cli/purge_caches.php
```

### Email prefill via URL

You can link to the login page with an email pre-filled:
`/login/index.php?email=user@example.com`. The magic link form
will render with that email already in the input. Useful for
welcome emails and external onboarding flows.

Special characters in email addresses must be URL-encoded in the
query parameter. The most common case is `+` in subaddresses
(e.g. Gmail-style `user+tag@example.com`), which must appear in
the URL as `%2B`:

    /login/index.php?email=user%2Btag@example.com

This is standard URL encoding — a literal `+` in a query string
is interpreted as a space, which produces an invalid email. Code
constructing these URLs via Moodle's `moodle_url` class or PHP's
`rawurlencode()` handles this automatically. Manual URL typing
during testing may not.

## For developers

`auth_magiclink` exposes a public API for external plugins that
need to generate magic login URLs:

```php
// Generate a one-time login URL for a user.
$url = \auth_magiclink\api::generate_login_url_for_user(
    $userid,
    7 * DAYSECS,                                        // TTL (null = default).
    new moodle_url('/course/view.php', ['id' => 5]),    // Optional wantsurl.
    'welcome'                                           // Purpose tag for audit.
);

// Revoke all active tokens for a user (e.g., on admin action).
$count = \auth_magiclink\api::revoke_user_tokens($userid);
```

The API caps TTL at 30 days. The `wantsurl` parameter is validated
against `PARAM_LOCALURL` — external URLs are silently dropped. All
API calls are logged to the audit table with the supplied purpose tag.
