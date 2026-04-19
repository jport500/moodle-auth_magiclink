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
 * Upgrade script for auth_magiclink.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the auth_magiclink plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool True on success.
 */
function xmldb_auth_magiclink_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026050705) {
        // Define table auth_magiclink_token to be created.
        $table = new xmldb_table('auth_magiclink_token');

        // Adding fields to table auth_magiclink_token.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('token', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('expires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('used', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table auth_magiclink_token.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table auth_magiclink_token.
        $table->add_index('token', XMLDB_INDEX_UNIQUE, ['token']);

        // Conditionally launch create table for auth_magiclink_token.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table auth_magiclink_audit to be created.
        $tableaudit = new xmldb_table('auth_magiclink_audit');

        // Adding fields to table auth_magiclink_audit.
        $tableaudit->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $tableaudit->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $tableaudit->add_field('email', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $tableaudit->add_field('action', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $tableaudit->add_field('ip', XMLDB_TYPE_CHAR, '45', null, XMLDB_NOTNULL, null, null);
        $tableaudit->add_field('info', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $tableaudit->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table auth_magiclink_audit.
        $tableaudit->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table auth_magiclink_audit.
        $tableaudit->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        // Conditionally launch create table for auth_magiclink_audit.
        if (!$dbman->table_exists($tableaudit)) {
            $dbman->create_table($tableaudit);
        }

        // Auth magiclink savepoint reached.
        upgrade_plugin_savepoint(true, 2026050705, 'auth', 'magiclink');
    }

    if ($oldversion < 2026051600) {
        // V3 migration: invalidate all existing plaintext tokens.
        // After this upgrade, all stored tokens are SHA-256 hashes.
        // Users with outstanding magic links must request new ones.
        $DB->set_field('auth_magiclink_token', 'used', 1, ['used' => 0]);
        upgrade_plugin_savepoint(true, 2026051600, 'auth', 'magiclink');
    }

    if ($oldversion < 2026060100) {
        // V3.3 migration: preserve v3.2 behavior for existing installs.
        // Sets allowed_auth_methods to 'magiclink' if unset so operators
        // who upgrade see identical login behavior until they deliberately
        // widen the allowlist. Fresh installs get a different default
        // (all enabled auth plugins) supplied by settings.php.
        if (get_config('auth_magiclink', 'allowed_auth_methods') === false) {
            set_config('allowed_auth_methods', 'magiclink', 'auth_magiclink');
        }
        upgrade_plugin_savepoint(true, 2026060100, 'auth', 'magiclink');
    }

    return true;
}
