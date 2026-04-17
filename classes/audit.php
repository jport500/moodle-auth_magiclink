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
 * Audit logger for auth_magiclink.
 *
 * Replaces the global add_to_audit_log() function from v2.
 * Writes structured records to the auth_magiclink_audit table.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * Structured audit logging for magic link events.
 */
class audit {
    /**
     * Write an audit log entry.
     *
     * @param int|null $userid The user ID, or null if unknown.
     * @param string $email The email address involved.
     * @param string $action The action identifier (e.g., 'send_link', 'login_success').
     * @param string $info Human-readable description.
     * @param string $ip The requester's IP address.
     * @return void
     */
    public static function log(?int $userid, string $email, string $action, string $info, string $ip): void {
        global $DB;

        $record = new \stdClass();
        $record->userid = $userid ?? 0;
        $record->email = $email;
        $record->action = $action;
        $record->info = $info;
        $record->ip = $ip;
        $record->timecreated = time();
        $DB->insert_record('auth_magiclink_audit', $record);
    }
}
