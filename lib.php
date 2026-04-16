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
 * Magic Link lib functions
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generate a log record for the plugin. TODO: get rid of this and use logstore_standard_log
 * @param int $userid: The ID of the user related to the record
 * @param string $email: The user's email
 * @param string $action: The id of the action being logged
 * @param string $info: A human-readable string describing the action
 * @param string $ip: The IP address of the user
 */
function add_to_audit_log($userid, $email, $action, $info, $ip) {
    global $DB;

    $audit = new stdClass();
    $audit->userid = $userid;
    $audit->email = $email;
    $audit->action = $action;
    $audit->ip = $ip;
    $audit->info = $info;
    $audit->timecreated = time();
    $DB->insert_record('auth_magiclink_audit', $audit);
}
