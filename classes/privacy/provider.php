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
 * Privacy API provider for auth_magiclink.
 *
 * Declares and handles personal data stored in auth_magiclink_token
 * and auth_magiclink_audit tables (user IDs, email addresses, IPs).
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for auth_magiclink.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('auth_magiclink_token', [
            'userid' => 'privacy:metadata:token:userid',
            'token' => 'privacy:metadata:token:token',
            'expires' => 'privacy:metadata:token:expires',
            'used' => 'privacy:metadata:token:used',
            'timecreated' => 'privacy:metadata:token:timecreated',
        ], 'privacy:metadata:token');

        $collection->add_database_table('auth_magiclink_audit', [
            'userid' => 'privacy:metadata:audit:userid',
            'email' => 'privacy:metadata:audit:email',
            'action' => 'privacy:metadata:audit:action',
            'info' => 'privacy:metadata:audit:info',
            'ip' => 'privacy:metadata:audit:ip',
            'timecreated' => 'privacy:metadata:audit:timecreated',
        ], 'privacy:metadata:audit');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $hastokens = $DB->record_exists('auth_magiclink_token', ['userid' => $userid]);
        $hasaudits = $DB->record_exists('auth_magiclink_audit', ['userid' => $userid]);

        if ($hastokens || $hasaudits) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist to populate.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }

        $userlist->add_from_sql(
            'userid',
            'SELECT DISTINCT userid FROM {auth_magiclink_token}',
            []
        );
        $userlist->add_from_sql(
            'userid',
            'SELECT DISTINCT userid FROM {auth_magiclink_audit}',
            []
        );
    }

    /**
     * Export all user data for the specified approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        $tokens = $DB->get_records('auth_magiclink_token', ['userid' => $userid]);
        foreach ($tokens as $token) {
            writer::with_context($context)->export_data(
                [get_string('privacy:path:tokens', 'auth_magiclink'), $token->id],
                (object)[
                    'userid' => $token->userid,
                    'token' => $token->token,
                    'expires' => transform::datetime($token->expires),
                    'used' => transform::yesno($token->used),
                    'timecreated' => transform::datetime($token->timecreated),
                ]
            );
        }

        $audits = $DB->get_records('auth_magiclink_audit', ['userid' => $userid]);
        foreach ($audits as $audit) {
            writer::with_context($context)->export_data(
                [get_string('privacy:path:audits', 'auth_magiclink'), $audit->id],
                (object)[
                    'userid' => $audit->userid,
                    'email' => $audit->email,
                    'action' => $audit->action,
                    'info' => $audit->info,
                    'ip' => $audit->ip,
                    'timecreated' => transform::datetime($audit->timecreated),
                ]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        $DB->delete_records('auth_magiclink_token');
        $DB->delete_records('auth_magiclink_audit');
    }

    /**
     * Delete all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $DB->delete_records('auth_magiclink_token', ['userid' => $userid]);
                $DB->delete_records('auth_magiclink_audit', ['userid' => $userid]);
            }
        }
    }

    /**
     * Delete multiple users' data within a single context.
     *
     * @param approved_userlist $userlist The approved users to delete data for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('auth_magiclink_token', "userid {$insql}", $params);
        $DB->delete_records_select('auth_magiclink_audit', "userid {$insql}", $params);
    }
}
