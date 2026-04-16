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
 * Tests for manage_controller.
 *
 * Tests SQL portability (sql_fullname), pagination, actions, and
 * capability check for the management page.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * PHPUnit tests for {@see manage_controller}.
 *
 * @covers \auth_magiclink\manage_controller
 */
final class manage_controller_test extends \advanced_testcase {
    /**
     * Test list_tokens returns correct data with sql_fullname (S8 portability fix).
     */
    public function test_list_tokens_with_fullname(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'magiclink',
            'firstname' => 'Alice',
            'lastname' => 'Smith',
        ]);

        $tm = new token_manager();
        $tm->create_token($user->id, null, 'login');

        $result = manage_controller::list_tokens(0, 25);

        $this->assertEquals(1, $result['total']);
        $this->assertCount(1, $result['tokens']);
        $token = $result['tokens'][0];
        $this->assertEquals('Alice Smith', $token->fullname);
        $this->assertEquals($user->email, $token->email);
        $this->assertEquals(get_string('token_status_unused', 'auth_magiclink'), $token->statuslabel);
    }

    /**
     * Test list_tokens with a mix of used/unused/expired tokens.
     */
    public function test_list_tokens_mixed_statuses(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();

        // Create 3 tokens — the second two will be revoked (used=1) by create_token.
        $tm->create_token($user->id, null, 'login');
        $tm->create_token($user->id, null, 'login');
        $tm->create_token($user->id, null, 'login');

        $result = manage_controller::list_tokens(0, 25);
        // 3 total rows (2 used from revocation + 1 active).
        $this->assertEquals(3, $result['total']);

        $usedcount = 0;
        $unusedcount = 0;
        foreach ($result['tokens'] as $t) {
            if ($t->statuskey === 'used') {
                $usedcount++;
            } else {
                $unusedcount++;
            }
        }
        $this->assertEquals(2, $usedcount);
        $this->assertEquals(1, $unusedcount);
    }

    /**
     * Test pagination: page 0 returns first batch, page 1 returns the rest.
     */
    public function test_list_tokens_pagination(): void {
        $this->resetAfterTest();

        // Create 3 different users with tokens to avoid revocation.
        for ($i = 0; $i < 3; $i++) {
            $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
            $tm = new token_manager();
            $tm->create_token($user->id, null, 'login');
        }

        $page0 = manage_controller::list_tokens(0, 2);
        $this->assertEquals(3, $page0['total']);
        $this->assertCount(2, $page0['tokens']);

        $page1 = manage_controller::list_tokens(1, 2);
        $this->assertEquals(3, $page1['total']);
        $this->assertCount(1, $page1['tokens']);
    }

    /**
     * Test list_audits returns records with fullname.
     */
    public function test_list_audits(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'magiclink',
            'firstname' => 'Bob',
            'lastname' => 'Jones',
        ]);
        audit::log($user->id, $user->email, 'send_link', '', '10.0.0.1');
        audit::log(null, 'unknown@test.com', 'no_user', '', '10.0.0.2');

        $result = manage_controller::list_audits(0, 25);

        $this->assertEquals(2, $result['total']);
        $this->assertCount(2, $result['audits']);
    }

    /**
     * Test execute_action: revoke marks token as used.
     */
    public function test_action_revoke(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();
        $tm->create_token($user->id, null, 'login');

        $tokens = manage_controller::list_tokens(0, 25);
        $tokenid = $tokens['tokens'][0]->id;

        $msgkey = manage_controller::execute_action('revoke', $tokenid);
        $this->assertEquals('linkrevoked', $msgkey);

        $row = $DB->get_record('auth_magiclink_token', ['id' => $tokenid]);
        $this->assertEquals(1, (int)$row->used);
    }

    /**
     * Test execute_action: extend resets expiry.
     */
    public function test_action_extend(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();
        $tm->create_token($user->id, null, 'login');

        $tokens = manage_controller::list_tokens(0, 25);
        $tokenid = $tokens['tokens'][0]->id;

        $before = time();
        $msgkey = manage_controller::execute_action('extend', $tokenid);
        $this->assertEquals('linkextended', $msgkey);

        $row = $DB->get_record('auth_magiclink_token', ['id' => $tokenid]);
        $this->assertGreaterThanOrEqual($before + (15 * 60) - 1, (int)$row->expires);
    }

    /**
     * Test execute_action: delete removes the record.
     */
    public function test_action_delete(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();
        $tm->create_token($user->id, null, 'login');

        $tokens = manage_controller::list_tokens(0, 25);
        $tokenid = $tokens['tokens'][0]->id;

        $msgkey = manage_controller::execute_action('delete', $tokenid);
        $this->assertEquals('linkdeleted', $msgkey);

        $this->assertFalse($DB->record_exists('auth_magiclink_token', ['id' => $tokenid]));
    }

    /**
     * Test execute_action: invalid action throws.
     */
    public function test_action_invalid_throws(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        manage_controller::execute_action('destroy', 1);
    }

    /**
     * Test capability check: user without auth/magiclink:manage is denied.
     */
    public function test_capability_check_denied(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        require_capability('auth/magiclink:manage', \context_system::instance());
    }

    /**
     * Test capability check: admin is allowed.
     */
    public function test_capability_check_admin_allowed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Should not throw.
        require_capability('auth/magiclink:manage', \context_system::instance());
        $this->assertTrue(true);
    }

    /**
     * Test empty state: list_tokens with no data returns 0 total and empty array.
     */
    public function test_empty_tokens(): void {
        $this->resetAfterTest();
        $result = manage_controller::list_tokens(0, 25);
        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['tokens']);
    }

    /**
     * Test empty state: list_audits with no data returns 0 total and empty array.
     */
    public function test_empty_audits(): void {
        $this->resetAfterTest();
        $result = manage_controller::list_audits(0, 25);
        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['audits']);
    }
}
