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
 * Tests for the prune_expired_tokens scheduled task.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * PHPUnit tests for the prune scheduled task.
 *
 * @covers \auth_magiclink\task\prune_expired_tokens
 * @covers \auth_magiclink\token_manager
 */
final class prune_task_test extends \advanced_testcase {
    /**
     * (a) Task runs without error on empty table.
     */
    public function test_runs_on_empty_table(): void {
        $this->resetAfterTest();
        $task = new \auth_magiclink\task\prune_expired_tokens();
        ob_start();
        $task->execute();
        ob_end_clean();
        $this->assertTrue(true);
    }

    /**
     * (b) Task removes tokens older than the threshold.
     */
    public function test_removes_expired_tokens(): void {
        global $DB;
        $this->resetAfterTest();

        $now = time();
        // Expired 40 days ago — default threshold is 30 days.
        $DB->insert_record('auth_magiclink_token', (object)[
            'userid' => 2,
            'token' => str_repeat('a', 64),
            'expires' => $now - (40 * DAYSECS),
            'used' => 1,
            'timecreated' => $now - (41 * DAYSECS),
        ]);

        $this->assertEquals(1, $DB->count_records('auth_magiclink_token'));

        $task = new \auth_magiclink\task\prune_expired_tokens();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertEquals(0, $DB->count_records('auth_magiclink_token'));
    }

    /**
     * (c) Task preserves tokens newer than the threshold.
     */
    public function test_preserves_recent_tokens(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();
        $tm->create_token($user->id, null, 'login');

        $this->assertEquals(1, $DB->count_records('auth_magiclink_token'));

        $task = new \auth_magiclink\task\prune_expired_tokens();
        ob_start();
        $task->execute();
        ob_end_clean();

        // Token is not expired — still present.
        $this->assertEquals(1, $DB->count_records('auth_magiclink_token'));
    }

    /**
     * (d) Task respects the prune_days setting.
     */
    public function test_uses_prune_days_setting(): void {
        global $DB;
        $this->resetAfterTest();

        $now = time();
        // Expired 5 days ago.
        $DB->insert_record('auth_magiclink_token', (object)[
            'userid' => 2,
            'token' => str_repeat('b', 64),
            'expires' => $now - (5 * DAYSECS),
            'used' => 1,
            'timecreated' => $now - (6 * DAYSECS),
        ]);

        // Default prune_days=30 → this 5-day-old expired token survives.
        $task = new \auth_magiclink\task\prune_expired_tokens();
        ob_start();
        $task->execute();
        ob_end_clean();
        $this->assertEquals(1, $DB->count_records('auth_magiclink_token'));

        // Set prune_days=1 → now it gets pruned.
        set_config('prune_days', 1, 'auth_magiclink');
        ob_start();
        $task->execute();
        ob_end_clean();
        $this->assertEquals(0, $DB->count_records('auth_magiclink_token'));
    }
}
