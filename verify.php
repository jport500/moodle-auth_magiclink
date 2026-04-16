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
 * Magic link token verification page entry point.
 *
 * Thin page boundary — all business logic is in verify_controller.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php'); // phpcs:ignore moodle.Files.RequireLogin.Missing

$token = required_param('token', PARAM_ALPHANUM);
$wantsurl = optional_param('wantsurl', '', PARAM_RAW);

$result = \auth_magiclink\verify_controller::handle_verify($token, getremoteaddr(), $wantsurl);

if (!empty($result['message'])) {
    redirect($result['url'], $result['message'], null, $result['messagetype']);
} else {
    redirect($result['url']);
}
