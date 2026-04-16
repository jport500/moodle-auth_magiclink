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
 * Magic link login page and form handler.
 *
 * Handles the email submission form and sends magic link emails.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->libdir/authlib.php");
require_once("$CFG->dirroot/auth/magiclink/lib.php");

$context = context_system::instance();
$PAGE->set_context($context);

// Check if already logged in.
if (isloggedin() && !isguestuser()) {
    redirect($CFG->wwwroot);
}

$email = optional_param('email', '', PARAM_EMAIL);

// Handle POST request.
if ($email) {
    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0, 'suspended' => 0]);
    $ip = getremoteaddr();

    // Domain restriction check.
    $alloweddomains = get_config('auth_magiclink', 'alloweddomains');
    if (!empty($alloweddomains)) {
        $userdomain = strtolower((explode('@', $email))[1]);

        // Remove whitespace and make lowercase before exploding on comma.
        $allowedlist = array_map(function ($domain) {
            return strtolower(trim($domain));
        }, explode(',', $alloweddomains));

        if (!in_array($userdomain, $allowedlist)) {
            add_to_audit_log($user ? $user->id : 0, $mail, 'domain_blocked', "Domain '$userdomain' not allowed.", $ip);

            redirect(
                new moodle_url('/login/index.php'),
                get_string('domainnotallowed', 'auth_magiclink'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }

    if ($user) {
        // Generate token.
        $token = bin2hex(random_bytes(32));
        $expiry = time() + (15 * 60);

        $record = new stdClass();
        $record->userid = $user->id;
        $record->token = $token;
        $record->expires = $expiry;
        $record->used = 0;
        $record->timecreated = time();

        $DB->insert_record('auth_magiclink_token', $record);

        add_to_audit_log($user->id, $email, 'send_link', "Magic link generated.", $ip);

        // Send email with custom template.
        $supportuser = core_user::get_support_user();
        $verifyurl = new moodle_url('/auth/magiclink/verify.php', ['token' => $token]);

        $subject = get_config('auth_magiclink', 'emailsubject');
        if (empty($subject)) {
            $subject = get_string('emailsubject_default', 'auth_magiclink');
        }

        $body = get_config('auth_magiclink', 'emailbody');
        if (empty($body)) {
            $body = get_string('emailbody_default', 'auth_magiclink');
        }

        $linkurl = $verifyurl->out(false);
        $textreplacements = [
            '{$a->firstname}' => $user->firstname,
            '{$a->lastname}' => $user->lastname,
            '{$a->link}' => htmlspecialchars($linkurl),
            '{$a->loginlink}' => '<a href="' . htmlspecialchars($linkurl) . '">Login</a>',
            '{$a->sitename}' => $SITE->fullname,
            '{$a->expiration}' => '15',
        ];

        $bodytext = strtr($body, $textreplacements);
        $user->mailformat = 1;
        email_to_user($user, $supportuser, $subject, $bodytext);

        redirect(
            new moodle_url('/login/index.php'),
            get_string('linksent', 'auth_magiclink', $email),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        add_to_audit_log(0, $email, 'login_failed', "User not found.", $ip);
        redirect(
            new moodle_url('/login/index.php'),
            get_string('invalidemail', 'auth_magiclink'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

redirect(new moodle_url('/login/index.php'));
