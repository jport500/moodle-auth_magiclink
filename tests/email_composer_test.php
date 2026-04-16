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
 * Tests for email_composer.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for {@see email_composer}.
 *
 * @covers \auth_magiclink\email_composer
 */
class email_composer_test extends \advanced_testcase {

    /**
     * Test that send_login_email sends an email successfully.
     */
    public function test_send_login_email(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $sink = $this->redirectEmails();

        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $token = bin2hex(random_bytes(32));

        $composer = new email_composer();
        $result = $composer->send_login_email($user, $token);
        $this->assertTrue($result);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $sink->close();
    }

    /**
     * Test that HTML-special characters in user names are escaped in HTML body.
     */
    public function test_xss_in_username_escaped(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $sink = $this->redirectEmails();

        $user = $this->getDataGenerator()->create_user([
            'auth' => 'magiclink',
            'firstname' => '<img src=x onerror=alert(1)>',
            'lastname' => '<script>alert(2)</script>',
        ]);
        $token = bin2hex(random_bytes(32));

        $composer = new email_composer();
        $composer->send_login_email($user, $token);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $body = $messages[0]->body;
        $this->assertStringNotContainsString('<img', $body);
        $this->assertStringNotContainsString('<script>', $body);
        $sink->close();
    }
}
