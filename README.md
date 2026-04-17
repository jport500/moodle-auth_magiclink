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
