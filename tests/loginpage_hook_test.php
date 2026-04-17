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
 * Tests for loginpage_hook email prefill.
 *
 * Tests that the magic link login form reads an optional email
 * query parameter and passes it to the template as the input default.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

/**
 * PHPUnit tests for the loginpage_hook email prefill feature.
 *
 * Testing approach: we render the login_form template directly with
 * controlled context data, since loginpage_hook injects via AMD JS
 * (making the hook output difficult to capture in PHPUnit). The
 * template context is the contract — if the right data reaches the
 * template, the Mustache engine handles the rendering correctly.
 *
 * @covers \auth_plugin_magiclink
 */
final class loginpage_hook_test extends \advanced_testcase {
    /**
     * (a) Template renders with empty email when no prefill provided.
     */
    public function test_template_renders_empty_email_by_default(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $html = $OUTPUT->render_from_template('auth_magiclink/login_form', [
            'actionurl' => '/auth/magiclink/login.php',
            'sesskey' => sesskey(),
            'email' => '',
        ]);

        $this->assertStringContainsString('value=""', $html);
        $this->assertStringContainsString('id="email"', $html);
    }

    /**
     * (b) Template renders with prefilled email when provided.
     */
    public function test_template_renders_prefilled_email(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $html = $OUTPUT->render_from_template('auth_magiclink/login_form', [
            'actionurl' => '/auth/magiclink/login.php',
            'sesskey' => sesskey(),
            'email' => 'user@example.com',
        ]);

        $this->assertStringContainsString('value="user@example.com"', $html);
    }

    /**
     * (c) PARAM_EMAIL cleans malformed input to empty string.
     */
    public function test_param_email_rejects_malformed(): void {
        $this->resetAfterTest();

        $cleaned = clean_param('not-a-valid-email', PARAM_EMAIL);
        $this->assertSame('', $cleaned);
    }

    /**
     * (d) Template HTML-escapes the email value (XSS protection).
     */
    public function test_template_escapes_email_value(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $html = $OUTPUT->render_from_template('auth_magiclink/login_form', [
            'actionurl' => '/auth/magiclink/login.php',
            'sesskey' => sesskey(),
            'email' => '"><script>alert(1)</script>',
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * (e) PARAM_EMAIL accepts valid email addresses.
     */
    public function test_param_email_accepts_valid(): void {
        $this->resetAfterTest();

        $cleaned = clean_param('test@school.edu', PARAM_EMAIL);
        $this->assertSame('test@school.edu', $cleaned);
    }
}
