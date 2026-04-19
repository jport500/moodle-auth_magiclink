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

/**
 * PHPUnit tests for {@see api}.
 *
 * @covers \auth_magiclink\api
 */
final class api_test extends \advanced_testcase {
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
     * Test generate_login_url_for_user with null wantsurl omits the param entirely.
     */
    public function test_generate_login_url_null_wantsurl_omitted(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $url = api::generate_login_url_for_user($user->id);
        $this->assertNull($url->get_param('wantsurl'));
        $this->assertStringNotContainsString('wantsurl', $url->out(false));
    }

    /**
     * Test generate_login_url_for_user accepts a valid local wantsurl.
     */
    public function test_generate_login_url_accepts_local_wantsurl(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $url = api::generate_login_url_for_user(
            $user->id,
            null,
            new \moodle_url('/course/view.php', ['id' => 5])
        );
        $this->assertNotEmpty($url->get_param('wantsurl'));
    }

    /**
     * Test generate_login_url_for_user rejects protocol-relative URLs.
     */
    public function test_generate_login_url_rejects_protocol_relative(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $url = api::generate_login_url_for_user(
            $user->id,
            null,
            new \moodle_url('//evil.com/path')
        );
        $this->assertNull($url->get_param('wantsurl'));
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

    /**
     * Unset config (fresh install, never saved): is_auth_allowed falls
     * back to all currently-enabled auth plugins. A 'manual' user
     * qualifies because manual auth is always enabled in Moodle core.
     */
    public function test_is_auth_allowed_unset_falls_back_to_enabled_plugins(): void {
        $this->resetAfterTest();
        unset_config('allowed_auth_methods', 'auth_magiclink');
        $user = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        $this->assertTrue(api::is_auth_allowed($user));
    }

    /**
     * Empty string config (admin deliberately saved an empty selection):
     * nobody qualifies — the lockdown case.
     */
    public function test_is_auth_allowed_empty_string_locks_everyone_out(): void {
        $this->resetAfterTest();
        set_config('allowed_auth_methods', '', 'auth_magiclink');
        $magic = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $manual = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        $this->assertFalse(api::is_auth_allowed($magic));
        $this->assertFalse(api::is_auth_allowed($manual));
    }

    /**
     * Comma-separated config (normal case): listed methods qualify,
     * others don't.
     */
    public function test_is_auth_allowed_csv_checks_membership(): void {
        $this->resetAfterTest();
        set_config('allowed_auth_methods', 'magiclink,manual', 'auth_magiclink');
        $magic = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $manual = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        $email = $this->getDataGenerator()->create_user(['auth' => 'email']);
        $this->assertTrue(api::is_auth_allowed($magic));
        $this->assertTrue(api::is_auth_allowed($manual));
        $this->assertFalse(api::is_auth_allowed($email));
    }

    /**
     * Regular user (no site-config capability) is not admin-blocked.
     */
    public function test_is_admin_user_false_for_regular_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $this->assertFalse(api::is_admin_user($user));
    }

    /**
     * Site admin (has moodle/site:config at system context) is
     * admin-blocked — this is the hardcoded v3.3 exclusion.
     */
    public function test_is_admin_user_true_for_site_admin(): void {
        global $CFG;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $admins = array_filter(explode(',', $CFG->siteadmins ?? ''));
        $admins[] = (string) $user->id;
        set_config('siteadmins', implode(',', $admins));
        $this->assertTrue(api::is_admin_user($user));
    }
}
