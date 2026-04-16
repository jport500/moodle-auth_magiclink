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
 * Magic Link authentication plugin.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/authlib.php');

/**
 * Magic Link authentication plugin class.
 *
 * Provides passwordless authentication via email magic links.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_plugin_magiclink extends auth_plugin_base {
    /**
     * Constructor.
     */
    public function __construct() {
        $this->authtype = 'magiclink';
        $this->config = get_config('auth_magiclink');
    }

    /**
     * Hook to add the magic link form to the login page.
     *
     * Injects the magic link email form and JavaScript for form toggling
     * on the standard Moodle login page. Passes sesskey for CSRF protection.
     *
     * @return void
     */
    public function loginpage_hook() {
        global $CFG, $PAGE, $OUTPUT;

        // Preserved v2 behavior: only show on the actual login page.
        if ($PAGE->pagetype !== 'login-index') {
            return;
        }

        // CHANGED from v2: pass sesskey in template context for CSRF protection.
        $magiclinkhtml = $OUTPUT->render_from_template(
            'auth_magiclink/login_form',
            [
                'actionurl' => "$CFG->wwwroot/auth/magiclink/login.php",
                'sesskey' => sesskey(),
            ]
        );

        // Preserved v2 behavior: inject via AMD JS for progressive enhancement.
        $PAGE->requires->js_call_amd('auth_magiclink/login', 'init', [$magiclinkhtml]);
    }

    /**
     * Returns false as magic link auth does not use passwords.
     *
     * @param string $username The username.
     * @param string $password The password.
     * @return bool Always returns false.
     */
    public function user_login($username, $password) {
        return false;
    }

    /**
     * Returns empty array as we use loginpage_hook instead.
     *
     * @param string $wantsurl The URL the user wants to go to.
     * @return array Empty array.
     */
    public function loginpage_idp_list($wantsurl) {
        return [];
    }

    /**
     * Hook called after user authentication.
     *
     * @param stdClass $user The user object.
     * @param string $username The username.
     * @param string $password The password.
     * @return bool Always returns true.
     */
    public function user_authenticated_hook(&$user, $username, $password) {
        return true;
    }
}
