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
 * Integration tests for the token verification flow.
 *
 * Tests the token lifecycle from generation through verification:
 * valid tokens, expired tokens, used tokens, bad tokens.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * Integration tests for token verification.
 *
 * @covers \auth_magiclink\token_manager
 * @covers \auth_magiclink\api
 */
final class verify_flow_test extends \advanced_testcase {
    /**
     * Happy path: generate token via API → verify → correct user returned.
     */
    public function test_happy_path(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $token = api::generate_token_for_user($user->id);
        $tm = new token_manager();
        $result = $tm->verify_and_consume($token);
        $this->assertEquals($user->id, $result->id);
    }

    /**
     * An expired token is rejected during verification.
     */
    public function test_expired_token_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $token = api::generate_token_for_user($user->id, 1);

        $hash = hash('sha256', $token);
        $DB->set_field('auth_magiclink_token', 'expires', time() - 100, ['token' => $hash]);

        $tm = new token_manager();
        $this->expectException(\moodle_exception::class);
        $tm->verify_and_consume($token);
    }

    /**
     * A used token is rejected on second use.
     */
    public function test_used_token_rejected(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $token = api::generate_token_for_user($user->id);
        $tm = new token_manager();
        $tm->verify_and_consume($token);
        $this->expectException(\moodle_exception::class);
        $tm->verify_and_consume($token);
    }

    /**
     * A completely invalid token is rejected.
     */
    public function test_bad_token_rejected(): void {
        $this->resetAfterTest();
        $tm = new token_manager();
        $this->expectException(\moodle_exception::class);
        $tm->verify_and_consume('not_a_real_token_at_all');
    }
}
