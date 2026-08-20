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
 * Runs one administrator-requested transfer through Moodle Cron.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manual_transfer extends \core\task\adhoc_task {
    /**
     * Executes the requested transfer type.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $type = is_object($data) && isset($data->type) ? (string)$data->type : '';

        $task = match ($type) {
            'course' => new transfer_course_backups(),
            'database' => new transfer_database_backups(),
            'content' => new transfer_content_objects(),
            default => null,
        };

        if ($task === null) {
            throw new \RuntimeException('Invalid manual transfer type.');
        }

        mtrace(get_string('manualtaskstarted', 'tool_secure_s3_storage', $type));
        $task->execute();
    }
}
