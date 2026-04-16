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
 * Tests for verify_controller.
 *
 * Each test calls verify_controller::handle_verify() and asserts on the
 * returned redirect descriptor, audit rows, and login state ($USER).
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * PHPUnit tests for {@see verify_controller}.
 *
 * @covers \auth_magiclink\verify_controller
 */
final class verify_controller_test extends \advanced_testcase {
    /**
     * (a) Happy path: valid token → user logged in → redirect to wwwroot.
     */
    public function test_happy_path_logs_user_in(): void {
        global $DB, $USER, $CFG;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $token = api::generate_token_for_user($user->id);

        $result = verify_controller::handle_verify($token, '10.0.0.1');

        // User is logged in.
        $this->assertTrue($result['loggedin']);
        $this->assertTrue(isloggedin());
        $this->assertFalse(isguestuser());
        $this->assertEquals($user->id, $USER->id);

        // Redirect to wwwroot (no wantsurl).
        $this->assertEquals($CFG->wwwroot, $result['url']);
        $this->assertEmpty($result['message']);

        // Audit row.
        $this->assertTrue($DB->record_exists('auth_magiclink_audit', [
            'userid' => $user->id,
            'action' => 'login_success',
        ]));
    }

    /**
     * (b) Happy path with local wantsurl: redirect honors the local URL.
     */
    public function test_happy_path_with_local_wantsurl(): void {
        global $USER;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $token = api::generate_token_for_user($user->id);

        $result = verify_controller::handle_verify($token, '10.0.0.1', '/course/view.php?id=5');

        $this->assertTrue($result['loggedin']);
        $this->assertEquals($user->id, $USER->id);
        $this->assertEquals('/course/view.php?id=5', $result['url']);
    }

    /**
     * (c) External wantsurl is dropped: redirect to wwwroot.
     */
    public function test_external_wantsurl_dropped(): void {
        global $USER, $CFG;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $token = api::generate_token_for_user($user->id);

        $result = verify_controller::handle_verify($token, '10.0.0.1', 'https://evil.com/steal');

        $this->assertTrue($result['loggedin']);
        $this->assertEquals($user->id, $USER->id);
        $this->assertEquals($CFG->wwwroot, $result['url']);
    }

    /**
     * (d) Invalid token: redirect to login with generic error.
     */
    public function test_invalid_token_generic_error(): void {
        global $DB;
        $this->resetAfterTest();

        $result = verify_controller::handle_verify(str_repeat('a', 64), '10.0.0.1');

        $this->assertFalse($result['loggedin']);
        $this->assertFalse(isloggedin());
        $this->assertEquals(
            get_string('tokennotvalid', 'auth_magiclink'),
            $result['message']
        );
        $this->assertEquals(\core\output\notification::NOTIFY_ERROR, $result['messagetype']);

        $this->assertTrue($DB->record_exists('auth_magiclink_audit', [
            'action' => 'login_failed',
        ]));
    }

    /**
     * (e) Expired token: redirect to login with generic error.
     */
    public function test_expired_token_generic_error(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $token = api::generate_token_for_user($user->id, 1);
        $hash = hash('sha256', $token);
        $DB->set_field('auth_magiclink_token', 'expires', time() - 100, ['token' => $hash]);

        $result = verify_controller::handle_verify($token, '10.0.0.1');

        $this->assertFalse($result['loggedin']);
        $this->assertEquals(
            get_string('tokennotvalid', 'auth_magiclink'),
            $result['message']
        );
    }

    /**
     * (f) Used token: redirect to login with generic error.
     */
    public function test_used_token_generic_error(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $token = api::generate_token_for_user($user->id);

        // Consume it first.
        $tm = new token_manager();
        $tm->verify_and_consume($token);

        // Log out so we can test the second attempt cleanly.
        \core\session\manager::init_empty_session();

        $result = verify_controller::handle_verify($token, '10.0.0.1');

        $this->assertFalse($result['loggedin']);
        $this->assertEquals(
            get_string('tokennotvalid', 'auth_magiclink'),
            $result['message']
        );
    }

    /**
     * (g) Uniform error message: invalid, expired, and used tokens all produce
     * byte-identical error notification. Prevents token-state probing.
     */
    public function test_uniform_error_across_failure_modes(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);

        // Expired token.
        $expiredtoken = api::generate_token_for_user($user->id, 1);
        $hash = hash('sha256', $expiredtoken);
        $DB->set_field('auth_magiclink_token', 'expires', time() - 100, ['token' => $hash]);

        // Used token (create a new one, consume it).
        $usedtoken = api::generate_token_for_user($user->id);
        $tm = new token_manager();
        $tm->verify_and_consume($usedtoken);
        \core\session\manager::init_empty_session();

        $results = [];
        $results['invalid'] = verify_controller::handle_verify(str_repeat('b', 64), '10.0.0.1');
        $results['expired'] = verify_controller::handle_verify($expiredtoken, '10.0.0.1');
        $results['used'] = verify_controller::handle_verify($usedtoken, '10.0.0.1');

        $expected = get_string('tokennotvalid', 'auth_magiclink');
        foreach ($results as $case => $result) {
            $this->assertFalse($result['loggedin'], "Case '{$case}' should not be logged in");
            $this->assertSame(
                $expected,
                $result['message'],
                "Case '{$case}' produced a different error message"
            );
            $this->assertSame(
                \core\output\notification::NOTIFY_ERROR,
                $result['messagetype'],
                "Case '{$case}' produced a different notification type"
            );
        }
    }

    /**
     * (h) Suspended user: token valid but user suspended → not logged in, generic error.
     */
    public function test_suspended_user_not_logged_in(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $token = api::generate_token_for_user($user->id);

        // Suspend after token creation.
        $DB->set_field('user', 'suspended', 1, ['id' => $user->id]);

        $result = verify_controller::handle_verify($token, '10.0.0.1');

        $this->assertFalse($result['loggedin']);
        $this->assertFalse(isloggedin());
        $this->assertEquals(
            get_string('tokennotvalid', 'auth_magiclink'),
            $result['message']
        );

        $this->assertTrue($DB->record_exists('auth_magiclink_audit', [
            'action' => 'login_failed',
        ]));
    }

    /**
     * (i) Deleted user: token valid but user deleted → not logged in, generic error.
     */
    public function test_deleted_user_not_logged_in(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $token = api::generate_token_for_user($user->id);

        // Delete after token creation (bypass delete_user to avoid observer stub).
        $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);

        $result = verify_controller::handle_verify($token, '10.0.0.1');

        $this->assertFalse($result['loggedin']);
        $this->assertFalse(isloggedin());
        $this->assertEquals(
            get_string('tokennotvalid', 'auth_magiclink'),
            $result['message']
        );
    }
}
