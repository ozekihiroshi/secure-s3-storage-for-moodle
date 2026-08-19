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
$string['contentbackup'] = 'Site content file pool';
$string['contentbackup_desc'] = 'Protects non-empty content objects referenced by a built-in database recovery snapshot. This does not change primary file storage and never deletes local or S3 objects.';
$string['contentbatchsize'] = 'Content objects per run';
$string['contentbatchsize_desc'] = 'Maximum number of distinct file-pool objects processed in one scheduled run. Allowed range: 1 to 1000.';
$string['contenttransferenabled'] = 'Enable scheduled content-object transfer';
$string['contenttransferenabled_desc'] = 'Disabled by default and requires the built-in database producer. Enable only after S3 permissions, capacity, cost, and isolated recovery procedures have been validated.';
$string['databaseartifactdirectory'] = 'External artifact directory';
$string['databaseartifactdirectory_desc'] = 'Used only in external producer mode. Absolute read-only hand-off path containing completed database payload and manifest pairs.';
$string['databasebackupsource'] = 'Database backup artifacts';
$string['databasebackupsource_desc'] = 'Choose the built-in producer for plugin-only operation or an external producer for advanced process and credential separation.';
$string['databaseproducermode'] = 'Database backup producer';
$string['databaseproducermode_builtin'] = 'Built-in Moodle producer (MariaDB/MySQL)';
$string['databaseproducermode_desc'] = 'Built-in mode creates a Moodle logical database export below moodledata. External mode only validates and transfers artifacts created by a separately privileged process. Changing mode does not enable backup.';
$string['databaseproducermode_external'] = 'External producer (advanced isolation)';
$string['databasetransferenabled'] = 'Enable scheduled database artifact transfer';
$string['databasetransferenabled_desc'] = 'Disabled by default. In built-in mode, each scheduled run creates and transfers one database backup. In external mode, it transfers completed artifacts from the hand-off directory.';
$string['pluginname'] = 'Secure S3 Storage';
$string['prefix'] = 'S3 prefix';
$string['prefix_desc'] = 'Dedicated object prefix used by this plugin. Retention must never operate outside this prefix.';
$string['privacy:metadata'] = 'Secure S3 Storage records filenames or content hashes, sizes, checksums, object keys, transfer status, and timestamps. Course archives, database artifacts, and enabled content objects may contain personal data and are transferred to the configured S3 destination.';
$string['privacy:metadata:s3'] = 'The configured S3-compatible storage receives enabled Moodle backup artifact types.';
$string['privacy:metadata:s3:archive'] = 'A course archive, database backup artifact, or content object that may contain Moodle user and site data.';
$string['privacy:metadata:transfer'] = 'Audit records for observed and transferred Moodle backup artifacts and content objects.';
$string['privacy:metadata:transfer:checksum'] = 'The artifact or content-object SHA-256 checksum.';
$string['privacy:metadata:transfer:filename'] = 'The local artifact filename or content hash.';
$string['privacy:metadata:transfer:filesize'] = 'The artifact or content-object size.';
$string['privacy:metadata:transfer:objectkey'] = 'The destination S3 object key.';
$string['privacy:metadata:transfer:status'] = 'The transfer status.';
$string['privacy:metadata:transfer:timecreated'] = 'The time the artifact or content object was first recorded.';
$string['region'] = 'AWS region';
$string['region_desc'] = 'AWS region containing the destination bucket.';
$string['secure_s3_storage:manage'] = 'Manage Secure S3 Storage configuration and backup transfers';
$string['sourcedirectory'] = 'Backup directory';
$string['sourcedirectory_desc'] = 'Absolute server path used by Moodle automated course backups. The path is validated before files are inspected.';
$string['stabilityseconds'] = 'Stability observation period';
$string['stabilityseconds_desc'] = 'A file must retain the same size and modification time for at least this many seconds across scheduled task runs. Minimum: 1 second.';
$string['task_already_running'] = 'Another Secure S3 Storage scan is already running.';
$string['task_configuration_error'] = 'Secure S3 Storage configuration is invalid; transfer stopped.';
$string['task_content_configuration_error'] = 'Secure S3 Storage content backup configuration is invalid; transfer stopped.';
$string['task_content_cycle_complete'] = 'Completed and published one database-matched Moodle content recovery set.';
$string['task_content_database_waiting'] = 'Content recovery set {$a} is waiting for its database artifact to pass remote verification.';
$string['task_content_failed'] = 'Content-object transfer failed for {$a}. The local object was preserved.';
$string['task_content_manifest_rejected'] = 'Rejected invalid content recovery manifest {$a}.';
$string['task_content_no_files'] = 'No incomplete content recovery set was found.';
$string['task_content_recovery_set_transferred'] = 'Transferred and verified content recovery set {$a}.';
$string['task_content_transferred'] = 'Transferred and verified content object {$a}.';
$string['task_database_configuration_error'] = 'Secure S3 Storage database artifact configuration is invalid; transfer stopped.';
$string['task_database_created'] = 'Created built-in database backup {$a}.';
$string['task_database_failed'] = 'Database artifact transfer failed for {$a}. The local payload and manifest were preserved.';
$string['task_database_manifest_rejected'] = 'Rejected invalid database artifact manifest {$a}.';
$string['task_database_no_files'] = 'No eligible completed database artifacts were found.';
$string['task_database_production_failed'] = 'Built-in database backup creation failed; no incomplete artifact was published.';
$string['task_database_transferred'] = 'Transferred and verified database artifact {$a}.';
$string['task_failure_detail'] = 'Failure detail ({$a->type}): {$a->message}';
$string['task_file_failed'] = 'Transfer failed for {$a}. The local archive was preserved.';
$string['task_file_observed'] = 'Observed {$a}; waiting for the stability period.';
$string['task_file_transferred'] = 'Transferred and verified {$a}.';
$string['task_no_files'] = 'No eligible Moodle backup archives were found.';
$string['task_transfer_content_objects'] = 'Transfer referenced Moodle content objects to Amazon S3';
$string['task_transfer_course_backups'] = 'Transfer completed course backups to Amazon S3';
$string['task_transfer_database_backups'] = 'Transfer completed database backup artifacts to Amazon S3';
$string['transferenabled'] = 'Enable scheduled transfer';
$string['transferenabled_desc'] = 'Disabled by default. Enable only after the source directory and S3 destination have been validated.';
