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
 * Tests for the event observer.
 *
 * Each test fires the actual Moodle event and asserts on DB state
 * afterward — does NOT call the observer callback directly.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * PHPUnit tests for {@see observer}.
 *
 * @covers \auth_magiclink\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Helper: create a magiclink user with an active token.
     *
     * @return array{user: \stdClass, token: string}
     */
    private function create_user_with_token(): array {
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();
        $token = $tm->create_token($user->id, null, 'login');
        return ['user' => $user, 'token' => $token];
    }

    /**
     * (a) user_deleted → tokens revoked.
     */
    public function test_user_deleted_revokes_tokens(): void {
        global $DB;
        $this->resetAfterTest();

        ['user' => $user] = $this->create_user_with_token();
        $this->assertEquals(1, $DB->count_records('auth_magiclink_token', [
            'userid' => $user->id, 'used' => 0,
        ]));

        delete_user($user);

        $this->assertEquals(0, $DB->count_records('auth_magiclink_token', [
            'userid' => $user->id, 'used' => 0,
        ]));
    }

    /**
     * (b) user_updated with no meaningful change → tokens NOT revoked.
     */
    public function test_user_updated_profile_only_preserves_tokens(): void {
        global $DB;
        $this->resetAfterTest();

        ['user' => $user] = $this->create_user_with_token();

        // Change firstname only — should NOT revoke tokens.
        $DB->set_field('user', 'firstname', 'NewName', ['id' => $user->id]);
        \core\event\user_updated::create_from_userid($user->id)->trigger();

        $this->assertEquals(1, $DB->count_records('auth_magiclink_token', [
            'userid' => $user->id, 'used' => 0,
        ]));
    }

    /**
     * (c) user_updated with suspended=1 → tokens revoked.
     */
    public function test_user_updated_suspended_revokes_tokens(): void {
        global $DB;
        $this->resetAfterTest();

        ['user' => $user] = $this->create_user_with_token();

        $DB->set_field('user', 'suspended', 1, ['id' => $user->id]);
        \core\event\user_updated::create_from_userid($user->id)->trigger();

        $this->assertEquals(0, $DB->count_records('auth_magiclink_token', [
            'userid' => $user->id, 'used' => 0,
        ]));
    }

    /**
     * (d) user_updated with auth changed away from magiclink → tokens revoked.
     */
    public function test_user_updated_auth_changed_revokes_tokens(): void {
        global $DB;
        $this->resetAfterTest();

        ['user' => $user] = $this->create_user_with_token();

        $DB->set_field('user', 'auth', 'manual', ['id' => $user->id]);
        \core\event\user_updated::create_from_userid($user->id)->trigger();

        $this->assertEquals(0, $DB->count_records('auth_magiclink_token', [
            'userid' => $user->id, 'used' => 0,
        ]));
    }

    /**
     * (e) user_password_updated → tokens revoked.
     */
    public function test_password_updated_revokes_tokens(): void {
        global $DB;
        $this->resetAfterTest();

        ['user' => $user] = $this->create_user_with_token();

        $event = \core\event\user_password_updated::create_from_user($user);
        $event->trigger();

        $this->assertEquals(0, $DB->count_records('auth_magiclink_token', [
            'userid' => $user->id, 'used' => 0,
        ]));
    }

    /**
     * (f) Idempotency: user with no tokens → events fire without error.
     */
    public function test_events_on_user_with_no_tokens(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);

        // No tokens exist for this user.
        $this->assertEquals(0, $DB->count_records('auth_magiclink_token', ['userid' => $user->id]));

        // All three events should complete without error.
        \core\event\user_updated::create_from_userid($user->id)->trigger();
        \core\event\user_password_updated::create_from_user($user)->trigger();

        // Delete user fires user_deleted event.
        delete_user($user);

        // Still no tokens, no errors.
        $this->assertEquals(0, $DB->count_records('auth_magiclink_token', ['userid' => $user->id]));
    }
}
