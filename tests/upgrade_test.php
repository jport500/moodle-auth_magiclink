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
 * Tests for the upgrade path.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for the v2 → v3 upgrade migration.
 */
class upgrade_test extends \advanced_testcase {

    /**
     * Test that after upgrade, all pre-existing tokens have used=1.
     */
    public function test_upgrade_invalidates_plaintext_tokens(): void {
        global $DB;
        $this->resetAfterTest();

        // Insert a fake v2 plaintext token.
        $DB->insert_record('auth_magiclink_token', (object)[
            'userid' => 2,
            'token' => str_repeat('a', 64),
            'expires' => time() + 3600,
            'used' => 0,
            'timecreated' => time(),
        ]);

        // Simulate the upgrade step.
        $DB->set_field('auth_magiclink_token', 'used', 1, ['used' => 0]);

        $record = $DB->get_record('auth_magiclink_token', ['token' => str_repeat('a', 64)]);
        $this->assertEquals(1, (int)$record->used);
    }

    /**
     * Test that new tokens created post-upgrade use SHA-256 hashes.
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
