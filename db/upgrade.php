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
 * Upgrade steps for Secure S3 Storage.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Performs plugin database upgrades.
 *
 * @param int $oldversion previously installed version
 * @return bool
 */
function xmldb_tool_secure_s3_storage_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026081601) {
        $table = new xmldb_table('tool_secure_s3_transfer');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('sourcehash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('filesize', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('filemtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'observed');
        $table->add_field('checksum', XMLDB_TYPE_CHAR, '64');
        $table->add_field('objectkey', XMLDB_TYPE_TEXT);
        $table->add_field('errormessage', XMLDB_TYPE_CHAR, '255');
        $table->add_field('timefirstseen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timelastattempt', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('sourcehashuniq', XMLDB_INDEX_UNIQUE, ['sourcehash']);
        $table->add_index('statusidx', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026081601, 'tool', 'secure_s3_storage');
    }

    if ($oldversion < 2026081702) {
        $oldtable = new xmldb_table('tool_secure_s3_transfer');
        $newtable = new xmldb_table('tool_secure_s3_storage_xfer');

        if ($dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
            $dbman->rename_table($oldtable, 'tool_secure_s3_storage_xfer');
        }

        upgrade_plugin_savepoint(true, 2026081702, 'tool', 'secure_s3_storage');
    }

    if ($oldversion < 2026081803) {
        if (get_config('tool_secure_s3_storage', 'databaseproducermode') === false) {
            set_config(
                'databaseproducermode',
                'external',
                'tool_secure_s3_storage'
            );
        }

        upgrade_plugin_savepoint(true, 2026081803, 'tool', 'secure_s3_storage');
    }

    return true;
}
