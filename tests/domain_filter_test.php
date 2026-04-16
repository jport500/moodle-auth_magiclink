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
 * Tests for domain_filter.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for {@see domain_filter}.
 *
 * @covers \auth_magiclink\domain_filter
 */
class domain_filter_test extends \advanced_testcase {

    /**
     * Test that an empty allowlist permits all domains.
     */
    public function test_empty_allowlist_permits_all(): void {
        $this->resetAfterTest();
        set_config('alloweddomains', '', 'auth_magiclink');
        $filter = new domain_filter();
        $this->assertTrue($filter->is_allowed('user@anything.com'));
    }

    /**
     * Test that a configured allowlist permits matching domains.
     */
    public function test_allowlist_permits_matching(): void {
        $this->resetAfterTest();
        set_config('alloweddomains', 'school.edu, university.com', 'auth_magiclink');
        $filter = new domain_filter();
        $this->assertTrue($filter->is_allowed('user@school.edu'));
        $this->assertTrue($filter->is_allowed('user@university.com'));
    }

    /**
     * Test that a configured allowlist blocks non-matching domains.
     */
    public function test_allowlist_blocks_nonmatching(): void {
        $this->resetAfterTest();
        set_config('alloweddomains', 'school.edu', 'auth_magiclink');
        $filter = new domain_filter();
        $this->assertFalse($filter->is_allowed('user@attacker.com'));
    }

    /**
     * Test case insensitivity.
     */
    public function test_case_insensitive(): void {
        $this->resetAfterTest();
        set_config('alloweddomains', 'School.EDU', 'auth_magiclink');
        $filter = new domain_filter();
        $this->assertTrue($filter->is_allowed('user@school.edu'));
        $this->assertTrue($filter->is_allowed('user@SCHOOL.EDU'));
    }
}
