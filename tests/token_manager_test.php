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
 * Tests for token_manager.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for {@see token_manager}.
 *
 * @covers \auth_magiclink\token_manager
 */
class token_manager_test extends \advanced_testcase {

    /**
     * Test that create_token returns a 64-char hex string.
     */
    public function test_create_token_returns_hex(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();
        $token = $tm->create_token($user->id, null, 'login');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * Test that creating a second token revokes the first.
     */
    public function test_create_token_revokes_prior(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();
        $token1 = $tm->create_token($user->id, null, 'login');
        $token2 = $tm->create_token($user->id, null, 'login');
        $this->assertNotEquals($token1, $token2);
        $this->expectException(\moodle_exception::class);
        $tm->verify_and_consume($token1);
    }

    /**
     * Test that create_token rejects a deleted user.
     */
    public function test_create_token_rejects_deleted_user(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        // Bypass delete_user() to avoid triggering the observer stub.
        $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);
        $tm = new token_manager();
        $this->expectException(\moodle_exception::class);
        $tm->create_token($user->id, null, 'login');
    }

    /**
     * Test that create_token rejects a suspended user.
     */
    public function test_create_token_rejects_suspended_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink', 'suspended' => 1]);
        $tm = new token_manager();
        $this->expectException(\moodle_exception::class);
        $tm->create_token($user->id, null, 'login');
    }

    /**
     * Test verify_and_consume with a valid token.
     */
    public function test_verify_and_consume_valid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();
        $token = $tm->create_token($user->id, null, 'login');
        $result = $tm->verify_and_consume($token);
        $this->assertEquals($user->id, $result->id);
    }

    /**
     * Test verify_and_consume rejects an already-used token.
     */
    public function test_verify_and_consume_rejects_used(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();
        $token = $tm->create_token($user->id, null, 'login');
        $tm->verify_and_consume($token);
        $this->expectException(\moodle_exception::class);
        $tm->verify_and_consume($token);
    }

    /**
     * Test verify_and_consume rejects a bad token.
     */
    public function test_verify_and_consume_rejects_invalid(): void {
        $this->resetAfterTest();
        $tm = new token_manager();
        $this->expectException(\moodle_exception::class);
        $tm->verify_and_consume(str_repeat('a', 64));
    }

    /**
     * Test verify_and_consume rejects if user becomes suspended.
     */
    public function test_verify_and_consume_rejects_suspended_user(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();
        $token = $tm->create_token($user->id, null, 'login');
        $DB->set_field('user', 'suspended', 1, ['id' => $user->id]);
        $this->expectException(\moodle_exception::class);
        $tm->verify_and_consume($token);
    }

    /**
     * Test revoke_all_for_user is idempotent.
     */
    public function test_revoke_all_for_user_idempotent(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();
        $tm->create_token($user->id, null, 'login');
        $count1 = $tm->revoke_all_for_user($user->id);
        $this->assertGreaterThanOrEqual(1, $count1);
        $count2 = $tm->revoke_all_for_user($user->id);
        $this->assertEquals(0, $count2);
    }

    /**
     * Test prune_expired removes only expired rows.
     */
    public function test_prune_expired(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();
        $tm->create_token($user->id, null, 'login');
        $pruned = $tm->prune_expired(0);
        $this->assertEquals(0, $pruned);
    }
}
