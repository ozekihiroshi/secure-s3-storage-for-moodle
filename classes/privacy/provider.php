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

namespace tool_secure_s3_storage\privacy;

/**
 * Privacy API metadata declaration.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements \core_privacy\local\metadata\provider {
    /**
     * Describes transfer audit data and the external S3 destination.
     *
     * @param \core_privacy\local\metadata\collection $collection metadata collection
     * @return \core_privacy\local\metadata\collection
     */
    public static function get_metadata(
        \core_privacy\local\metadata\collection $collection
    ): \core_privacy\local\metadata\collection {
        $collection->add_database_table(
            'tool_secure_s3_storage_xfer',
            [
                'filename' => 'privacy:metadata:transfer:filename',
                'filesize' => 'privacy:metadata:transfer:filesize',
                'checksum' => 'privacy:metadata:transfer:checksum',
                'objectkey' => 'privacy:metadata:transfer:objectkey',
                'status' => 'privacy:metadata:transfer:status',
                'timecreated' => 'privacy:metadata:transfer:timecreated',
            ],
            'privacy:metadata:transfer'
        );
        $collection->add_external_location_link(
            's3',
            ['archive' => 'privacy:metadata:s3:archive'],
            'privacy:metadata:s3'
        );
        return $collection;
    }
}
