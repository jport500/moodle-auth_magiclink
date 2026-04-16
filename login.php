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
 * Magic link login page entry point.
 *
 * Thin page boundary — all business logic is in login_controller.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php'); // phpcs:ignore moodle.Files.RequireLogin.Missing

// Preserved v2 behavior: redirect already-logged-in users to wwwroot.
if (isloggedin() && !isguestuser()) {
    redirect($CFG->wwwroot);
}

$email = optional_param('email', '', PARAM_EMAIL);
$loginurl = new moodle_url('/login/index.php');

// Preserved v2 behavior: GET requests (or POST with empty email) redirect to login page.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($email)) {
    redirect($loginurl);
}

// CHANGED from v2: CSRF protection.
require_sesskey();

$result = \auth_magiclink\login_controller::handle_request($email, getremoteaddr());
redirect($result['url'], $result['message'], null, $result['messagetype']);
