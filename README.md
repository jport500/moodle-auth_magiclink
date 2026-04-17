# Magic Link Authentication (auth_magiclink)

Passwordless authentication for Moodle. Users enter their email
address and receive a one-time login link. No password required.

**Version:** 3.0
**Requires:** Moodle 4.1+ (2024040100)
**License:** GPL v3+

## Features

- One-click passwordless login via email
- SHA-256 hashed token storage (plaintext never stored)
- CSRF-protected login form
- Configurable token TTL (default 15 minutes)
- Per-email and per-IP rate limiting
- Domain allowlist restriction
- Auth-type enforcement (only `auth=magiclink` users receive links)
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
| `wrong_auth` | User exists but `auth` is not `magiclink` | Change user's auth method to `magiclink` in their profile |
| `no_user` | No active user found with that email | Check spelling, or user may be deleted/suspended |
| `domain_blocked` | Email domain not in the allowed list | Add the domain to the `alloweddomains` setting |
| `rate_limited` | Too many requests from this email or IP | Wait for the rate limit window to expire, or purge caches (see below) |
| `send_failed` | Token created but email delivery failed (check `info` for exception) | Check Moodle email configuration and mail server logs |

### Who can use magic link login

Magic link login is only available to users whose authentication
method is set to **Magic Link**. Users on other authentication
methods (Manual, OAuth2, SAML, LDAP, etc.) will not receive a magic
link if they request one — the audit log records this as
`wrong_auth` and the user sees the same uniform "if this email
exists" message as any other rejection.

This is intentional. Allowing magic link login for password-based
auth methods would mean email compromise is sufficient for account
access, bypassing passwords entirely. For privileged accounts
(admins, managers) on `auth='manual'`, this would be a significant
security regression.

**To enable magic link login for a user:** Site Administration >
Users > Accounts > Browse list of users > edit the user's profile >
set Authentication method to "Magic Link" > Save.

**If you want to change this restriction:** the plugin does not
currently expose a setting for it. Relaxing the auth-type check is a
deliberate product decision, not a configuration option. If you have
a specific use case (for example, SSO-fallback login when your identity
provider is down), discuss with the LMS Light team before modifying
plugin code.

**Diagnosing:** if a user reports "I didn't get the email," check
the audit log for their email address. A `wrong_auth` row means the
user exists but their auth method is not `magiclink`. This is the
most common support ticket for new deployments where admin accounts
are tested before switching auth methods.

### Rate limiter and testing

Repeated requests (including against non-existent emails) count
against the per-email and per-IP rate limits. During testing, if you
hit the limit, purge the MUC cache to reset counters:

```bash
php admin/cli/purge_caches.php
```

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
