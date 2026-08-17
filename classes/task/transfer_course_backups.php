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

namespace tool_secure_s3_storage\task;

/**
 * Finds completed Moodle course backups and transfers them to S3.
 *
 * The task remains inert until transfer is explicitly enabled.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class transfer_course_backups extends \core\task\scheduled_task {
    /**
     * Returns the localized task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_transfer_course_backups', 'tool_secure_s3_storage');
    }

    /**
     * Executes the scheduled transfer scan.
     */
    public function execute(): void {
        if (!get_config('tool_secure_s3_storage', 'transferenabled')) {
            return;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('tool_secure_s3_storage');
        $lock = $lockfactory->get_lock('transfer_scan', 0);
        if (!$lock) {
            mtrace(get_string('task_already_running', 'tool_secure_s3_storage'));
            return;
        }

        try {
            try {
                $configuration = \tool_secure_s3_storage\local\configuration::from_plugin_config();
            } catch (\Throwable) {
                $message = get_string('task_configuration_error', 'tool_secure_s3_storage');
                mtrace($message);
                throw new \RuntimeException($message);
            }

            $manager = new \tool_secure_s3_storage\local\transfer_manager();
            $result = $manager->execute($configuration);

            if ($result['found'] === 0) {
                mtrace(get_string('task_no_files', 'tool_secure_s3_storage'));
            }
            if ($result['observed'] > 0) {
                mtrace($result['observed'] . ' archive(s) are waiting for the stability period.');
            }
            if ($result['failed'] > 0) {
                throw new \RuntimeException('One or more S3 transfers failed; local archives were preserved.');
            }
        } finally {
            $lock->release();
        }
    }
}
