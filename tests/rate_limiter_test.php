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
 * Tests for rate_limiter.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * PHPUnit tests for {@see rate_limiter}.
 *
 * @covers \auth_magiclink\rate_limiter
 */
final class rate_limiter_test extends \advanced_testcase {
    /**
     * Test that requests under the limit are allowed.
     */
    public function test_under_limit_allowed(): void {
        $this->resetAfterTest();
        $rl = new rate_limiter();
        $this->assertTrue($rl->is_allowed('test@example.com', '127.0.0.1'));
    }

    /**
     * Test that requests at the limit are throttled.
     */
    public function test_at_limit_throttled(): void {
        $this->resetAfterTest();
        $rl = new rate_limiter();
        for ($i = 0; $i < rate_limiter::EMAIL_LIMIT; $i++) {
            $this->assertTrue($rl->is_allowed('test@example.com', '127.0.0.1'));
            $rl->record('test@example.com', '127.0.0.1');
        }
        $this->assertFalse($rl->is_allowed('test@example.com', '127.0.0.1'));
    }

    /**
     * Test that reset clears counters.
     */
    public function test_reset_clears_counters(): void {
        $this->resetAfterTest();
        $rl = new rate_limiter();
        for ($i = 0; $i < rate_limiter::EMAIL_LIMIT; $i++) {
            $rl->record('test@example.com', '127.0.0.1');
        }
        $this->assertFalse($rl->is_allowed('test@example.com', '127.0.0.1'));
        $rl->reset('test@example.com');
        $this->assertTrue($rl->is_allowed('test@example.com', '127.0.0.1'));
    }

    /**
     * Test that per-email and per-IP are tracked independently.
     */
    public function test_independent_tracking(): void {
        $this->resetAfterTest();
        $rl = new rate_limiter();
        for ($i = 0; $i < rate_limiter::EMAIL_LIMIT; $i++) {
            $rl->record('test@example.com', '127.0.0.1');
        }
        $this->assertFalse($rl->is_allowed('test@example.com', '127.0.0.1'));
        $this->assertTrue($rl->is_allowed('other@example.com', '127.0.0.1'));
    }
}
