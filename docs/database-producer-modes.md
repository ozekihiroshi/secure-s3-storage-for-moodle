# Database backup producer modes

Secure S3 Storage supports two deliberately separate ways to create database
backup artifacts. Both modes use the same validation, S3 transfer, audit, and
isolated-recovery boundary after an artifact has been published.

## Built-in producer (standard operation)

The built-in producer is intended for administrators who install only the
Moodle plugin. Moodle Cron creates the artifact and transfers it without a
shell command, a companion container, or database credentials stored in plugin
settings.

The first implementation supports MariaDB and MySQL-compatible Moodle drivers.
It starts a repeatable-read, read-only database transaction and streams the
Moodle core DTL XML export to a private directory below `moodledata`. The XML is
then compressed, hashed, and published by atomic rename. The manifest is always
published last.

This is a portable logical Moodle database export. Recovery requires an empty,
isolated database and the same Moodle database schema version. It is not a
substitute for Moodle content-file backup.

Built-in production and transfer are disabled by default. Enabling the database
scheduled task is an explicit administrator decision.

## External producer (advanced operation)

The external producer mode keeps the version 1 artifact contract. A separately
privileged process uses the database vendor's backup utility and publishes a
completed payload and manifest into a read-only hand-off directory. The plugin
never receives the producer's database credentials and never executes an
administrator-supplied command.

Use this mode when operational policy requires stronger process and credential
separation, a database-native dump, support for a database engine not yet
handled by the built-in producer, or independent scheduling and monitoring.

Docker, systemd, Kubernetes, and managed export jobs may all implement the same
external contract. None is a required plugin dependency.

## Compatibility and safe defaults

- Fresh installations select the built-in producer but keep database backup
  disabled.
- Installations upgraded from 0.3.0 retain external producer mode, so an
  existing hand-off workflow does not silently change.
- Switching producer mode never enables a task by itself.
- Restore is never run by a web request and never targets the live database.
- A failed production or transfer attempt preserves completed local artifacts.

## Security boundary

The built-in producer trades process isolation for simple deployment. It uses
only the Moodle database connection already available to the application and
writes below the non-web-accessible Moodle data directory.

The external producer keeps database credentials, native dump tools, and dump
creation outside the Moodle process. The plugin receives read-only artifact
access and AWS credentials only through the AWS SDK default provider chain.

Both modes require independent S3 controls such as least-privilege IAM,
encryption at rest, Versioning or Object Lock where appropriate, lifecycle
rules, and restore testing.
