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
 * Domain filter for magic link requests.
 *
 * Checks whether an email address belongs to an allowed domain,
 * based on the configured allowlist. Empty allowlist permits all domains.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

defined('MOODLE_INTERNAL') || die();

/**
 * Validates email domains against the configured allowlist.
 */
class domain_filter {

    /**
     * Check whether the email's domain is permitted.
     *
     * Returns true if the allowlist is empty (all domains allowed)
     * or if the email's domain is in the allowlist.
     *
     * @param string $email The email address to check.
     * @return bool True if the domain is allowed.
     */
    public function is_allowed(string $email): bool {
        throw new \coding_exception('not implemented');
    }
}
