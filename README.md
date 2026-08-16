# Secure S3 Storage for Moodle

[![ZIP release gate](https://github.com/ozekihiroshi/secure-s3-storage-for-moodle/actions/workflows/release-gate.yml/badge.svg)](https://github.com/ozekihiroshi/secure-s3-storage-for-moodle/actions/workflows/release-gate.yml)

Secure S3 Storage is a Moodle administrator tool for transferring completed
Moodle course backup archives to Amazon S3 without storing long-lived AWS
access keys in Moodle.

The first release targets Moodle-generated `.mbz` course backups. It is not a
complete Moodle site disaster-recovery product: a full site recovery also
requires the database, `moodledata`, configuration, and locally added code.

## Status

Version 0.2.0 is an alpha release for controlled evaluation on Moodle 5.2.
Transfers remain disabled by default. The scheduled task discovers stable
top-level `.mbz` files, streams them to S3, reads them back to verify their
SHA-256 digest and size, records transfer history, suppresses duplicates, and
preserves the local archive.

AWS credentials are never entered in Moodle. The bundled AWS SDK uses its
default credential provider chain. A runtime-only endpoint override supports
S3-compatible development services such as MinIO without weakening the Moodle
settings boundary.

Every push to `main` and every pull request runs an isolated ZIP release gate.
The workflow creates a real Moodle backup, transfers it to MinIO, installs the
plugin ZIP into an empty Moodle environment, restores the backup, and verifies
the course marker without repository or plugin source bind mounts.

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

AWS charges may apply. This plugin does not create buckets, IAM identities, or
Moodle automated backup schedules.

## Installation

Download `tool_secure_s3_storage.zip` from the matching GitHub Release. Install
it through **Site administration > Plugins > Install plugins**, or extract the
`secure_s3_storage` directory to `admin/tool/` and complete the Moodle upgrade.

Official release ZIP files include the pinned AWS SDK runtime. GitHub source
archives do not contain `vendor/` and are not installable release packages.

## Configuration

1. Configure Moodle automated course backups to write `.mbz` files into a
   dedicated directory readable by the web and cron processes.
2. Supply AWS credentials through the runtime environment, IAM instance or task
   role, web identity, or another AWS SDK credential provider.
3. Open **Site administration > Plugins > Admin tools > Secure S3 Storage**.
4. Configure the AWS region, bucket, dedicated prefix, source directory, and
   stability interval.
5. Enable scheduled transfer only after validating the destination permissions.

The initial IAM policy should allow `s3:PutObject`, `s3:GetObject`, and
`s3:DeleteObject` for incomplete verification cleanup below the configured
prefix. The plugin does not currently perform retention or delete successfully
verified objects.

## Releases

Release ZIP files are generated from a clean Git commit using the committed
Composer lock file. Each GitHub Release also contains a SHA-256 checksum file.
See [`CHANGES.md`](CHANGES.md) for release history.

Marketplace submission text is maintained in
[`docs/marketplace.md`](docs/marketplace.md).
