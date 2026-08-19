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
 * Transfers referenced Moodle file-pool objects to Amazon S3.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class transfer_content_objects extends \core\task\scheduled_task {
    /**
     * Returns the localized task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_transfer_content_objects', 'tool_secure_s3_storage');
    }

    /**
     * Executes one bounded content-object transfer batch.
     */
    public function execute(): void {
        if (!get_config('tool_secure_s3_storage', 'contenttransferenabled')) {
            return;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('tool_secure_s3_storage');
        $lock = $lockfactory->get_lock('content_transfer_scan', 0);
        if (!$lock) {
            mtrace(get_string('task_already_running', 'tool_secure_s3_storage'));
            return;
        }

        try {
            try {
                if (
                    \tool_secure_s3_storage\local\database_backup_producer::get_mode() !==
                    \tool_secure_s3_storage\local\database_backup_producer::MODE_BUILTIN
                ) {
                    throw new \RuntimeException('Content recovery requires the built-in database producer.');
                }
                $configuration = \tool_secure_s3_storage\local\configuration::from_content_pool();
                $batchsize = (int)get_config('tool_secure_s3_storage', 'contentbatchsize');
                if ($batchsize < 1 || $batchsize > 1000) {
                    throw new \RuntimeException('Invalid content batch size.');
                }
            } catch (\Throwable) {
                $message = get_string('task_content_configuration_error', 'tool_secure_s3_storage');
                mtrace($message);
                throw new \RuntimeException($message);
            }

            $manager = new \tool_secure_s3_storage\local\content_transfer_manager();
            $result = $manager->execute($configuration, $batchsize);
            if ($result['found'] === 0 && !$result['waiting']) {
                mtrace(get_string('task_content_no_files', 'tool_secure_s3_storage'));
            }
            if ($result['recoverysetcomplete']) {
                mtrace(get_string('task_content_cycle_complete', 'tool_secure_s3_storage'));
            }
            if ($result['failed'] > 0) {
                throw new \RuntimeException('A content object transfer failed; the local object was preserved.');
            }
        } finally {
            $lock->release();
        }
    }
}
