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
 * Magic link login controller.
 *
 * Thin controller that delegates to service classes. Every outcome
 * path produces the same user-visible message to defeat email enumeration.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// @codingStandardsIgnoreLine
require(__DIR__ . '/../../config.php');

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

// CHANGED from v2: CSRF protection — requires sesskey in form submission.
require_sesskey();

$ip = getremoteaddr();
$uniform = get_string('linksent_uniform', 'auth_magiclink');

// CHANGED from v2: rate limiting on magic-link requests.
$limiter = new \auth_magiclink\rate_limiter();
if (!$limiter->is_allowed($email, $ip)) {
    \auth_magiclink\audit::log(null, $email, 'rate_limited', '', $ip);
    redirect($loginurl, $uniform, null, \core\output\notification::NOTIFY_INFO);
}

// Preserved v2 behavior: domain restriction check.
$filter = new \auth_magiclink\domain_filter();
if (!$filter->is_allowed($email)) {
    // CHANGED from v2: uses $email (fixes S1 $mail typo), uniform message (fixes S3).
    \auth_magiclink\audit::log(null, $email, 'domain_blocked', '', $ip);
    $limiter->record($email, $ip);
    redirect($loginurl, $uniform, null, \core\output\notification::NOTIFY_INFO);
}

// Preserved v2 behavior: user lookup filters deleted=0, suspended=0.
// CHANGED from v2: also requires auth='magiclink' to prevent magic-link
// capture of accounts using other auth methods (e.g., admin with auth='manual').
$user = $DB->get_record('user', ['email' => $email, 'deleted' => 0, 'suspended' => 0]);
if (!$user || $user->auth !== 'magiclink') {
    $action = !$user ? 'no_user' : 'wrong_auth';
    \auth_magiclink\audit::log($user->id ?? null, $email, $action, '', $ip);
    $limiter->record($email, $ip);
    // CHANGED from v2: uniform message regardless of outcome (fixes S3).
    redirect($loginurl, $uniform, null, \core\output\notification::NOTIFY_INFO);
}

// Preserved v2 behavior: generate token, send email, audit log, redirect to login page.
// CHANGED from v2: token is SHA-256 hashed before storage (S4), email has
// separate plaintext/HTML bodies (Q3), user fields HTML-escaped (S7).
try {
    $tm = new \auth_magiclink\token_manager();
    $token = $tm->create_token($user->id, null, 'login');

    $composer = new \auth_magiclink\email_composer();
    $composer->send_login_email($user, $token);

    \auth_magiclink\audit::log($user->id, $email, 'send_link', '', $ip);
    $limiter->record($email, $ip);
} catch (\Exception $e) {
    \auth_magiclink\audit::log($user->id, $email, 'send_failed', $e->getMessage(), $ip);
    $limiter->record($email, $ip);
}

// CHANGED from v2: uniform message for all outcomes (fixes S3 email enumeration).
redirect($loginurl, $uniform, null, \core\output\notification::NOTIFY_INFO);
