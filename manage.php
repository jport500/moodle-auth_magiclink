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
 * Magic link management page.
 *
 * Admin interface for managing tokens and viewing audit logs.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();
require_capability('moodle/site:config', $context);
$PAGE->set_url(new moodle_url('/auth/magiclink/manage.php'));

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

// Handle actions.
if ($action && $id && confirm_sesskey()) {
    if ($action === 'revoke') {
        $DB->set_field('auth_magiclink_token', 'used', 1, ['id' => $id]);
        redirect(
            $PAGE->url,
            get_string('linkrevoked', 'auth_magiclink'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    if ($action === 'extend') {
        // Extend by 15 mins from now.
        $newexpiry = time() + (15 * 60);
        $DB->set_field('auth_magiclink_token', 'expires', $newexpiry, ['id' => $id]);
        redirect(
            $PAGE->url,
            get_string('linkextended', 'auth_magiclink'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    if ($action === 'delete') {
        $DB->delete_records('auth_magiclink_token', ['id' => $id]);
        redirect(
            $PAGE->url,
            get_string('linkdeleted', 'auth_magiclink'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managelinks', 'auth_magiclink'));
$PAGE->set_heading(get_string('managelinks', 'auth_magiclink'));
$PAGE->requires->js_call_amd('auth_magiclink/manage', 'init');

echo $OUTPUT->header();

$sql = "
    SELECT 
        t.id, t.userid, t.expires, t.timecreated, 
        CONCAT(u.firstname, ' ', u.lastname) as fullname, 
        u.email,
        CASE
            WHEN used = 1 THEN 'used'
            ELSE 'unused'
        END AS used
    FROM {auth_magiclink_token} t
    JOIN {user} u ON t.userid = u.id
    ORDER BY t.timecreated DESC";
$tokens = $DB->get_records_sql($sql, null, 0, 100);

foreach ($tokens as $token) {
    if ($token->used === 'used') {
        $token->actions = ['delete'];
    } else {
        $token->actions = ['extend', 'revoke'];
    }
}

$sql = "SELECT a.*, u.firstname, u.lastname
             FROM {auth_magiclink_audit} a
             LEFT JOIN {user} u ON a.userid = u.id
             ORDER BY a.timecreated DESC";
$audits = $DB->get_records_sql($sql, null, 0, 100);

echo $OUTPUT->render_from_template(
    'auth_magiclink/manage',
    [
        'hastokens' => count($tokens) > 0,
        'tokens' => array_values($tokens),
        'hasaudits' => count($audits) > 0,
        'audits' => array_values($audits)
    ]
);

echo $OUTPUT->footer();
