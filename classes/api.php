<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Public API façade for auth_magiclink.
 *
 * Stable contract for external callers (e.g., local_welcomeemail).
 * All methods delegate to internal service classes.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * Public API for generating and managing magic link tokens.
 */
class api {
    /** @var int Maximum TTL for any token. */
    const MAX_TTL_SECONDS = token_manager::MAX_TTL_SECONDS;

    /**
     * Generate a token for a user and return the plaintext.
     *
     * @param int $userid The target user ID.
     * @param int|null $ttlseconds TTL override; null uses the configured default.
     * @param string $purpose Purpose tag for audit logging.
     * @return string 64-char hex plaintext token.
     * @throws \moodle_exception If the user is invalid.
     */
    public static function generate_token_for_user(
        int $userid,
        ?int $ttlseconds = null,
        string $purpose = 'login'
    ): string {
        $tm = new token_manager();
        return $tm->create_token($userid, $ttlseconds, $purpose);
    }

    /**
     * Generate a complete login URL for a user.
     *
     * When $wantsurl is null, the wantsurl parameter is omitted from the
     * returned URL entirely (not set to an empty string). When $wantsurl
     * is provided but fails PARAM_LOCALURL validation (e.g., external URL),
     * it is silently dropped.
     *
     * @param int $userid The target user ID.
     * @param int|null $ttlseconds TTL override; null uses the configured default.
     * @param \moodle_url|null $wantsurl Post-login redirect URL (local only; external URLs rejected).
     * @param string $purpose Purpose tag for audit logging.
     * @return \moodle_url The verify URL with the token query parameter.
     * @throws \moodle_exception If the user is invalid.
     */
    public static function generate_login_url_for_user(
        int $userid,
        ?int $ttlseconds = null,
        ?\moodle_url $wantsurl = null,
        string $purpose = 'login'
    ): \moodle_url {
        $token = self::generate_token_for_user($userid, $ttlseconds, $purpose);

        $params = ['token' => $token];

        if ($wantsurl !== null) {
            $raw = $wantsurl->out(false);
            $local = clean_param($raw, PARAM_LOCALURL);
            if (!empty($local)) {
                $params['wantsurl'] = $local;
            }
        }

        return new \moodle_url('/auth/magiclink/verify.php', $params);
    }

    /**
     * Revoke all active tokens for a user.
     *
     * Idempotent — safe to call even if no tokens exist.
     *
     * @param int $userid The user whose tokens should be revoked.
     * @return int Number of tokens revoked.
     */
    public static function revoke_user_tokens(int $userid): int {
        $tm = new token_manager();
        return $tm->revoke_all_for_user($userid);
    }

    /**
     * Check whether a user's auth method is on the magic-link allowlist.
     *
     * Three states for the stored config (spec §5.3 of the v3.3 design
     * captured in docs/DECISIONS.md):
     *
     *   - Unset (false): fresh install that never visited settings. Fall
     *     back to all currently-enabled auth plugins — permissive
     *     fresh-install default without requiring a write.
     *   - Empty string: admin deliberately saved an empty selection.
     *     Honor it — allowlist is empty, nobody qualifies. This is the
     *     lockdown case ("temporarily disable magic links for everyone").
     *   - Comma-separated: the normal case. Parse and check membership.
     *
     * Admin exclusion is layered on top of this check by the login and
     * verify controllers (Phase 3).
     *
     * @param \stdClass $user A user record with an `auth` field.
     * @return bool True if the user's auth method is allowed.
     */
    public static function is_auth_allowed(\stdClass $user): bool {
        $raw = get_config('auth_magiclink', 'allowed_auth_methods');
        if ($raw === false) {
            $allowed = array_keys(\core\plugininfo\auth::get_enabled_plugins() ?: []);
        } else {
            $allowed = $raw === '' ? [] : explode(',', $raw);
        }
        return in_array($user->auth, $allowed, true);
    }
}
