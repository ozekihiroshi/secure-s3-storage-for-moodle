# Secure S3 Storage for Moodle

Secure S3 Storage is a Moodle administrator tool for transferring completed
Moodle course backup archives to Amazon S3 without storing long-lived AWS
access keys in Moodle.

The first release targets Moodle-generated `.mbz` course backups. It is not a
complete Moodle site disaster-recovery product: a full site recovery also
requires the database, `moodledata`, configuration, and locally added code.

## Status

This repository is in early development. The administrator settings page and
scheduled transfer task are implemented, but transfers remain disabled by
default. The task discovers stable top-level `.mbz` files, streams them to S3,
reads them back to verify their SHA-256 digest and size, records transfer
history, suppresses duplicates, and preserves the local archive.

AWS credentials are never entered in Moodle. The bundled AWS SDK uses its
default credential provider chain. A runtime-only endpoint override supports
S3-compatible development services such as MinIO without weakening the Moodle
settings boundary.

## Component

- Moodle component: `tool_secure_s3_storage`
- Installation path: `admin/tool/secure_s3_storage`
- Initial Moodle target: 5.2
- License: GPL-3.0-or-later

The security boundary and release acceptance criteria are defined in
[`docs/security-model.md`](docs/security-model.md).
