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

defined('MOODLE_INTERNAL') || die();

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
        throw new \coding_exception('not implemented');
    }

    /**
     * Generate a complete login URL for a user.
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
        throw new \coding_exception('not implemented');
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
        throw new \coding_exception('not implemented');
    }
}
