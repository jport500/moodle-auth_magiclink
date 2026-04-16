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
 * Email composer for magic link messages.
 *
 * Composes and sends magic link login emails with properly escaped
 * placeholders. Provides separate plaintext and HTML bodies.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

defined('MOODLE_INTERNAL') || die();

/**
 * Composes and sends magic link emails.
 */
class email_composer {

    /**
     * Send a magic link login email to a user.
     *
     * User-controlled fields (firstname, lastname) are HTML-escaped in the
     * HTML body. Plaintext and HTML bodies are passed to the correct
     * email_to_user() parameters.
     *
     * @param \stdClass $user The recipient user record.
     * @param string $token The plaintext token for the magic link URL.
     * @return bool True if the email was sent successfully.
     */
    public function send_login_email(\stdClass $user, string $token): bool {
        throw new \coding_exception('not implemented');
    }

    /**
     * Format the configured TTL as a human-readable string.
     *
     * @return string The TTL in minutes (e.g., "15").
     */
    public static function format_ttl(): string {
        throw new \coding_exception('not implemented');
    }
}
