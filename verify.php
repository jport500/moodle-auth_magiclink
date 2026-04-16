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
 * Magic link token verification page.
 *
 * Validates the magic link token and logs the user in.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/authlib.php');
require_once("$CFG->dirroot/auth/magiclink/lib.php");

$token = required_param('token', PARAM_ALPHANUM);
$ip = getremoteaddr();

$record = $DB->get_record('auth_magiclink_token', ['token' => $token]);

if ($record && $record->used == 0 && $record->expires > time()) {
    $user = $DB->get_record('user', ['id' => $record->userid]);

    if ($user) {
        $record->used = 1;
        $DB->update_record('auth_magiclink_token', $record);

        add_to_audit_log($user->id, $user->email, 'login_success', "User logged in via magic link.", $ip);
        complete_user_login($user);
        $url = $SESSION->wantsurl ?? $CFG->wwwroot;
        redirect($url);
    }
}

// Audit log: Login failed (expired or used token).
$useremail = 'unknown';
if ($record && isset($record->userid)) {
    $useremail = $DB->get_field('user', 'email', ['id' => $record->userid]) ?: 'unknown';
}

$info = 'Token expired.';
if (!$record) {
    $info = 'Token not found.';
} else if ($record->used) {
    $info = 'Token already used.';
}

add_to_audit_log($record ? $record->userid : 0, $useremail, 'login_failed', $info, $ip);

redirect(
    new moodle_url('/login/index.php'),
    get_string('expired', 'auth_magiclink'),
    null,
    \core\output\notification::NOTIFY_ERROR
);
