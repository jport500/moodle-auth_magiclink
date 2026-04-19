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
 * Login request controller for auth_magiclink.
 *
 * Contains all business logic between sesskey validation and the final
 * redirect. Returns a redirect descriptor so login.php can issue the
 * actual redirect — this keeps the controller fully testable (Moodle's
 * redirect() throws in CLI/PHPUnit without storing the notification).
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * Handles magic-link login requests.
 */
class login_controller {
    /**
     * Process a magic-link login request.
     *
     * Every outcome path returns the same user-visible message and
     * notification type to defeat email enumeration. The real reason
     * is recorded in the audit log.
     *
     * @param string $email The submitted email address (already PARAM_EMAIL cleaned).
     * @param string $ip The requester's IP address.
     * @param rate_limiter|null $limiter Injectable for testing; null uses real instance.
     * @param domain_filter|null $filter Injectable for testing; null uses real instance.
     * @param token_manager|null $tm Injectable for testing; null uses real instance.
     * @param email_composer|null $composer Injectable for testing; null uses real instance.
     * @return array{url: \moodle_url, message: string, messagetype: string}
     */
    public static function handle_request(
        string $email,
        string $ip,
        ?rate_limiter $limiter = null,
        ?domain_filter $filter = null,
        ?token_manager $tm = null,
        ?email_composer $composer = null
    ): array {
        global $DB;

        $limiter = $limiter ?? new rate_limiter();
        $filter = $filter ?? new domain_filter();
        $tm = $tm ?? new token_manager();
        $composer = $composer ?? new email_composer();

        $loginurl = new \moodle_url('/login/index.php');
        $uniform = get_string('linksent_uniform', 'auth_magiclink');
        $uniformresult = [
            'url' => $loginurl,
            'message' => $uniform,
            'messagetype' => \core\output\notification::NOTIFY_INFO,
        ];

        // Rate limiting.
        if (!$limiter->is_allowed($email, $ip)) {
            audit::log(null, $email, 'rate_limited', '', $ip);
            return $uniformresult;
        }

        // Domain restriction.
        if (!$filter->is_allowed($email)) {
            audit::log(null, $email, 'domain_blocked', '', $ip);
            $limiter->record($email, $ip);
            return $uniformresult;
        }

        // User lookup — must exist, be active, and have an auth method
        // on the v3.3 allowlist (api::is_auth_allowed — see classes/api.php
        // for the three-state logic).
        $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0, 'suspended' => 0]);
        if (!$user || !api::is_auth_allowed($user)) {
            $action = !$user ? 'no_user' : 'wrong_auth';
            audit::log($user->id ?? null, $email, $action, '', $ip);
            $limiter->record($email, $ip);
            return $uniformresult;
        }

        // Generate token and send email.
        try {
            $token = $tm->create_token($user->id, null, 'login');
            $composer->send_login_email($user, $token);
            audit::log($user->id, $email, 'send_link', '', $ip);
            $limiter->record($email, $ip);
        } catch (\Exception $e) {
            audit::log($user->id, $email, 'send_failed', $e->getMessage(), $ip);
            $limiter->record($email, $ip);
        }

        return $uniformresult;
    }
}
