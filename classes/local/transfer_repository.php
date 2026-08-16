<?php
// This file is part of Secure S3 Storage for Moodle.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace tool_secure_s3_storage\local;

/**
 * Persists observations and verified transfer outcomes.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class transfer_repository {
    private const TABLE = 'tool_secure_s3_transfer';

    /**
     * Observes an archive and reports whether it has remained stable long enough.
     *
     * @param array{path: string, sourcehash: string, filename: string, size: int, mtime: int} $archive archive data
     * @param int $stabilityseconds required stable period
     * @return int|false|null record id when ready, null while waiting, or false when complete
     */
    public function observe(array $archive, int $stabilityseconds): int|false|null {
        global $DB;

        $now = time();
        $record = $DB->get_record(self::TABLE, ['sourcehash' => $archive['sourcehash']]);

        if (!$record) {
            $id = $DB->insert_record(self::TABLE, (object)[
                'sourcehash' => $archive['sourcehash'],
                'filename' => $archive['filename'],
                'filesize' => $archive['size'],
                'filemtime' => $archive['mtime'],
                'status' => 'observed',
                'timefirstseen' => $now,
                'timelastattempt' => 0,
                'timecompleted' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            return null;
        }

        if ((int)$record->filesize !== $archive['size'] || (int)$record->filemtime !== $archive['mtime']) {
            $record->filename = $archive['filename'];
            $record->filesize = $archive['size'];
            $record->filemtime = $archive['mtime'];
            $record->status = 'observed';
            $record->checksum = null;
            $record->objectkey = null;
            $record->errormessage = null;
            $record->timefirstseen = $now;
            $record->timelastattempt = 0;
            $record->timecompleted = 0;
            $record->timemodified = $now;
            $DB->update_record(self::TABLE, $record);
            return null;
        }

        if ($record->status === 'success') {
            return false;
        }

        if (($now - (int)$record->timefirstseen) < $stabilityseconds) {
            return null;
        }

        return (int)$record->id;
    }

    /**
     * Records the start of an idempotent upload attempt.
     *
     * @param int $id transfer record id
     * @param string $checksum SHA-256 checksum
     * @param string $objectkey deterministic object key
     */
    public function mark_attempt(int $id, string $checksum, string $objectkey): void {
        global $DB;

        $DB->update_record(self::TABLE, (object)[
            'id' => $id,
            'status' => 'uploading',
            'checksum' => $checksum,
            'objectkey' => $objectkey,
            'errormessage' => null,
            'timelastattempt' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Marks an upload as remotely verified.
     *
     * @param int $id transfer record id
     */
    public function mark_success(int $id): void {
        global $DB;

        $now = time();
        $DB->update_record(self::TABLE, (object)[
            'id' => $id,
            'status' => 'success',
            'errormessage' => null,
            'timecompleted' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Stores only a sanitized error category.
     *
     * @param int $id transfer record id
     * @param string $errorcategory sanitized category
     */
    public function mark_failure(int $id, string $errorcategory): void {
        global $DB;

        $DB->update_record(self::TABLE, (object)[
            'id' => $id,
            'status' => 'failed',
            'errormessage' => substr(clean_param($errorcategory, PARAM_ALPHANUMEXT), 0, 255),
            'timemodified' => time(),
        ]);
    }
}
