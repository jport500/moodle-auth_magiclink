# Architectural Decisions — auth_magiclink

This document captures significant design decisions made during the plugin's development, with the alternatives considered and reasoning for the chosen path. Entries are ordered by date.

Future-you, a collaborator reading this in 2027, or a different AI in a different session should be able to understand not just what the plugin does but why it does it that way. If a decision needs to be revisited, this document explains what was weighed at the time and what new information would justify revisiting.

Entries 1–7 were added with the v3.3 release (April 2026). Earlier architectural decisions (v1.0 through v3.2) predate this document and are not retroactively captured here; significant ones are summarized in `CHANGES.md`.

---

## 1. Configurable allowlist replaces hardcoded `auth='magiclink'` restriction

**Decision:** v3.3 introduces a configurable allowlist setting (`allowed_auth_methods`) controlling which auth methods may request magic-link logins. Replaces the hardcoded `$user->auth !== 'magiclink'` rejection that v3.0 through v3.2 enforced.

**Alternatives considered:**
- *Keep the hardcoded restriction.* Maximally restrictive — only users explicitly flipped to `auth=magiclink` can use the feature. Simple to reason about but creates operator burden for mixed-auth deployments.
- *Make it configurable with a boolean "strict mode" toggle.* Binary choice: either magiclink-only (v3.2 behavior) or any-auth-allowed (anything goes). Coarse-grained; doesn't support "magiclink + manual, but not oauth2" patterns that real deployments want.
- *Configurable multiselect allowlist.* Chosen. Admins pick which auth methods qualify. Supports the common mixed-auth case cleanly.

**Why configurable allowlist:** Customer feedback after `local_welcomeemail` v1.0 showed the v3.2 restriction created support burden. Real LMS Light deployments mix auth methods — some users on `auth=manual`, others on `auth=email` for self-registration, some on `auth=oauth2` or SSO. Operators wanted magic-link login to work across auth methods that have equivalent security posture (receiving a magic-link email requires inbox access, which is the same trust level as forgot-password flows on any auth method). The hardcoded restriction treated magiclink as uniquely privileged when it isn't.

**Backward compatibility via upgrade path:** Existing v3.2 installs receive `allowed_auth_methods = 'magiclink'` via `db/upgrade.php`. Upgrades preserve v3.2 behavior until admin deliberately widens the allowlist. Fresh installs default to all currently-enabled auth plugins plus `magiclink` — permissive by default for new deployments, consistent with customer feedback.

**Admin exclusion is separate:** The hardcoded admin-capability exclusion (Decision 2) is orthogonal to this setting — it always applies regardless of allowlist contents. The allowlist controls the *category* of user who can use magic-link login; admin exclusion is a *user-level* hard rule that operators cannot relax.

**Date:** 2026-04-19, during v3.3 implementation.

**Would revisit if:** Customer feedback shows the allowlist granularity is wrong (e.g., operators want per-role rules, not per-auth-method rules). Or if a future threat model reveals that mixed-auth magic-link support creates issues we didn't anticipate.

---

## 2. Admin exclusion is hardcoded and non-configurable

**Decision:** Users with `moodle/site:config` capability at the system context are always blocked from magic-link login. Three properties that together make this a hard rule:

1. **Hardcoded in `api::is_admin_user()`.** The check is in code, not a setting. There is no admin UI to disable it.
2. **Non-relaxable via allowlist.** Even if an operator tried to work around the exclusion by putting admin users' auth method on `allowed_auth_methods`, the admin check runs first (Decision 3) and rejects them. No configuration path opens admin magic-link login.
3. **Capability-scoped, not user-scoped.** The check uses `has_capability('moodle/site:config', context_system::instance(), $user)`, which returns true for *every* user who holds that capability — by `siteadmins` membership, by role assignment at system context, or by any future Moodle grant mechanism. It is not limited to the single out-of-the-box "Admin User." If 20 staff accounts have been granted admin capability, all 20 are blocked.

The check runs at both login request (`login_controller`) and token verify (`verify_controller`). Operators cannot add admins to the allowlist via configuration.

**Alternatives considered:**
- *Configurable admin exclusion via allowlist.* Admin user's auth method could be in the allowlist, and no separate check applied. Rejected — see threat model below.
- *Configurable "allow admins" toggle.* Operators opt in to admin magic-link access. Rejected — creates a foot-gun setting whose default is "off" but whose existence tempts operators to turn on.
- *Block only site admins (via `is_siteadmin()`, which reads `$CFG->siteadmins`).* Narrower than capability-based. Misses users who have `moodle/site:config` via role assignment without being in `siteadmins`. Rejected for being too permissive.

**Threat model that motivates the hardcoded exclusion:**
1. Attacker gains access to an admin's email inbox (phishing, account compromise at the email provider, device loss, etc.).
2. Without admin exclusion, attacker visits the Moodle login page, enters the admin's email, receives a magic link in the compromised inbox, clicks it, and lands in an admin session.
3. Result: email compromise cascades directly to site-wide admin takeover. One compromised inbox → full Moodle control.

Passwords break this chain — even with email access, attacker needs the password (or password-reset → which requires both the email AND the admin intentionally initiating the reset). Magic-link login removes the password step. Hardcoded admin exclusion restores a bright line: admin sessions require admin password, full stop.

**Why non-configurable:** Any setting is a foot-gun. Operators investigating "why doesn't my admin account work with magic link?" would naturally look for a setting to flip. Not having one makes the policy explicit and the reasoning auditable in code (single enforcement point in `api::is_admin_user()`). Operators who genuinely need admin accounts to use magic-link login should contact the LMS Light team to discuss their threat model before any bespoke modification.

**Auditability:** Admin attempts surface as audit action `admin_blocked` (not `wrong_auth`), so operators reading the log can tell "an admin tried to use magic link" apart from "a random user's auth method isn't allowed." See Decision 3 for ordering rationale.

**Date:** 2026-04-19, during v3.3 implementation.

**Would revisit if:** Moodle core introduces a capability specifically for "user may authenticate via magic link" that admins can be granted. The current approach treats admin capability as a singular privilege signal; a more granular Moodle permission model could supersede it.

---

## 3. Admin-first ordering at login and verify checkpoints

**Decision:** Both `login_controller` and `verify_controller` run the admin-exclusion check BEFORE the allowlist check. A rejection due to admin status produces audit action `admin_blocked`, never `wrong_auth`, even if the user's auth method also happens to be off the allowlist.

**Alternatives considered:**
- *Allowlist check first.* Minor performance argument (has_capability is marginally more expensive than an `in_array`), and more specific audit for the common-case rejection. But produces confusing audit log entries when an admin with a disallowed auth method attempts login — would show `wrong_auth` and hide the admin-attempt signal entirely.
- *Run both checks, emit both audit rows.* Two audit entries per rejection. Doubles log volume for a narrow case and complicates downstream aggregation.
- *Admin-first (chosen).*

**Why admin-first:** Audit-log clarity is the primary operational concern. An admin's magic-link attempt is a security-sensitive signal — operators want to see it prominently, filter on it, alert on it. An attempt that shows as `wrong_auth` mixed in with routine user rejections buries the signal.

Example:
- Admin user has `auth='manual'`, allowlist is `['magiclink']`.
- Both checks would reject.
- Admin-first: audit shows `admin_blocked`. Operator filtering `action='admin_blocked'` catches the admin attempt.
- Allowlist-first: audit shows `wrong_auth`. Admin attempt blends into the 20 other `wrong_auth` rows from regular users that day.

**Performance trade-off acknowledged:** `has_capability` is slightly more expensive than `in_array($user->auth, $allowed, true)`. The difference is microseconds and `has_capability` results are cached by Moodle's MUC layer. Measurable only if an operator is running load tests specifically against this path. Audit clarity wins.

**Call-site pattern:** Both controllers use explicit sequential `if` blocks rather than a combined `can_use_magiclink()` helper. That way each check owns its audit action. A combined helper would require returning an enum or status object to preserve the "which check failed?" information, which complicates the API without benefit.

**Date:** 2026-04-19, during v3.3 Phase 3 implementation.

**Would revisit if:** Audit volume analysis shows `admin_blocked` entries are rare enough that the allowlist-first optimization would materially matter, AND operators signal they'd rather filter on two actions than one. Neither is likely.

---

## 4. Three-state setting helper: unset / empty / comma-separated

**Decision:** `api::is_auth_allowed()` distinguishes three states of the stored `allowed_auth_methods` config:
- **Unset** (`get_config` returns `false`): fall back to all currently-enabled auth plugins via `\core\plugininfo\auth::get_enabled_plugins()`. Defensive default, not the primary path.
- **Empty string** (`''`): honored as an admin-intended lockdown. No one qualifies.
- **Comma-separated string** (normal case): parse and check membership via `in_array`.

**Alternatives considered:**
- *Treat unset and empty as equivalent.* Either both fall back to a permissive default, or both reject everyone. Loses the distinction between "admin hasn't configured" (want a reasonable default) and "admin explicitly locked down" (want magic-link disabled for everyone temporarily).
- *Always require an explicit stored value.* Reject if unset, requiring admin to visit settings before the feature works. Rejected — adds a mandatory setup step to fresh installs without benefit (the settings.php default is already correct, see Decision 6).

**Why the three-state distinction:** Operators have two legitimate reasons to have an empty-looking allowlist:
1. Fresh install that hasn't been touched. Permissive default is the right answer.
2. Deliberate lockdown ("we're being attacked, disable magic links NOW, I'll un-disable later"). Lockdown must be honored.

These intentions collide only when the code can't tell them apart. By distinguishing `false` (never stored) from `''` (explicitly stored as empty), `is_auth_allowed` handles both without asking the operator to jump through hoops.

**In practice:** The unset-fallback path is defensive and should rarely fire — Moodle's install lifecycle writes the settings.php default into config on fresh install (see Decision 6), so `allowed_auth_methods` has a stored value from day one. But the fallback catches the edge case where config gets cleared outside the install path (DB manipulation, admin CLI mistake, uninstall-then-reinstall-then-clear-config race). Three states means the code is safe under these scenarios without silent misbehavior.

**Test coverage:** All three states have dedicated tests in `tests/api_test.php` — unset→fallback, empty→lockdown, csv→membership — each with explicit setup to exercise the distinct path.

**Date:** 2026-04-19, during v3.3 Phase 2 implementation.

**Would revisit if:** A future Moodle core change alters the semantics of `get_config` return values such that `false` / `''` / `null` stop being distinguishable.

---

## 5. Verify-time re-check without eager revocation on allowlist change

**Decision:** When an admin narrows the allowlist (removes an auth method that was previously allowed), tokens already issued to users with that auth method are NOT immediately revoked. Those tokens remain in the DB until expiry. The enforcement happens at verify time: `verify_controller` calls `api::is_auth_allowed($user)` after `verify_and_consume` succeeds, and rejects tokens whose user's auth is no longer allowed.

**Alternatives considered:**
- *Eager revocation on allowlist change.* When admin saves a narrowed allowlist, iterate all outstanding tokens, revoke any belonging to users whose auth is now disallowed. Cleaner state but requires an admin-settings-save observer, a large DB scan, and a complex mutation. Breaks the "save a setting" fast path.
- *No verify-time check; trust issue-time.* If the token was issued under a permissive allowlist, honor it at verify. Simpler but creates a window where narrowing the allowlist has no effect for ≤15 minutes (the token TTL). For security-motivated narrowings (admin suspects abuse), this window matters.
- *Verify-time re-check (chosen).* Cheap — just the same `is_auth_allowed` call at verify. Immediate effect. Bounded exposure window via short TTL.

**Why the re-check is sufficient without eager revocation:** Token TTLs are short (15 min default). The worst case is: allowlist narrows at T+0, a token was issued at T−14min to a user whose auth is now disallowed, the user clicks the link at T+1. The verify-time check rejects them cleanly. The longer the admin waits between issuing the narrowing order and an attacker using a pre-issued token, the smaller the attack window; after 15 min it's closed entirely.

**Trade-off:** Under extreme circumstances (admin narrows allowlist during active attack), up to 15 minutes of already-issued tokens could be redeemed if not for the verify-time check. The verify-time check eliminates that. If an operator wants belt-and-braces, they can manually revoke tokens via the admin UI, which is a separate action from narrowing the allowlist.

**Same reasoning applies to the admin-capability case** (see Decision 2): if a user gains admin capability between issue and verify, the verify-time admin check rejects the token. Moodle doesn't emit a reliable event on capability grants, so observer-based eager revocation isn't possible for that case anyway. Verify-time enforcement is the only enforcement point for the capability-change window.

**Date:** 2026-04-19, during v3.3 Phase 2 implementation.

**Would revisit if:** TTLs are significantly lengthened (e.g., 24-hour tokens for specific use cases), making the 15-minute exposure-window argument weaker. At that point, eager revocation on allowlist change may become worth the complexity.

---

## 6. Fresh-install default is set via `settings.php`, not `db/install.php`

**Decision:** The fresh-install default value of `allowed_auth_methods` is declared in `settings.php`'s `admin_setting_configmultiselect` default parameter. No `db/install.php` hook. Moodle's install lifecycle writes the settings.php default into config on fresh install.

**Alternatives considered:**
- *`db/install.php` with `set_config('allowed_auth_methods', ...)`.* Seemed intuitive — explicitly populate the config on install. Attempted during Phase 2 development. Didn't work.
- *Rely on runtime fallback only.* Leave config unset; let `api::is_auth_allowed` fall back to `get_enabled_plugins` on every call. Rejected — introduces a permanent "unset state" in production, complicates audit traceability (DB shows no value even though feature is in use), and confuses the settings-page UI (admin sees "not configured" for a feature that's in use).
- *`settings.php` default (chosen).*

**Why `db/install.php` didn't work — lifecycle archaeology:**

Moodle's install sequence for a plugin:
1. `db/install.xml` runs (tables created).
2. `db/install.php` runs if present (plugin-specific setup).
3. `admin_apply_default_settings(NULL, true)` runs later in the install lifecycle, from `lib/installlib.php:481`. The `$unconditional=true` arg means settings.php defaults are applied regardless of whether config already has a value.

So anything `db/install.php` writes to config for a setting that's defined in `settings.php` gets **unconditionally overwritten** by step 3. Discovered empirically during Phase 2 development — added a debug log to install.php, confirmed it wrote the intended value, then observed the DB state post-install showing the settings.php default instead.

**The fix:** Update `settings.php`'s default to the permissive value (all currently-enabled auth plugins plus `magiclink`). Install lifecycle then writes that value to config automatically. `db/install.php` deleted — redundant and would be overwritten.

**One subtlety — magiclink in the options array:** `admin_setting_configmultiselect` silently trims default values not present in the options array. If `magiclink` isn't in `$CFG->auth` at install time (and it typically isn't — plugins aren't auto-enabled in auth on install), `get_enabled_plugins` doesn't return it, so the options array wouldn't include it, so the default would trim it, so the stored allowlist would exclude the plugin's own auth method. Defensive fix: `settings.php` explicitly adds `magiclink` to both the default array AND the options array so it's valid to store.

**Trade-off:** `settings.php` code is slightly more complex (explicit `magiclink` handling). But the complexity is localized to one file, obvious in context, and matches the reason for the plugin's existence.

**Date:** 2026-04-19, during v3.3 Phase 2 implementation.

**Would revisit if:** Moodle changes its install-lifecycle ordering such that `db/install.php` runs after `admin_apply_default_settings`. Seems unlikely but would reverse which lever is correct.

---

## 7. Version numbers are monotonic integers in YYYYMMDD format, not calendar-accurate dates

**Decision:** `$plugin->version` in `version.php` is a strictly monotonic integer. The YYYYMMDD format is a convenient encoding for readability, not a binding claim that the version number matches the actual release date.

**Historical context:** Through v3.0, v3.1, and v3.2 (released May 2026 in the version numbers, but really landing in April 2026 per git history), version numbers were set with typos that pushed the date portion past calendar reality. By the time v3.2 shipped (commit `c288050`, April 17, 2026), the stored version was `2026051800` — a date in May that hadn't arrived.

**Why not fix the typos:** Moodle's upgrade machinery uses `$plugin->version` to decide whether to run upgrade.php blocks. Every `upgrade_plugin_savepoint` call records a specific number. Retroactively lowering the version to match real dates would:
1. Trigger downgrade-exception errors on any site running the plugin.
2. Require a matching savepoint scheme revision that admins would have to coordinate.
3. Produce confused git-blame output when archaeologists trace version numbers.

The version number is effectively opaque once it's shipped. Calendar accuracy was never its job — it's a monotonic counter with a date-shaped encoding.

**Going forward:** v3.3 ships as `2026060100` (nominally June 1, 2026, but actually landing April 2026 per git history). This extends the established pattern. Future versions must be greater than `2026060100`. A v3.4 that lands in May 2026 could be `2026070100`, or `2026060200`, or any monotonic increment. It doesn't need to match the calendar — it needs to be greater than the previous version and ideally easy to order visually (leading digits dominate).

**Why document this in DECISIONS.md:** A future developer reading version.php and seeing `2026060100` against an April 2026 commit date will wonder if something is wrong. This entry is the explanation they need. It also guards against well-meaning cleanup commits that "fix the date typo" and break upgrades for every existing deployment.

**Date:** 2026-04-19, formalized with v3.3 release.

**Would revisit if:** A future version-numbering standard emerges that handles this more elegantly. As of April 2026, Moodle's upgrade machinery makes the monotonic-integer constraint load-bearing, so any alternative has to preserve monotonicity.

---

## Known issues (post-v3.3)

### Audit-semantics bug: `send_login_email` return value ignored

**Observation (during v3.3 real-SMTP verification):** `login_controller::handle_request` calls `send_login_email()` which returns bool, but the return value is discarded. When `email_to_user()` returns false (e.g., transient SMTP connect failure), the code still writes `send_link` to the audit log instead of `send_failed`.

**Scope:** Pre-existing bug from v3.0-era code. Not introduced by v3.3. Narrow impact — only visible on SMTP failures, which are relatively rare. Audit log reader sees `send_link` + no corresponding `login_success` and may assume user "didn't click the link," when actually no email was delivered.

**Planned fix:** A future release will check the return of `send_login_email` and emit `send_failed` with the error info. Needs a corresponding test in `login_controller_test` that mocks email failure and asserts the audit state.

**Workaround for now:** Operators troubleshooting "user says they didn't get the email" should check Moodle's main mail error log (`$CFG->dataroot/temp/mailer-debug.log` or similar) to distinguish "email delivered but user ignored it" from "email never left Moodle."
