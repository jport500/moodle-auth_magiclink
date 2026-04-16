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
 * Rate limiter for magic link requests.
 *
 * Enforces per-email and per-IP request limits within a configurable
 * time window. Uses Moodle cache (MUC) for counter storage.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

defined('MOODLE_INTERNAL') || die();

/**
 * Rate limiter for magic link token requests.
 */
class rate_limiter {

    /** @var int Default max requests per email per window. */
    const EMAIL_LIMIT = 3;

    /** @var int Default rate limit window in seconds. */
    const EMAIL_WINDOW = 900;

    /** @var int Default max requests per IP per window. */
    const IP_LIMIT = 10;

    /** @var int Default IP rate limit window in seconds. */
    const IP_WINDOW = 900;

    /**
     * Check whether a magic-link request from (email, ip) is allowed.
     *
     * Does NOT record the attempt — call record() separately on success.
     *
     * @param string $email The email address being requested.
     * @param string $ip The requester's IP address.
     * @return bool True if allowed, false if throttled.
     */
    public function is_allowed(string $email, string $ip): bool {
        throw new \coding_exception('not implemented');
    }

    /**
     * Record a request attempt (increments counters).
     *
     * @param string $email The email address requested.
     * @param string $ip The requester's IP address.
     * @return void
     */
    public function record(string $email, string $ip): void {
        throw new \coding_exception('not implemented');
    }

    /**
     * Reset rate limits for a specific email.
     *
     * Used by admin actions.
     *
     * @param string $email The email address to reset limits for.
     * @return void
     */
    public function reset(string $email): void {
        throw new \coding_exception('not implemented');
    }
}
