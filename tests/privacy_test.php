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
 * Privacy API tests for auth_magiclink.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * PHPUnit tests for the privacy provider.
 *
 * @covers \auth_magiclink\privacy\provider
 */
final class privacy_test extends provider_testcase {
    /**
     * Helper: create a user with token and audit data.
     *
     * @return \stdClass The created user.
     */
    private function create_user_with_data(): \stdClass {
        $user = $this->getDataGenerator()->create_user(['auth' => 'magiclink']);
        $tm = new token_manager();
        $tm->create_token($user->id, null, 'login');
        audit::log($user->id, $user->email, 'send_link', '', '10.0.0.1');
        return $user;
    }

    /**
     * (a) get_metadata returns both tables.
     */
    public function test_get_metadata(): void {
        $collection = new collection('auth_magiclink');
        $collection = \auth_magiclink\privacy\provider::get_metadata($collection);
        $items = $collection->get_collection();

        $tablenames = [];
        foreach ($items as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $tablenames[] = $item->get_name();
            }
        }

        $this->assertContains('auth_magiclink_token', $tablenames);
        $this->assertContains('auth_magiclink_audit', $tablenames);
    }

    /**
     * (b) get_contexts_for_userid: system context when data exists, empty when not.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();

        $user = $this->create_user_with_data();
        $contextlist = \auth_magiclink\privacy\provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals(\context_system::instance()->id, $contextlist->get_contextids()[0]);

        // User with no data.
        $empty = $this->getDataGenerator()->create_user();
        $contextlist2 = \auth_magiclink\privacy\provider::get_contexts_for_userid($empty->id);
        $this->assertEmpty($contextlist2);
    }

    /**
     * (c) export_user_data produces exported rows.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();

        $user = $this->create_user_with_data();
        $context = \context_system::instance();
        $contextlist = new approved_contextlist($user, 'auth_magiclink', [$context->id]);

        \auth_magiclink\privacy\provider::export_user_data($contextlist);

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * (d) delete_data_for_user removes only that user's rows.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->create_user_with_data();
        $user2 = $this->create_user_with_data();

        $context = \context_system::instance();
        $contextlist = new approved_contextlist($user1, 'auth_magiclink', [$context->id]);

        \auth_magiclink\privacy\provider::delete_data_for_user($contextlist);

        $this->assertEquals(0, $DB->count_records('auth_magiclink_token', ['userid' => $user1->id]));
        $this->assertEquals(0, $DB->count_records('auth_magiclink_audit', ['userid' => $user1->id]));

        // User2's data survives.
        $this->assertGreaterThan(0, $DB->count_records('auth_magiclink_token', ['userid' => $user2->id]));
        $this->assertGreaterThan(0, $DB->count_records('auth_magiclink_audit', ['userid' => $user2->id]));
    }

    /**
     * (e) get_users_in_context includes users who have data.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        $user1 = $this->create_user_with_data();
        $user2 = $this->create_user_with_data();
        $empty = $this->getDataGenerator()->create_user();

        $userlist = new userlist(\context_system::instance(), 'auth_magiclink');
        \auth_magiclink\privacy\provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();

        $this->assertContains((int)$user1->id, $userids);
        $this->assertContains((int)$user2->id, $userids);
        $this->assertNotContains((int)$empty->id, $userids);
    }

    /**
     * (f) delete_data_for_users removes only the listed users' rows.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->create_user_with_data();
        $user2 = $this->create_user_with_data();

        $userlist = new approved_userlist(
            \context_system::instance(),
            'auth_magiclink',
            [$user1->id]
        );

        \auth_magiclink\privacy\provider::delete_data_for_users($userlist);

        $this->assertEquals(0, $DB->count_records('auth_magiclink_token', ['userid' => $user1->id]));
        $this->assertGreaterThan(0, $DB->count_records('auth_magiclink_token', ['userid' => $user2->id]));
    }

    /**
     * (g) delete_data_for_all_users_in_context empties both tables.
     */
    public function test_delete_all_in_context(): void {
        global $DB;
        $this->resetAfterTest();

        $this->create_user_with_data();
        $this->create_user_with_data();

        $this->assertGreaterThan(0, $DB->count_records('auth_magiclink_token'));
        $this->assertGreaterThan(0, $DB->count_records('auth_magiclink_audit'));

        \auth_magiclink\privacy\provider::delete_data_for_all_users_in_context(
            \context_system::instance()
        );

        $this->assertEquals(0, $DB->count_records('auth_magiclink_token'));
        $this->assertEquals(0, $DB->count_records('auth_magiclink_audit'));
    }
}
