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
 * Tests for the v2 → v3 upgrade migration.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * PHPUnit tests for the upgrade path.
 *
 * @covers \auth_magiclink\token_manager
 * @covers \auth_magiclink\api
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Test that the v3 migration invalidates all unused plaintext tokens.
     *
     * Inserts a mix of used and unused tokens with various expiry times,
     * runs the upgrade function, and verifies:
     * - All used=0 rows become used=1
     * - Already used=1 rows remain used=1
     * - No rows are deleted
     */
    public function test_upgrade_invalidates_all_unused_tokens(): void {
        global $DB, $CFG;
        $this->resetAfterTest();

        $now = time();
        $baserecord = ['timecreated' => $now];

        // Unused, not expired.
        $DB->insert_record('auth_magiclink_token', (object)array_merge($baserecord, [
            'userid' => 2, 'token' => str_repeat('a', 64),
            'expires' => $now + 3600, 'used' => 0,
        ]));
        // Unused, already expired.
        $DB->insert_record('auth_magiclink_token', (object)array_merge($baserecord, [
            'userid' => 2, 'token' => str_repeat('b', 64),
            'expires' => $now - 3600, 'used' => 0,
        ]));
        // Already used.
        $DB->insert_record('auth_magiclink_token', (object)array_merge($baserecord, [
            'userid' => 2, 'token' => str_repeat('c', 64),
            'expires' => $now + 3600, 'used' => 1,
        ]));

        $this->assertEquals(3, $DB->count_records('auth_magiclink_token'));
        $this->assertEquals(2, $DB->count_records('auth_magiclink_token', ['used' => 0]));

        // Run the upgrade function with an old version that triggers the migration.
        // Lower the installed version so upgrade_plugin_savepoint accepts the new savepoint.
        $DB->set_field(
            'config_plugins',
            'value',
            '2026050705',
            ['plugin' => 'auth_magiclink', 'name' => 'version']
        );
        require_once($CFG->dirroot . '/auth/magiclink/db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');
        xmldb_auth_magiclink_upgrade(2026050705);

        // All rows still present (no deletes).
        $this->assertEquals(3, $DB->count_records('auth_magiclink_token'));
        // All unused tokens are now used.
        $this->assertEquals(0, $DB->count_records('auth_magiclink_token', ['used' => 0]));
        $this->assertEquals(3, $DB->count_records('auth_magiclink_token', ['used' => 1]));
    }

    /**
     * Running upgrade when the installed version is already current is
     * a no-op for every migration block — no tokens invalidated, no
     * config rewritten. "Current" tracks the installed plugin version
     * so this test stays honest as new upgrade blocks are added.
     */
    public function test_upgrade_idempotent_when_current(): void {
        global $DB, $CFG;
        $this->resetAfterTest();

        // Insert an unused token.
        $DB->insert_record('auth_magiclink_token', (object)[
            'userid' => 2, 'token' => str_repeat('d', 64),
            'expires' => time() + 3600, 'used' => 0,
            'timecreated' => time(),
        ]);

        require_once($CFG->dirroot . '/auth/magiclink/db/upgrade.php');
        // Oldversion == 2026060100 (current): every migration block's
        // guard short-circuits, so nothing executes.
        xmldb_auth_magiclink_upgrade(2026060100);

        // Token remains unused (the < 2026051600 block did not execute).
        $this->assertEquals(1, $DB->count_records('auth_magiclink_token', ['used' => 0]));
    }

    /**
     * V3.3 upgrade from a pre-v3.3 state (unset allowlist) sets the
     * allowlist to 'magiclink' so existing installs retain v3.2
     * login behavior.
     */
    public function test_upgrade_sets_allowlist_to_magiclink_for_pre_v33(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        // Wipe any pre-existing value to simulate a fresh pre-v3.3 state.
        unset_config('allowed_auth_methods', 'auth_magiclink');
        $this->assertFalse(get_config('auth_magiclink', 'allowed_auth_methods'));

        // Point the installed version at v3.2 so the savepoint accepts the bump.
        $DB->set_field(
            'config_plugins',
            'value',
            '2026051800',
            ['plugin' => 'auth_magiclink', 'name' => 'version']
        );

        require_once($CFG->dirroot . '/auth/magiclink/db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');
        xmldb_auth_magiclink_upgrade(2026051800);

        $this->assertSame(
            'magiclink',
            get_config('auth_magiclink', 'allowed_auth_methods')
        );
    }

    /**
     * V3.3 upgrade does not overwrite an allowlist that is already set
     * (idempotent on re-run; respectful if an admin already configured).
     */
    public function test_upgrade_preserves_existing_allowlist(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        // Admin had already configured a custom allowlist.
        set_config('allowed_auth_methods', 'magiclink,manual', 'auth_magiclink');

        $DB->set_field(
            'config_plugins',
            'value',
            '2026051800',
            ['plugin' => 'auth_magiclink', 'name' => 'version']
        );

        require_once($CFG->dirroot . '/auth/magiclink/db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');
        xmldb_auth_magiclink_upgrade(2026051800);

        $this->assertSame(
            'magiclink,manual',
            get_config('auth_magiclink', 'allowed_auth_methods')
        );
    }

    /**
     * Running the upgrade from v3.3 onward is a no-op for the allowlist
     * block (guard condition short-circuits).
     */
    public function test_upgrade_no_op_at_v33_or_later(): void {
        global $CFG;
        $this->resetAfterTest();

        // Simulate an admin clearing the setting after v3.3 was installed.
        unset_config('allowed_auth_methods', 'auth_magiclink');

        require_once($CFG->dirroot . '/auth/magiclink/db/upgrade.php');
        // Oldversion >= 2026060100: the v3.3 block should NOT execute.
        xmldb_auth_magiclink_upgrade(2026060100);

        // Still unset — the guard short-circuited.
        $this->assertFalse(get_config('auth_magiclink', 'allowed_auth_methods'));
    }

    /**
     * Test that new tokens created post-upgrade use SHA-256 hashes in DB.
     */
    public function test_post_upgrade_tokens_are_hashed(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $token = api::generate_token_for_user($user->id);

        // The plaintext should NOT be in the DB.
        $this->assertFalse($DB->record_exists('auth_magiclink_token', ['token' => $token]));

        // The hash should be in the DB.
        $hash = hash('sha256', $token);
        $this->assertTrue($DB->record_exists('auth_magiclink_token', ['token' => $hash]));
    }
}
