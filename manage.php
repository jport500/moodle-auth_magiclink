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
 * Magic link management page entry point.
 *
 * Admin interface for managing tokens and viewing audit logs.
 * Registered as admin_externalpage 'auth_magiclink_manage' in settings.php.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// @codingStandardsIgnoreLine
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$tokenpage = optional_param('tokenpage', 0, PARAM_INT);
$auditpage = optional_param('auditpage', 0, PARAM_INT);

// Preserved v2 behavior: handle actions before page output.
// sesskey checked via confirm_sesskey() — same CSRF pattern as v2.
if ($action && $id && confirm_sesskey()) {
    $msgkey = \auth_magiclink\manage_controller::execute_action($action, $id);
    redirect(
        new moodle_url('/auth/magiclink/manage.php'),
        get_string($msgkey, 'auth_magiclink'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// CHANGED from v2: uses admin_externalpage_setup (fixes M6) with
// auth/magiclink:manage capability instead of moodle/site:config.
$manageurl = new moodle_url('/auth/magiclink/manage.php');
admin_externalpage_setup('auth_magiclink_manage', '', null, $manageurl);

$PAGE->requires->js_call_amd('auth_magiclink/manage', 'init');

echo $OUTPUT->header();

// Token section with pagination.
$perpage = \auth_magiclink\manage_controller::PER_PAGE;
$tokendata = \auth_magiclink\manage_controller::list_tokens($tokenpage, $perpage);
$auditdata = \auth_magiclink\manage_controller::list_audits($auditpage, $perpage);

$baseurl = new moodle_url('/auth/magiclink/manage.php');

echo $OUTPUT->render_from_template('auth_magiclink/manage', [
    'hastokens' => !empty($tokendata['tokens']),
    'tokens' => $tokendata['tokens'],
    'notokens' => get_string('notokens', 'auth_magiclink'),
    'hasaudits' => !empty($auditdata['audits']),
    'audits' => $auditdata['audits'],
    'noaudits' => get_string('noaudits', 'auth_magiclink'),
]);

if ($tokendata['total'] > $perpage) {
    echo $OUTPUT->paging_bar($tokendata['total'], $tokenpage, $perpage, $baseurl, 'tokenpage');
}

if ($auditdata['total'] > $perpage) {
    echo $OUTPUT->paging_bar($auditdata['total'], $auditpage, $perpage, $baseurl, 'auditpage');
}

echo $OUTPUT->footer();
