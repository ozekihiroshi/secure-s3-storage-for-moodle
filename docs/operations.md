# Automated backup and S3 transfer operations

Secure S3 Storage transfers completed Moodle course backup archives. It does
not create backups, select course content, or schedule Moodle's backup process.
Those responsibilities remain with Moodle's automated course backup system.

The operating path is:

```text
Moodle Cron
  -> Moodle automated course backup creates an .mbz archive
  -> the archive is written to a shared backup directory
  -> Secure S3 Storage waits for the archive to become stable
  -> the archive is uploaded to S3 and read back for verification
```

## Prerequisites

- A supported Moodle installation with Cron running regularly.
- An Amazon S3 bucket and a dedicated object prefix.
- A runtime AWS identity with access to that bucket and prefix. Do not enter
  long-lived AWS access keys in Moodle settings.
- An absolute server directory that the Moodle web and Cron processes can both
  read. Moodle must also be able to write automated backups to it.

The directory is configurable. The companion `moodle-rescue` Docker deployment
uses `/var/moodlebackups`, which is also the plugin default.

## 1. Prepare the shared backup directory

For a conventional installation, create an absolute directory outside the web
root and grant only the Moodle runtime account the required access.

For Docker, mount the same named volume at the same path in both the web and
Cron services. For example:

```yaml
services:
  moodle:
    volumes:
      - moodle_backups:/var/moodlebackups

  moodle-cron:
    volumes:
      - moodle_backups:/var/moodlebackups

volumes:
  moodle_backups:
```

Do not use a directory inside the public web root. Confirm that files created
by the Moodle web process are visible to the Cron process before enabling S3
transfer.

## 2. Configure Moodle automated course backups

Open:

**Site administration > Courses > Backups > Automated backup setup**

Then:

1. Enable automated backups.
2. Select the days and execution time. Choose a low-traffic period because
   course backups can be CPU- and storage-intensive.
3. Set **Save to...** to the shared absolute directory. With the companion
   Docker deployment, enter `/var/moodlebackups`.
4. Select the backup contents appropriate for the site's recovery and privacy
   requirements.
5. Configure deletion of old backups and the minimum number of backups to keep.
6. Save the settings and confirm Moodle accepts the destination path.

Moodle's backup report is available under **Site administration > Reports >
Backups**. Moodle can skip unchanged or hidden courses according to the
automated-backup settings; a skipped course is not an S3 transfer failure.

The Moodle 5.2 documentation describes these settings in
[Automated course backup](https://docs.moodle.org/502/en/admin/backup).

## 3. Run Moodle Cron

Moodle's automated backup task and the Secure S3 Storage transfer task are both
scheduled tasks. They run only when Moodle Cron runs. Moodle recommends running
Cron at least once per minute.

A conventional installation normally invokes:

```sh
php admin/cli/cron.php
```

The companion Docker deployment runs this continuously in the `moodle-cron`
service. Cron and the web service must use the same Moodle database,
`moodledata`, plugin code version, and backup volume.

See Moodle's
[command-line Cron documentation](https://docs.moodle.org/502/en/Installing_Moodle_using_command_line)
for general installation guidance.

## 4. Configure Secure S3 Storage

Open:

**Site administration > Plugins > Admin tools > Secure S3 Storage**

Configure:

- **AWS region**: the region containing the bucket.
- **S3 bucket**: the existing destination bucket.
- **S3 prefix**: a dedicated prefix owned by this deployment.
- **Source directory**: the same absolute directory configured in Moodle's
  automated backup settings.
- **Stability period**: how long an archive must retain the same size and
  modification time across scans before it is eligible for transfer.

Leave scheduled transfer disabled until the directory, IAM policy, and S3
destination have been validated. Then enable **Enable scheduled transfer**.

The task scans only top-level `.mbz` files in the configured directory. A
successful transfer is streamed to S3, read back, checked for matching size and
SHA-256, and recorded in Moodle to suppress duplicate transfers.

## 5. Validate the complete path

Use a non-sensitive test course. A Moodle administrator can create a test
archive with the standard CLI backup command:

```sh
php admin/cli/backup.php --courseid=COURSE_ID --destination=/var/moodlebackups
```

Replace the destination when a different shared directory is configured.
Confirm that the resulting `.mbz` is visible to the Cron process.

The transfer task normally runs every five minutes. It can be invoked for an
administrator-controlled test with:

```sh
php admin/cli/scheduled_task.php --execute='\tool_secure_s3_storage\task\transfer_course_backups'
```

The stability check compares observations across task runs. A newly discovered
archive may therefore be reported as waiting during its first scan. Allow the
configured stability period to pass before the next scan.

A successful run reports that the archive was transferred and verified. Also
confirm the object below the configured S3 prefix and retain a documented
restore test appropriate for the site's recovery plan.

## Retention and recovery boundary

The plugin preserves the local `.mbz` after a successful transfer and does not
delete verified S3 objects. Configure Moodle's local automated-backup retention
and an S3 lifecycle policy according to the site's requirements, and monitor
both storage locations.

Course `.mbz` archives are not a complete Moodle site backup. Recovering a full
site also requires independently protected copies of the database,
`moodledata`, configuration, and locally installed code. Test restoration on a
separate Moodle environment rather than assuming that an upload alone proves
recoverability.

## Database backup modes

Database backup is separate from Moodle automated course backups. In the
current development version, select a producer under **Secure S3 Storage >
Database backup artifacts** and then explicitly enable the database scheduled
task.

**Built-in Moodle producer** is the standard plugin-only path for
MariaDB/MySQL. Moodle Cron opens a repeatable-read, read-only transaction, uses
Moodle core's DTL exporter, compresses and hashes the XML below the private
`moodledata/tool_secure_s3_storage/database` directory, publishes its manifest
last, and transfers it in that task run. No database password or executable
path is added to plugin settings.

**External producer** is the advanced isolation path. A privileged deployment
producer publishes a completed native payload and manifest into a hand-off
directory. Moodle Cron reads that directory, validates the exact v1 contract,
and transfers the pair. This mode remains appropriate for database engines not
supported by the built-in producer and sites requiring database-native tools or
strict process separation.

Administrators can use Moodle's **Scheduled tasks** page to inspect the task
and use **Run now** for an intentional test. Normal operation requires Moodle
Cron; no pasted shell sequence is required after settings are saved.

Restore only into an empty, isolated database. The built-in DTL artifact
requires the matching Moodle schema version. Use the bundled CLI-only
`admin/tool/secure_s3_storage/cli/restore_database.php`; target credentials are
read from `SECURE_S3_RESTORE_DB*` environment variables and the command refuses
the live database name or a target containing tables. The plugin never
overwrites the live database from a web request.

See [`database-producer-modes.md`](database-producer-modes.md),
[`database-artifact-v1.md`](database-artifact-v1.md), and
[`database-artifact-v2.md`](database-artifact-v2.md). The companion
`moodle-rescue` [database backup guide](https://github.com/ozekihiroshi/moodle-rescue/blob/main/docs/database-backup.md)
documents the reference external producer and isolated restore gate.