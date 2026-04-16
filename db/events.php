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
 * Event observers for auth_magiclink.
 *
 * Revokes active tokens when user state changes (suspension, deletion,
 * password change) to prevent stale tokens from granting access.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_updated',
        'callback' => '\auth_magiclink\observer::user_updated',
        'internal' => false,
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback' => '\auth_magiclink\observer::user_deleted',
        'internal' => false,
    ],
    [
        'eventname' => '\core\event\user_password_updated',
        'callback' => '\auth_magiclink\observer::user_password_updated',
        'internal' => false,
    ],
];
