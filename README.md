# Secure S3 Storage for Moodle

[![ZIP release gate](https://github.com/ozekihiroshi/secure-s3-storage-for-moodle/actions/workflows/release-gate.yml/badge.svg)](https://github.com/ozekihiroshi/secure-s3-storage-for-moodle/actions/workflows/release-gate.yml)
[![Moodle Plugin CI](https://github.com/ozekihiroshi/secure-s3-storage-for-moodle/actions/workflows/moodle-plugin-ci.yml/badge.svg)](https://github.com/ozekihiroshi/secure-s3-storage-for-moodle/actions/workflows/moodle-plugin-ci.yml)

Secure S3 Storage is a Moodle administrator tool for transferring completed
Moodle course backup archives and validated database backup artifacts to Amazon
S3 without storing long-lived AWS access keys in Moodle.

The plugin does not create course backups. Moodle's automated course backup
system creates `.mbz` archives in an administrator-selected directory, and this
plugin transfers completed archives from that directory to S3:

```text
Moodle automated course backups
        -> shared backup directory
        -> Secure S3 Storage scheduled task
        -> Amazon S3
```

Version 0.3 supports Moodle-generated `.mbz` course backups and an external,
manifest-validated MariaDB artifact path. The current development branch adds
a plugin-only built-in database producer for MariaDB/MySQL while retaining the
external producer as an advanced isolation option. It is not yet a complete
site disaster-recovery product: full recovery also requires `moodledata`,
configuration, and locally added code.

The planned order is database backup, local content backup, native S3 content
storage without an external plugin dependency, and independent protection of
that primary S3 content. See [`docs/roadmap.md`](docs/roadmap.md) for the
reason and completion condition for each step. Detailed boundaries are in
[`docs/backup-architecture.md`](docs/backup-architecture.md). Content backup and
primary S3 content storage are not implemented in version 0.3.

## Status

The latest public release is 0.3.0 Alpha for controlled course and database
artifact evaluation on Moodle 5.2. Course and database transfers remain
independently disabled by default. Fresh development installations select the
built-in database producer; 0.3 upgrades retain external mode. The course task discovers stable
top-level `.mbz` files, streams them to S3, reads them back to verify their
SHA-256 digest and size, records transfer history, suppresses duplicates, and
preserves the local archive.

AWS credentials are never entered in Moodle. The bundled AWS SDK uses its
default credential provider chain. A runtime-only endpoint override supports
S3-compatible development services such as MinIO without weakening the Moodle
settings boundary.

Every push to `main` and every pull request runs an isolated ZIP release gate.
The workflow installs the plugin ZIP into an empty Moodle environment, rejects
an abnormal database manifest and a checksum-corrupt payload, and confirms no
completion manifest is published. It then transfers and restores a real
MariaDB dump through MinIO and a separate empty database. The existing course
backup, checksum, and restore gate also runs without a plugin source bind mount.

## Component

- Moodle component: `tool_secure_s3_storage`
- Installation path: `admin/tool/secure_s3_storage`
- Initial Moodle target: 5.2
- License: GPL-3.0-or-later

The security boundary and release acceptance criteria are defined in
[`docs/security-model.md`](docs/security-model.md).

## Requirements

- Moodle 5.2
- PHP 8.2 or later, as required by Moodle 5.2
- PHP cURL support
- An Amazon S3 bucket and a runtime AWS identity with access to the dedicated
  destination prefix
- A server directory containing completed Moodle course backup `.mbz` files
- Moodle Cron running regularly so automated backups and the plugin scheduled
  task can execute

AWS charges may apply. This plugin does not create buckets, IAM identities, or
Moodle automated backup schedules.

## Installation

Download `tool_secure_s3_storage.zip` from the matching GitHub Release. Install
it through **Site administration > Plugins > Install plugins**, or extract the
`secure_s3_storage` directory to `admin/tool/` and complete the Moodle upgrade.

Official release ZIP files include the pinned AWS SDK runtime. GitHub source
archives do not contain `vendor/` and are not installable release packages.

## Configuration

1. Open **Site administration > Courses > Backups > Automated backup setup**.
   Enable automated backups, select the schedule and retention policy, and set
   **Save to...** to a dedicated directory readable by the web and cron
   processes. The companion Docker environment uses `/var/moodlebackups`.
2. Supply AWS credentials through the runtime environment, IAM instance or task
   role, web identity, or another AWS SDK credential provider.
3. Open **Site administration > Plugins > Admin tools > Secure S3 Storage**.
4. Configure the AWS region, bucket, dedicated prefix, source directory, and
   stability interval.
5. Enable scheduled transfer only after validating the destination permissions.

The backup directory must refer to the same storage from both the Moodle web
process and the process running Cron. The plugin preserves successfully
transferred local archives, so configure Moodle's automated-backup retention
settings and monitor local capacity.

See [`docs/operations.md`](docs/operations.md) for course-backup setup and
[`docs/database-artifact-v1.md`](docs/database-artifact-v1.md) for the exact
database hand-off contract. The reference producer and isolated S3 round-trip
restore gate live in the companion `moodle-rescue` repository.

For database backup, choose one producer mode. **Built-in Moodle producer**
requires only the plugin, MariaDB/MySQL, Moodle Cron, and S3 runtime identity;
it writes a private Moodle DTL XML artifact below `moodledata` and transfers it
in the same scheduled run. **External producer** uses the separately mounted
read-only hand-off directory and keeps native dump credentials and execution
outside Moodle PHP. Neither mode exposes a web restore action.

See [`docs/database-producer-modes.md`](docs/database-producer-modes.md), the
external [`docs/database-artifact-v1.md`](docs/database-artifact-v1.md), and
the built-in [`docs/database-artifact-v2.md`](docs/database-artifact-v2.md).

The initial IAM policy should allow `s3:PutObject`, `s3:GetObject`, and
`s3:DeleteObject` for incomplete verification cleanup below the configured
prefix. The plugin does not currently perform retention or delete successfully
verified objects.

## Privacy and external service

Course archives and database backup artifacts can contain participant names,
identifiers, activity content, submissions, grades, site configuration, and
other personal data. When the corresponding transfer is enabled, each eligible
artifact is sent to the
administrator-configured Amazon S3 or compatible destination. The site operator
is responsible for the destination's region, access controls, encryption,
retention, legal basis, and data-processing notices.

Moodle stores operational transfer audit metadata, including the local
filename, size, checksum, object key, status, and timestamps. The plugin does
not send static AWS credentials or store them in Moodle settings.

## Releases

Release ZIP files are generated from a clean Git commit using the committed
Composer lock file. Each GitHub Release also contains a SHA-256 checksum file.
See [`CHANGES.md`](CHANGES.md) for release history.

Marketplace submission text is maintained in
[`docs/marketplace.md`](docs/marketplace.md).
