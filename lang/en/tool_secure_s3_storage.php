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
 * English language strings.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['awsconfiguration'] = 'AWS configuration';
$string['awsconfiguration_desc'] = 'The plugin uses the AWS SDK default credential provider chain. Long-lived AWS access keys are not stored in Moodle settings.';
$string['backupsource'] = 'Course backup source';
$string['backupsource_desc'] = 'Only completed Moodle course backup archives from the configured directory are eligible for transfer.';
$string['bucket'] = 'S3 bucket';
$string['bucket_desc'] = 'Destination Amazon S3 bucket. The runtime identity must already have access.';
$string['pluginname'] = 'Secure S3 Storage';
$string['prefix'] = 'S3 prefix';
$string['prefix_desc'] = 'Dedicated object prefix used by this plugin. Retention must never operate outside this prefix.';
$string['privacy:metadata'] = 'Secure S3 Storage records archive filenames, sizes, checksums, object keys, transfer status, and timestamps. Course backup archives may contain personal data and are transferred to the configured S3 destination.';
$string['privacy:metadata:s3'] = 'The configured S3-compatible storage receives Moodle course backup archives.';
$string['privacy:metadata:s3:archive'] = 'A course backup archive that may contain course participant data selected by Moodle backup settings.';
$string['privacy:metadata:transfer'] = 'Audit records for observed and transferred Moodle backup archives.';
$string['privacy:metadata:transfer:checksum'] = 'The archive SHA-256 checksum.';
$string['privacy:metadata:transfer:filename'] = 'The local archive filename.';
$string['privacy:metadata:transfer:filesize'] = 'The archive size.';
$string['privacy:metadata:transfer:objectkey'] = 'The destination S3 object key.';
$string['privacy:metadata:transfer:status'] = 'The transfer status.';
$string['privacy:metadata:transfer:timecreated'] = 'The time the archive was first recorded.';
$string['region'] = 'AWS region';
$string['region_desc'] = 'AWS region containing the destination bucket.';
$string['secure_s3_storage:manage'] = 'Manage Secure S3 Storage configuration and backup transfers';
$string['sourcedirectory'] = 'Backup directory';
$string['sourcedirectory_desc'] = 'Absolute server path used by Moodle automated course backups. The path is validated before files are inspected.';
$string['stabilityseconds'] = 'Stability observation period';
$string['stabilityseconds_desc'] = 'A file must retain the same size and modification time for at least this many seconds across scheduled task runs. Minimum: 1 second.';
$string['task_already_running'] = 'Another Secure S3 Storage scan is already running.';
$string['task_configuration_error'] = 'Secure S3 Storage configuration is invalid; transfer stopped.';
$string['task_failure_detail'] = 'Failure detail ({$a->type}): {$a->message}';
$string['task_file_failed'] = 'Transfer failed for {$a}. The local archive was preserved.';
$string['task_file_observed'] = 'Observed {$a}; waiting for the stability period.';
$string['task_file_transferred'] = 'Transferred and verified {$a}.';
$string['task_no_files'] = 'No eligible Moodle backup archives were found.';
$string['task_transfer_course_backups'] = 'Transfer completed course backups to Amazon S3';
$string['transferenabled'] = 'Enable scheduled transfer';
$string['transferenabled_desc'] = 'Disabled by default. Enable only after the source directory and S3 destination have been validated.';
