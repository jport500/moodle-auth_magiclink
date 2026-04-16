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
 * Scheduled task to prune expired magic link tokens.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_magiclink\task;

/**
 * Deletes expired tokens older than the configured threshold.
 */
class prune_expired_tokens extends \core\task\scheduled_task {
    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_prune_expired_tokens', 'auth_magiclink');
    }

    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        $tm = new \auth_magiclink\token_manager();
        $days = (int)(get_config('auth_magiclink', 'prune_days') ?: 30);
        $pruned = $tm->prune_expired($days * DAYSECS);
        mtrace("auth_magiclink: pruned {$pruned} expired tokens.");
    }
}
