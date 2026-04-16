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
 * Token lifecycle manager for auth_magiclink.
 *
 * Single source of truth for token creation, verification, revocation,
 * and pruning. Tokens are hashed with SHA-256 before storage; plaintext
 * is returned to the caller exactly once and never read back from the DB.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages the full token lifecycle: create, verify, revoke, prune.
 */
class token_manager {

    /** @var int Token string length in hex characters. */
    const TOKEN_HEX_LENGTH = 64;

    /** @var int Default TTL if no setting is configured (15 minutes). */
    const DEFAULT_TTL_SECONDS = 900;

    /** @var int Upper bound on any caller-supplied TTL (30 days). */
    const MAX_TTL_SECONDS = 2592000;

    /**
     * Create a token for a user, persist the hash, return the plaintext token.
     *
     * Revokes any existing unused tokens for the same user before creating
     * the new one, preventing token-table bloat under attack.
     *
     * @param int $userid Must reference an existing, non-deleted, non-guest, non-suspended user.
     * @param int|null $ttlseconds Null uses the configured or default TTL. Capped at MAX_TTL_SECONDS.
     * @param string $purpose Tagged in audit log ('login', 'welcome', etc.).
     * @return string 64-char hex token (plaintext, for inclusion in URL).
     * @throws \moodle_exception If user is invalid.
     */
    public function create_token(int $userid, ?int $ttlseconds, string $purpose): string {
        throw new \coding_exception('not implemented');
    }

    /**
     * Verify and consume a token.
     *
     * Hashes the incoming plaintext, looks up the hash in the DB, validates
     * the token is unused and unexpired, and checks the user is still active.
     *
     * @param string $token Plaintext token from URL.
     * @return \stdClass The user record on success.
     * @throws \moodle_exception tokeninvalid, tokenexpired, tokenused, or userinactive.
     */
    public function verify_and_consume(string $token): \stdClass {
        throw new \coding_exception('not implemented');
    }

    /**
     * Revoke all active (unused, unexpired) tokens for a user.
     *
     * Idempotent — safe to call even if no tokens exist.
     *
     * @param int $userid The user whose tokens should be revoked.
     * @return int Number of tokens revoked.
     */
    public function revoke_all_for_user(int $userid): int {
        throw new \coding_exception('not implemented');
    }

    /**
     * Prune expired tokens older than the given threshold.
     *
     * Intended to be called by a scheduled task.
     *
     * @param int $olderthansec Remove tokens expired more than this many seconds ago.
     * @return int Number of rows deleted.
     */
    public function prune_expired(int $olderthansec = 2592000): int {
        throw new \coding_exception('not implemented');
    }

    /**
     * Hash a plaintext token with SHA-256.
     *
     * @param string $plaintext The raw token string.
     * @return string 64-char hex SHA-256 hash.
     */
    private function hash(string $plaintext): string {
        return hash('sha256', $plaintext);
    }

    /**
     * Generate a cryptographically secure plaintext token.
     *
     * @return string 64-char hex string.
     */
    private function generate_plaintext(): string {
        return bin2hex(random_bytes(32));
    }
}
