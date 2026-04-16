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
 * Tests for the public API façade.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for {@see api}.
 *
 * @covers \auth_magiclink\api
 */
class api_test extends \advanced_testcase {

    /**
     * Test generate_token_for_user returns a valid token.
     */
    public function test_generate_token_for_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $token = api::generate_token_for_user($user->id);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * Test generate_login_url_for_user returns a moodle_url with the token.
     */
    public function test_generate_login_url_for_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $url = api::generate_login_url_for_user($user->id);
        $this->assertInstanceOf(\moodle_url::class, $url);
        $this->assertStringContainsString('/auth/magiclink/verify.php', $url->out(false));
        $this->assertNotEmpty($url->get_param('token'));
    }

    /**
     * Test generate_login_url_for_user rejects external wantsurl.
     */
    public function test_generate_login_url_rejects_external_wantsurl(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $url = api::generate_login_url_for_user(
            $user->id,
            null,
            new \moodle_url('https://evil.com/steal')
        );
        $this->assertEmpty($url->get_param('wantsurl'));
    }

    /**
     * Test revoke_user_tokens is idempotent.
     */
    public function test_revoke_user_tokens_idempotent(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        api::generate_token_for_user($user->id);
        $count1 = api::revoke_user_tokens($user->id);
        $this->assertGreaterThanOrEqual(1, $count1);
        $count2 = api::revoke_user_tokens($user->id);
        $this->assertEquals(0, $count2);
    }
}
