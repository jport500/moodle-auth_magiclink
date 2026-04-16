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
 * Revokes active magic link tokens when user state changes
 * (updated, deleted, password changed) to prevent stale tokens
 * from granting access to suspended or deleted accounts.
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
     * Revokes tokens if the user was suspended or auth method changed.
     *
     * @param \core\event\user_updated $event The event instance.
     * @return void
     */
    public static function user_updated(\core\event\user_updated $event): void {
        throw new \coding_exception('not implemented');
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
        throw new \coding_exception('not implemented');
    }

    /**
     * Handle user_password_updated event.
     *
     * Revokes all tokens — password change should invalidate magic links.
     *
     * @param \core\event\user_password_updated $event The event instance.
     * @return void
     */
    public static function user_password_updated(\core\event\user_password_updated $event): void {
        throw new \coding_exception('not implemented');
    }
}
