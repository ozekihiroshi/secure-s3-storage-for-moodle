# Security model

## Purpose

Secure S3 Storage transfers completed Moodle course backup archives and
validated database backup artifacts to a dedicated Amazon S3 bucket prefix. Its
security value is narrow credentials,
strict file and object boundaries, integrity verification, safe retention, and
recoverability.

This document is normative. A feature that violates these constraints must not
be enabled or released.

## Initial scope

Version 0.3 covers Moodle-generated `.mbz` course backup archives and transfer
of externally produced MariaDB artifacts under the exact v1 manifest contract.
It does not yet protect all of `moodledata`, configuration, or locally installed
code.

## Protected assets

- Course content and files contained in `.mbz` archives.
- Site state and personal data contained in completed database artifacts.
- Personal or sensitive data included by Moodle backup settings.
- AWS workload credentials supplied by the runtime environment.
- S3 objects below the configured plugin prefix.
- Local backup archives before and after transfer.
- Transfer history and integrity metadata.

## Trust boundaries

1. Moodle administrators configure a source directory and S3 destination.
2. Moodle creates course backups independently of this plugin.
3. A deployment-controlled producer creates DB artifacts without exposing DB
   credentials or dump execution to Moodle PHP.
4. The plugin reads only eligible completed artifacts from independently
   configured source directories.
5. The AWS SDK obtains credentials from its default provider chain.
6. Amazon S3 stores artifacts below the dedicated prefix.
7. Restore execution remains outside all web and scheduled transfer paths.

## Mandatory controls

### Credentials

- Do not provide settings for AWS access key IDs, secret access keys, or
  session tokens.
- Do not store AWS credentials in Moodle configuration or plugin tables.
- Use the AWS SDK default credential provider chain.
- Permit a non-AWS S3 endpoint only through runtime environment configuration
  used by development and tests, never through Moodle settings.
- Never include credentials, signed headers, tokens, or credential-provider
  diagnostics in user-visible errors or transfer history.

### Source files

- Require an absolute, canonical source directory.
- Reject missing, unreadable, symlinked, or out-of-bound source paths.
- Accept regular files with the exact `.mbz` extension only.
- Reject symlinks and files whose canonical path escapes the source directory.
- Do not transfer a file until its size and modification time are stable across
  the configured observation interval.
- Open files for streaming and handle partial reads and writes completely.
- Never modify or delete a Moodle-generated archive before a verified upload.

### Database artifacts

- Keep DB credentials and dump execution outside Moodle settings and PHP.
- Mount the completed-artifact directory read-only in Cron and do not mount it
  in the Moodle web container.
- Require the exact artifact v1 fields and reject unknown fields, unsafe names,
  symlinks, hard-link aliases, traversal, nested paths, and unsupported values.
- Publish and transfer payload first and manifest last.
- Re-open by file identity and verify size, modification time, and streamed
  SHA-256 against the manifest before upload.
- Keep restore execution outside the plugin and require an isolated empty target.

### S3 objects

- Normalize region, bucket, and prefix before use.
- Reject empty buckets, unsafe prefixes, traversal segments, and control bytes.
- Generate collision-resistant object keys below the configured prefix.
- Stream uploads; do not load an entire backup artifact into PHP memory.
- Compute SHA-256 locally and request or record S3 integrity metadata.
- Mark a transfer successful only after remote integrity has been verified.
- Make retries idempotent and prevent concurrent duplicate transfers.

### Retention

- Run retention only after a successful, verified upload.
- List and delete only below the normalized dedicated prefix.
- Delete only objects matching the plugin's versioned object-key grammar.
- Treat unknown objects as out of scope.
- Never delete remote backups during plugin uninstall.

### Authorization and audit

- Grant management to site administrators by default.
- Require a system-context capability and Moodle session-key validation for
  every state-changing web action.
- Record outcome, archive identity, checksum, size, backend, object key, and
  timestamps without secrets or raw exception details.
- Use Moodle Task API locks to prevent overlapping scans and transfers.

## Failure behavior

- Fail closed when the source boundary, destination, credentials, or integrity
  cannot be established.
- Preserve the local archive after upload or verification failure.
- Do not run retention after any transfer or verification failure.
- Present actionable but sanitized administrator messages.
- Preserve detailed sanitized diagnostics in Moodle task logs.

## Release gates

- Moodle coding-style and automated plugin checks pass.
- Source-boundary, symlink, stability, partial I/O, idempotency, and retention
  tests pass.
- The built release archive alone passes installation and upgrade tests.
- Connection read/write/read-back/delete succeeds against an S3-compatible test
  service without credentials stored in Moodle.
- The final build succeeds against real Amazon S3 using workload credentials.
- A downloaded `.mbz` matches its original SHA-256 and restores successfully in
  an empty Moodle test environment.
- A downloaded DB payload and manifest pass local corruption checks, import into
  a uniquely named empty MariaDB database, and are read by a fresh Moodle image.
- Install, disable, re-enable, upgrade, and uninstall behavior are verified.
