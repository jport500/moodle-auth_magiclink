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
 * Management page controller for auth_magiclink.
 *
 * Provides query methods for the admin token/audit management page.
 * Extracted from manage.php for testability and portability (fixes S8).
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * Query and action logic for the manage page.
 */
class manage_controller {
    /** @var int Default rows per page. */
    const PER_PAGE = 25;

    /**
     * Get paginated token records with user fullname.
     *
     * Uses $DB->sql_fullname() for portable name concatenation (fixes S8).
     *
     * @param int $page Zero-based page number.
     * @param int $perpage Rows per page.
     * @return array{tokens: array, total: int}
     */
    public static function list_tokens(int $page = 0, int $perpage = self::PER_PAGE): array {
        global $DB;

        $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        $sql = "SELECT t.id, t.userid, t.expires, t.timecreated, t.used,
                       {$fullname} AS fullname, u.email
                  FROM {auth_magiclink_token} t
                  JOIN {user} u ON t.userid = u.id
              ORDER BY t.timecreated DESC";

        $total = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {auth_magiclink_token} t JOIN {user} u ON t.userid = u.id"
        );
        $records = $DB->get_records_sql($sql, null, $page * $perpage, $perpage);

        $tokens = [];
        foreach ($records as $token) {
            $token->statuslabel = ((int)$token->used === 1)
                ? get_string('token_status_used', 'auth_magiclink')
                : get_string('token_status_unused', 'auth_magiclink');
            $token->statuskey = ((int)$token->used === 1) ? 'used' : 'unused';
            if ((int)$token->used === 1) {
                $token->actions = [['action' => 'delete']];
            } else {
                $token->actions = [['action' => 'extend'], ['action' => 'revoke']];
            }
            // Pass token id into each action for the template.
            foreach ($token->actions as &$act) {
                $act['id'] = $token->id;
            }
            $tokens[] = $token;
        }

        return ['tokens' => $tokens, 'total' => (int)$total];
    }

    /**
     * Get paginated audit log records.
     *
     * @param int $page Zero-based page number.
     * @param int $perpage Rows per page.
     * @return array{audits: array, total: int}
     */
    public static function list_audits(int $page = 0, int $perpage = self::PER_PAGE): array {
        global $DB;

        $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        $sql = "SELECT a.id, a.userid, a.email, a.action, a.ip, a.info, a.timecreated,
                       {$fullname} AS fullname
                  FROM {auth_magiclink_audit} a
             LEFT JOIN {user} u ON a.userid = u.id
              ORDER BY a.timecreated DESC";

        $total = $DB->count_records('auth_magiclink_audit');
        $records = $DB->get_records_sql($sql, null, $page * $perpage, $perpage);

        return ['audits' => array_values($records), 'total' => (int)$total];
    }

    /**
     * Execute a token management action.
     *
     * @param string $action One of 'revoke', 'extend', 'delete'.
     * @param int $id The token record ID.
     * @return string The notification lang string key.
     * @throws \moodle_exception If the action is unknown.
     */
    public static function execute_action(string $action, int $id): string {
        global $DB;

        switch ($action) {
            case 'revoke':
                $DB->set_field('auth_magiclink_token', 'used', 1, ['id' => $id]);
                return 'linkrevoked';
            case 'extend':
                $newexpiry = time() + (15 * 60);
                $DB->set_field('auth_magiclink_token', 'expires', $newexpiry, ['id' => $id]);
                return 'linkextended';
            case 'delete':
                $DB->delete_records('auth_magiclink_token', ['id' => $id]);
                return 'linkdeleted';
            default:
                throw new \moodle_exception('invalidaction', 'auth_magiclink');
        }
    }
}
