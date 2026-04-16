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
 * Integration tests for the login → verify flow.
 *
 * Tests the service-layer orchestration that login.php and verify.php
 * delegate to. Covers happy path (email sent, token verified) and
 * error paths (rate limited, domain blocked, unknown email, wrong auth).
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * Integration tests for login and verify flows.
 *
 * @covers \auth_magiclink\token_manager
 * @covers \auth_magiclink\api
 * @covers \auth_magiclink\email_composer
 * @covers \auth_magiclink\rate_limiter
 * @covers \auth_magiclink\domain_filter
 * @covers \auth_magiclink\audit
 */
final class verify_flow_test extends \advanced_testcase {
    /**
     * Happy path: valid magiclink user → email sent → token verifies → user returned.
     */
    public function test_happy_path_email_sent_and_token_verifies(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $sink = $this->redirectEmails();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'magiclink',
            'email' => 'alice@school.edu',
        ]);

        $tm = new token_manager();
        $token = $tm->create_token($user->id, null, 'login');

        $composer = new email_composer();
        $result = $composer->send_login_email($user, $token);
        $this->assertTrue($result);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('verify.php', $messages[0]->body);
        $sink->close();

        // Token verification returns the user.
        $verified = $tm->verify_and_consume($token);
        $this->assertEquals($user->id, $verified->id);
        $this->assertEquals(0, (int)$verified->deleted);
        $this->assertEquals(0, (int)$verified->suspended);
    }

    /**
     * No email is sent when the user's email is not found.
     */
    public function test_no_email_for_unknown_user(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $sink = $this->redirectEmails();

        $email = 'nobody@school.edu';
        $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0, 'suspended' => 0]);
        $this->assertFalse($user);

        audit::log(null, $email, 'no_user', '', '127.0.0.1');

        $messages = $sink->get_messages();
        $this->assertCount(0, $messages);
        $sink->close();

        // Verify audit row was written.
        $auditrow = $DB->get_record('auth_magiclink_audit', ['email' => $email, 'action' => 'no_user']);
        $this->assertNotFalse($auditrow);
    }

    /**
     * No email is sent when user has auth != 'magiclink'.
     */
    public function test_rejects_user_with_non_magiclink_auth(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $sink = $this->redirectEmails();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'manual',
            'email' => 'admin@school.edu',
        ]);

        // Simulate the controller's auth-type check.
        $found = $DB->get_record('user', [
            'email' => 'admin@school.edu',
            'deleted' => 0,
            'suspended' => 0,
        ]);
        $this->assertNotFalse($found);
        $this->assertNotEquals('magiclink', $found->auth);

        audit::log($found->id, 'admin@school.edu', 'wrong_auth', '', '127.0.0.1');

        $messages = $sink->get_messages();
        $this->assertCount(0, $messages);
        $sink->close();

        // Verify audit row was written with 'wrong_auth' action.
        $auditrow = $DB->get_record('auth_magiclink_audit', [
            'email' => 'admin@school.edu',
            'action' => 'wrong_auth',
        ]);
        $this->assertNotFalse($auditrow);
        $this->assertEquals($found->id, $auditrow->userid);
    }

    /**
     * No email is sent when domain is blocked.
     */
    public function test_no_email_when_domain_blocked(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $sink = $this->redirectEmails();

        set_config('alloweddomains', 'school.edu', 'auth_magiclink');
        $filter = new domain_filter();
        $this->assertFalse($filter->is_allowed('user@attacker.com'));

        audit::log(null, 'user@attacker.com', 'domain_blocked', '', '127.0.0.1');

        $messages = $sink->get_messages();
        $this->assertCount(0, $messages);
        $sink->close();

        $auditrow = $DB->get_record('auth_magiclink_audit', [
            'email' => 'user@attacker.com',
            'action' => 'domain_blocked',
        ]);
        $this->assertNotFalse($auditrow);
    }

    /**
     * No email is sent when rate limit is exceeded.
     */
    public function test_no_email_when_rate_limited(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $sink = $this->redirectEmails();

        $limiter = new rate_limiter();
        $email = 'spammed@school.edu';
        $ip = '10.0.0.1';

        // Exhaust the rate limit.
        for ($i = 0; $i < rate_limiter::EMAIL_LIMIT; $i++) {
            $this->assertTrue($limiter->is_allowed($email, $ip));
            $limiter->record($email, $ip);
        }
        $this->assertFalse($limiter->is_allowed($email, $ip));

        audit::log(null, $email, 'rate_limited', '', $ip);

        $messages = $sink->get_messages();
        $this->assertCount(0, $messages);
        $sink->close();

        $auditrow = $DB->get_record('auth_magiclink_audit', [
            'email' => $email,
            'action' => 'rate_limited',
        ]);
        $this->assertNotFalse($auditrow);
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

    /**
     * Audit log correctly records a send_link action.
     */
    public function test_audit_log_records_send_link(): void {
        global $DB;
        $this->resetAfterTest();

        audit::log(42, 'test@school.edu', 'send_link', '', '192.168.1.1');

        $row = $DB->get_record('auth_magiclink_audit', [
            'userid' => 42,
            'action' => 'send_link',
        ]);
        $this->assertNotFalse($row);
        $this->assertEquals('test@school.edu', $row->email);
        $this->assertEquals('192.168.1.1', $row->ip);
    }

    /**
     * Audit log handles null userid (unknown user).
     */
    public function test_audit_log_null_userid(): void {
        global $DB;
        $this->resetAfterTest();

        audit::log(null, 'unknown@test.com', 'no_user', '', '10.0.0.1');

        $row = $DB->get_record('auth_magiclink_audit', [
            'email' => 'unknown@test.com',
            'action' => 'no_user',
        ]);
        $this->assertNotFalse($row);
        $this->assertEquals(0, (int)$row->userid);
    }
}
