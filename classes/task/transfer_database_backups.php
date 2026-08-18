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
 * Transfers completed database artifacts to S3.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class transfer_database_backups extends \core\task\scheduled_task {
    /**
     * Returns the localized task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_transfer_database_backups', 'tool_secure_s3_storage');
    }

    /**
     * Executes the scheduled database artifact transfer.
     */
    public function execute(): void {
        if (!get_config('tool_secure_s3_storage', 'databasetransferenabled')) {
            return;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('tool_secure_s3_storage');
        $lock = $lockfactory->get_lock('database_transfer_scan', 0);
        if (!$lock) {
            mtrace(get_string('task_already_running', 'tool_secure_s3_storage'));
            return;
        }

        try {
            $producer = null;
            $builtin =
                \tool_secure_s3_storage\local\database_backup_producer::get_mode() ===
                \tool_secure_s3_storage\local\database_backup_producer::MODE_BUILTIN;
            try {
                if ($builtin) {
                    $producer = new \tool_secure_s3_storage\local\database_backup_producer();
                    $producer->prepare_directory();
                }
                $configuration = \tool_secure_s3_storage\local\configuration::from_database_plugin_config();
            } catch (\Throwable) {
                $message = get_string('task_database_configuration_error', 'tool_secure_s3_storage');
                mtrace($message);
                throw new \RuntimeException($message);
            }

            if ($producer !== null) {
                try {
                    $payload = $producer->produce();
                    mtrace(get_string('task_database_created', 'tool_secure_s3_storage', $payload));
                } catch (\Throwable $exception) {
                    $message = get_string('task_database_production_failed', 'tool_secure_s3_storage');
                    mtrace($message);
                    throw new \RuntimeException($message, 0, $exception);
                }
            }

            $manager = new \tool_secure_s3_storage\local\database_transfer_manager();
            $result = $manager->execute($configuration);

            if ($result['found'] === 0) {
                mtrace(get_string('task_database_no_files', 'tool_secure_s3_storage'));
            }
            if ($result['observed'] > 0) {
                mtrace($result['observed'] . ' database artifact(s) were observed and will be retried.');
            }
            if ($result['failed'] > 0) {
                throw new \RuntimeException('One or more database artifact transfers failed; local artifacts were preserved.');
            }
        } finally {
            $lock->release();
        }
    }
}
