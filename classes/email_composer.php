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
     * HTML body and tag-stripped in the plaintext body. Plaintext and HTML
     * bodies are passed to the correct email_to_user() parameters.
     *
     * @param \stdClass $user The recipient user record.
     * @param string $token The plaintext token for the magic link URL.
     * @return bool True if the email was sent successfully.
     */
    public function send_login_email(\stdClass $user, string $token): bool {
        global $SITE;

        $verifyurl = new \moodle_url('/auth/magiclink/verify.php', ['token' => $token]);
        $linkurl = $verifyurl->out(false);
        $ttl = self::format_ttl();

        // Plaintext placeholders — strip HTML from user-controlled fields.
        $textreplacements = [
            '{$a->firstname}' => clean_param($user->firstname, PARAM_TEXT),
            '{$a->lastname}' => clean_param($user->lastname, PARAM_TEXT),
            '{$a->sitename}' => format_string($SITE->fullname),
            '{$a->link}' => $linkurl,
            '{$a->loginlink}' => $linkurl,
            '{$a->expiration}' => $ttl,
        ];

        // HTML placeholders — escape user-controlled fields.
        $htmlreplacements = [
            '{$a->firstname}' => s($user->firstname),
            '{$a->lastname}' => s($user->lastname),
            '{$a->sitename}' => s(format_string($SITE->fullname)),
            '{$a->link}' => s($linkurl),
            '{$a->loginlink}' => \html_writer::link($verifyurl, get_string('loginhere', 'auth_magiclink')),
            '{$a->expiration}' => $ttl,
        ];

        $subject = get_config('auth_magiclink', 'emailsubject');
        if (empty($subject)) {
            $subject = get_string('emailsubject_default', 'auth_magiclink');
        }

        $template = get_config('auth_magiclink', 'emailbody');
        if (empty($template)) {
            $template = get_string('emailbody_default', 'auth_magiclink');
        }

        $bodytext = strtr($template, $textreplacements);
        $bodyhtml = text_to_html(strtr($template, $htmlreplacements), false, false, true);

        $supportuser = \core_user::get_support_user();
        return email_to_user($user, $supportuser, $subject, $bodytext, $bodyhtml);
    }

    /**
     * Format the configured TTL as a human-readable string.
     *
     * @return string The TTL in minutes (e.g., "15").
     */
    public static function format_ttl(): string {
        $ttlseconds = (int)(get_config('auth_magiclink', 'tokenttlseconds')
            ?: token_manager::DEFAULT_TTL_SECONDS);
        return (string)round($ttlseconds / 60);
    }
}
