# Changelog

## v3.3 (2026-04-19)

Configurable auth-method allowlist replaces the v3.0–v3.2 hardcoded
`auth='magiclink'` restriction. Customer feedback after `local_welcomeemail`
v1.0 shipped showed the hardcoded check created support burden on
mixed-auth deployments — operators wanted magic-link login to work
across auth methods with equivalent security posture.

### Features

- New setting `allowed_auth_methods` — admin-configurable multiselect
  of enabled auth plugins. Users whose `auth` field is on the list
  may request magic links.
- Fresh-install default: all currently-enabled auth plugins PLUS
  `magiclink` (the plugin's own auth method). Permissive for new
  deployments.
- Upgrade-from-v3.2 default: `magiclink` only. Existing deployments
  see no behavior change until an admin widens the allowlist.
- Admin-capability exclusion is hardcoded and non-configurable. Users
  with `moodle/site:config` are always blocked from magic-link login
  regardless of allowlist contents. Threat model: an attacker who
  compromises an admin's email inbox must not be able to convert
  that into an admin session. See `docs/DECISIONS.md` entry 2.
- Verify-time re-check: tokens issued under a wider allowlist or
  before the user gained admin capability are rejected at verify time
  if the allowlist has narrowed or the user has become admin. Short
  TTLs bound the exposure window.

### New audit actions

- `admin_blocked` — emitted at both login request and token verify
  when the user has `moodle/site:config`. A distinct action (not
  `wrong_auth`) so operators can filter for admin-magic-link attempts
  as a security-sensitive signal. Admin-first ordering: admin users
  always surface as `admin_blocked` regardless of whether their auth
  method is also off the allowlist. See `docs/DECISIONS.md` entry 3.
- `wrong_auth` is now emitted at verify time as well as at login
  request. Pre-v3.3, verify-time rejections used `login_failed` with
  an info-column reason string; v3.3 normalizes allowlist rejections
  to the `wrong_auth` top-level action in both controllers. Operators
  with existing audit queries filtering by action may see increased
  `wrong_auth` volume post-v3.3 (both controllers emit it now).
  `login_failed` remains reserved for token-integrity failures
  (invalid, expired, used) at verify time.

### Configuration

- `allowed_auth_methods` at Site Administration > Plugins >
  Authentication > Magic Link. Multiselect; empty selection is
  honored as a deliberate lockdown.
- New auth plugins enabled on a site AFTER auth_magiclink install
  do not automatically join the allowlist. Admins must opt in by
  editing the setting.

### Migration notes

- Upgrade from v3.2 writes `allowed_auth_methods = 'magiclink'` for
  existing installs via `db/upgrade.php`. Pre-v3.3 behavior
  preserved. Three PHPUnit tests cover the upgrade path.
- No schema changes. Version bump: 2026051800 → 2026060100.
  Release: v3.2 → v3.3. Maturity unchanged (STABLE).
- Version number is a monotonic integer, not a calendar-accurate
  date. See `docs/DECISIONS.md` entry 7 for the historical context.

### Internal changes

- New public API `\auth_magiclink\api::is_auth_allowed(\stdClass $user)`
  — allowlist check with three-state logic (unset fallback, empty
  lockdown, CSV membership).
- New public API `\auth_magiclink\api::is_admin_user(\stdClass $user)`
  — admin-capability check.
- `login_controller`, `verify_controller`, and `observer` all call
  the shared helpers; hardcoded `auth !== 'magiclink'` removed.
- Admin-first ordering at login and verify (admin check before
  allowlist check) for audit-log clarity.
- Observer revokes tokens when a user's auth method leaves the
  allowlist (not just when it stops being `magiclink`). Capability
  grants don't fire user_updated, so observer doesn't cover the
  admin-capability window — verify-time enforcement is the single
  covering point for that.

### Dependencies

- Unchanged from v3.2.

### New architectural-decisions document

- `docs/DECISIONS.md` (new) captures the seven v3.3 design decisions
  with alternatives considered, reasoning, and "would revisit if"
  triggers. Future developers returning to this code should read
  that file first.

## v3.2 (2026-04-17)

Small feature addition for integration with external plugins.

- The magic link login form now accepts an email prefill via
  query parameter. A URL like
  `/login/index.php?email=user@example.com` will render the
  form with the email field populated, so users can click
  "Send Magic Link" directly without re-typing.
- Intended use case: welcome emails sent by other LMS Light
  plugins that want to deep-link users to a pre-filled form.
- Security note: the prefill accepts any syntactically-valid
  email; there is no verification that the submitter is who
  the email belongs to. An attacker could craft a prefill
  URL for a phishing page — the risk is minor (enumeration
  confirmation, not account access) and matches patterns
  common in other web apps. Not a new attack surface;
  documented for transparency.

## v3.1 (2026-04-16)

Bug fixes.

- Clear `auth_forcepasswordchange` user preference on successful
  magic link verification. Without this, users created via admin
  UI with the "Force password change on next login" flag set
  (which Moodle applies by default for new accounts) would click
  a valid magic link, be logged in successfully, then hit
  Moodle's "You cannot proceed without changing your password,
  however there is no available page for changing it" error.
  The flag is meaningless for passwordless auth — a successful
  magic link verification is itself proof of identity.

Documentation.

- README troubleshooting section clarifies that magic link
  login requires `auth='magiclink'` on the user profile, with
  guidance on how to enable it for existing users.

## v3.0 (2026-04-16)

Security hardening rewrite. Addresses all 26 findings from the v2
audit. PHP-level rewrite around service classes; schema and URL
contracts preserved.

### Security fixes

- S1 Critical: audit log on domain-blocked path now receives the
  real email (was writing null due to $mail typo)
- S2 High: CSRF protection via sesskey on login form submission
- S3 High: uniform response message defeats email enumeration
- S4 High: tokens now stored as SHA-256 hashes, not plaintext
- S5 High: rate limiting per email and per IP on link requests
- S6 Medium: suspended/deleted users rejected at verification
- S7 Medium: user-controlled fields HTML-escaped in email bodies
- New: auth-type restriction — only users with auth='magiclink'
  can receive magic links (prevents capture of admin accounts
  with other auth methods)

### New features

- Public API at `\auth_magiclink\api` for external callers to
  generate magic login URLs
- `wantsurl` query parameter on verify.php for deep-linked login
  (local URLs only; external URLs rejected via PARAM_LOCALURL)
- Scheduled task `\auth_magiclink\task\prune_expired_tokens`
  removes expired tokens daily
- Event observers invalidate tokens on user_deleted,
  user_suspended, auth change, password change

### New settings

- `tokenttlseconds` (default 900)
- `ratelimit_email` (default 3)
- `ratelimit_ip` (default 10)
- `ratelimit_window` (default 900)
- `prune_days` (default 30)

### New capability

- `auth/magiclink:manage` (manager archetype) replaces reliance
  on `moodle/site:config` for the management page

### Documentation

- Added Troubleshooting section to README covering the audit log
  as the primary delivery diagnostic, with action reference table.

### Architectural changes

- All page scripts (login.php, verify.php, manage.php) reduced
  to thin controllers. Business logic moved to service classes
  under `classes/`.
- Privacy API (GDPR) provider implemented for
  `auth_magiclink_token` and `auth_magiclink_audit` tables.
- 79 PHPUnit tests added (was zero in v2).

### Migration notes

- Existing plaintext tokens in `auth_magiclink_token` are marked
  `used=1` at upgrade. Users with outstanding magic links will
  need to request new ones.
- No schema changes. The `token` column still holds a 64-char
  string, but now it's a hash rather than plaintext.
- Version numbering note: v3.0 ships as 2026051602. A v2.0
  upgrade savepoint at 2026050705 was latent (higher than v2's
  declared version); v3 supersedes it cleanly.

## v2.0 (2026-01-20)

- Added audit log table
- Added domain restriction support
- Added admin management page with token extend/revoke/delete

## v1.0

- Initial release: passwordless email login via magic links
