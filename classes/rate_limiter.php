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
        $cache = $this->get_cache();
        $now = time();

        $emaillimit = (int)(get_config('auth_magiclink', 'ratelimit_email') ?: self::EMAIL_LIMIT);
        $emailwindow = (int)(get_config('auth_magiclink', 'ratelimit_window') ?: self::EMAIL_WINDOW);
        $iplimit = (int)(get_config('auth_magiclink', 'ratelimit_ip') ?: self::IP_LIMIT);
        $ipwindow = $emailwindow;

        $emailcount = $this->count_recent($cache, 'email_' . md5($email), $now, $emailwindow);
        if ($emailcount >= $emaillimit) {
            return false;
        }

        $ipcount = $this->count_recent($cache, 'ip_' . md5($ip), $now, $ipwindow);
        if ($ipcount >= $iplimit) {
            return false;
        }

        return true;
    }

    /**
     * Record a request attempt (increments counters).
     *
     * @param string $email The email address requested.
     * @param string $ip The requester's IP address.
     * @return void
     */
    public function record(string $email, string $ip): void {
        $cache = $this->get_cache();
        $now = time();

        $this->append_timestamp($cache, 'email_' . md5($email), $now);
        $this->append_timestamp($cache, 'ip_' . md5($ip), $now);
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
        $cache = $this->get_cache();
        $cache->delete('email_' . md5($email));
    }

    /**
     * Get the MUC cache instance.
     *
     * @return \cache The rate limit cache.
     */
    private function get_cache(): \cache {
        return \cache::make('auth_magiclink', 'ratelimit');
    }

    /**
     * Count timestamps within the given window.
     *
     * @param \cache $cache The cache instance.
     * @param string $key The cache key.
     * @param int $now Current timestamp.
     * @param int $window Window size in seconds.
     * @return int Number of recent entries.
     */
    private function count_recent(\cache $cache, string $key, int $now, int $window): int {
        $timestamps = $cache->get($key);
        if (!is_array($timestamps)) {
            return 0;
        }
        $cutoff = $now - $window;
        $recent = array_filter($timestamps, function (int $ts) use ($cutoff): bool {
            return $ts >= $cutoff;
        });
        return count($recent);
    }

    /**
     * Append a timestamp to a cache entry, pruning old entries.
     *
     * @param \cache $cache The cache instance.
     * @param string $key The cache key.
     * @param int $now Current timestamp.
     * @return void
     */
    private function append_timestamp(\cache $cache, string $key, int $now): void {
        $maxwindow = (int)(get_config('auth_magiclink', 'ratelimit_window') ?: self::EMAIL_WINDOW);
        $cutoff = $now - $maxwindow;
        $timestamps = $cache->get($key);
        if (!is_array($timestamps)) {
            $timestamps = [];
        }
        // Prune old entries while appending.
        $timestamps = array_filter($timestamps, function (int $ts) use ($cutoff): bool {
            return $ts >= $cutoff;
        });
        $timestamps[] = $now;
        $cache->set($key, $timestamps);
    }
}
