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
 * Event observer for auth_magiclink.
 *
 * Revokes active magic link tokens when user state changes in ways
 * that should invalidate authentication (suspension, deletion,
 * password change, auth method change). Profile-only edits (name,
 * timezone, avatar) are deliberately ignored.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * Observes core user events and revokes tokens as needed.
 */
class observer {
    /**
     * Handle user_updated event.
     *
     * Revokes tokens only if the user is now suspended or their auth
     * method is no longer 'magiclink'. Profile-only edits are ignored
     * to avoid UX regressions (user changes timezone, tokens survive).
     *
     * The event provides no indication of which fields changed
     * (create_from_userid only sets objectid/context), so we check
     * the current user record state.
     *
     * @param \core\event\user_updated $event The event instance.
     * @return void
     */
    public static function user_updated(\core\event\user_updated $event): void {
        global $DB;

        $userid = $event->objectid;
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return;
        }

        if ((int)$user->suspended === 1 || $user->auth !== 'magiclink') {
            $tm = new token_manager();
            $tm->revoke_all_for_user($userid);
        }
    }

    /**
     * Handle user_deleted event.
     *
     * Revokes all tokens for the deleted user.
     *
     * @param \core\event\user_deleted $event The event instance.
     * @return void
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        $tm = new token_manager();
        $tm->revoke_all_for_user($event->objectid);
    }

    /**
     * Handle user_password_updated event.
     *
     * Revokes all tokens. A password change suggests the user or an
     * admin wants to re-assert identity — outstanding magic links
     * should not bypass that intent.
     *
     * @param \core\event\user_password_updated $event The event instance.
     * @return void
     */
    public static function user_password_updated(\core\event\user_password_updated $event): void {
        $tm = new token_manager();
        $tm->revoke_all_for_user($event->relateduserid);
    }
}
