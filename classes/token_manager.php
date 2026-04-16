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
        global $DB;

        $this->validate_user($userid);

        $ttl = $this->resolve_ttl($ttlseconds);

        $this->revoke_all_for_user($userid);

        $plaintext = $this->generate_plaintext();
        $record = new \stdClass();
        $record->userid = $userid;
        $record->token = $this->hash($plaintext);
        $record->expires = time() + $ttl;
        $record->used = 0;
        $record->timecreated = time();
        $DB->insert_record('auth_magiclink_token', $record);

        return $plaintext;
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
        global $DB;

        $hash = $this->hash($token);
        $record = $DB->get_record('auth_magiclink_token', ['token' => $hash]);

        if (!$record) {
            throw new \moodle_exception('tokeninvalid', 'auth_magiclink');
        }
        if ((int)$record->used !== 0) {
            throw new \moodle_exception('tokenused', 'auth_magiclink');
        }
        if ((int)$record->expires <= time()) {
            throw new \moodle_exception('tokenexpired', 'auth_magiclink');
        }

        $user = $DB->get_record('user', [
            'id' => $record->userid,
            'deleted' => 0,
            'suspended' => 0,
        ]);
        if (!$user || isguestuser($user)) {
            throw new \moodle_exception('userinactive', 'auth_magiclink');
        }

        $DB->set_field('auth_magiclink_token', 'used', 1, ['id' => $record->id]);

        return $user;
    }

    /**
     * Revoke all active (unused) tokens for a user.
     *
     * Idempotent — safe to call even if no tokens exist.
     *
     * @param int $userid The user whose tokens should be revoked.
     * @return int Number of tokens revoked.
     */
    public function revoke_all_for_user(int $userid): int {
        global $DB;

        $count = $DB->count_records('auth_magiclink_token', [
            'userid' => $userid,
            'used' => 0,
        ]);

        if ($count > 0) {
            $DB->set_field('auth_magiclink_token', 'used', 1, [
                'userid' => $userid,
                'used' => 0,
            ]);
        }

        return $count;
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
        global $DB;

        $cutoff = time() - $olderthansec;
        $count = $DB->count_records_select(
            'auth_magiclink_token',
            'expires < :cutoff',
            ['cutoff' => $cutoff]
        );

        if ($count > 0) {
            $DB->delete_records_select(
                'auth_magiclink_token',
                'expires < :cutoff',
                ['cutoff' => $cutoff]
            );
        }

        return $count;
    }

    /**
     * Validate that a user ID references a usable account.
     *
     * @param int $userid The user ID to validate.
     * @throws \moodle_exception If the user is deleted, suspended, guest, or nonexistent.
     */
    private function validate_user(int $userid): void {
        global $DB;

        $user = $DB->get_record('user', [
            'id' => $userid,
            'deleted' => 0,
            'suspended' => 0,
        ]);

        if (!$user || isguestuser($user)) {
            throw new \moodle_exception('invaliduser', 'auth_magiclink');
        }
    }

    /**
     * Resolve the effective TTL from caller, config, or default.
     *
     * @param int|null $ttlseconds Caller-provided TTL, or null.
     * @return int Effective TTL in seconds, capped at MAX_TTL_SECONDS.
     */
    private function resolve_ttl(?int $ttlseconds): int {
        if ($ttlseconds === null) {
            $configured = get_config('auth_magiclink', 'tokenttlseconds');
            $ttl = !empty($configured) ? (int)$configured : self::DEFAULT_TTL_SECONDS;
        } else {
            $ttl = $ttlseconds;
        }

        return min(max($ttl, 1), self::MAX_TTL_SECONDS);
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
