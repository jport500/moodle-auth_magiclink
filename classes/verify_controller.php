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
 * Token verification controller for auth_magiclink.
 *
 * Verifies a magic-link token, logs the user in via complete_user_login(),
 * and returns a redirect descriptor. All failure modes produce the same
 * generic user-visible error to prevent token-state probing.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * Handles magic-link token verification and login.
 */
class verify_controller {
    /**
     * Verify a token and log the user in.
     *
     * On success, calls complete_user_login() to establish the session,
     * then returns a redirect to the post-login destination.
     *
     * On any failure (invalid, expired, used, inactive user), returns a
     * redirect to the login page with a generic error notification. The
     * specific failure reason is recorded in the audit log only.
     *
     * @param string $token The plaintext token from the URL.
     * @param string $ip The requester's IP address.
     * @param string $wantsurlparam The wantsurl query parameter (may be empty).
     * @param token_manager|null $tm Injectable for testing; null uses real instance.
     * @return array{url: string, message: string, messagetype: string, loggedin: bool}
     */
    public static function handle_verify(
        string $token,
        string $ip,
        string $wantsurlparam = '',
        ?token_manager $tm = null
    ): array {
        global $CFG, $SESSION;

        $tm = $tm ?? new token_manager();
        $loginurl = new \moodle_url('/login/index.php');
        $genericerror = get_string('tokennotvalid', 'auth_magiclink');

        try {
            $user = $tm->verify_and_consume($token);
        } catch (\moodle_exception $e) {
            $reason = self::classify_failure($e);
            $userid = self::resolve_userid_for_audit($token, $tm);
            $email = self::resolve_email_for_audit($userid);
            audit::log($userid, $email, 'login_failed', $reason, $ip);

            return [
                'url' => $loginurl,
                'message' => $genericerror,
                'messagetype' => \core\output\notification::NOTIFY_ERROR,
                'loggedin' => false,
            ];
        }

        audit::log($user->id, $user->email, 'login_success', '', $ip);
        @complete_user_login($user);

        $destination = self::resolve_destination($wantsurlparam);

        return [
            'url' => $destination,
            'message' => '',
            'messagetype' => '',
            'loggedin' => true,
        ];
    }

    /**
     * Determine the post-login redirect destination.
     *
     * Priority: wantsurl query param (if local) → SESSION wantsurl → wwwroot.
     *
     * @param string $wantsurlparam The wantsurl from the verify URL query string.
     * @return string The destination URL.
     */
    private static function resolve_destination(string $wantsurlparam): string {
        global $CFG, $SESSION;

        if (!empty($wantsurlparam)) {
            $local = clean_param($wantsurlparam, PARAM_LOCALURL);
            if (!empty($local)) {
                return $local;
            }
        }

        if (!empty($SESSION->wantsurl)) {
            $url = $SESSION->wantsurl;
            unset($SESSION->wantsurl);
            return $url;
        }

        return $CFG->wwwroot;
    }

    /**
     * Classify a verification failure for the audit log.
     *
     * @param \moodle_exception $e The exception from verify_and_consume.
     * @return string Human-readable failure reason for audit.
     */
    private static function classify_failure(\moodle_exception $e): string {
        $errorcode = $e->errorcode ?? '';
        $map = [
            'tokeninvalid' => 'Token not found.',
            'tokenexpired' => 'Token expired.',
            'tokenused' => 'Token already used.',
            'userinactive' => 'User inactive.',
        ];
        return $map[$errorcode] ?? 'Verification failed.';
    }

    /**
     * Try to resolve the userid from the token hash for audit logging on failure.
     *
     * @param string $token The plaintext token.
     * @param token_manager $tm The token manager (unused; DB lookup done directly).
     * @return int|null The userid if the token record exists, else null.
     */
    private static function resolve_userid_for_audit(string $token, token_manager $tm): ?int {
        global $DB;
        $hash = hash('sha256', $token);
        $record = $DB->get_record('auth_magiclink_token', ['token' => $hash]);
        return $record ? (int)$record->userid : null;
    }

    /**
     * Resolve an email address for audit logging.
     *
     * @param int|null $userid The user ID, or null if unknown.
     * @return string The email address, or 'unknown'.
     */
    private static function resolve_email_for_audit(?int $userid): string {
        global $DB;
        if ($userid === null) {
            return 'unknown';
        }
        return $DB->get_field('user', 'email', ['id' => $userid]) ?: 'unknown';
    }
}
