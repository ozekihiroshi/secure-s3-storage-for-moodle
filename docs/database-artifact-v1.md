# Database artifact contract v1

## Status

This document defines the first stable hand-off contract between a privileged
database backup producer and Secure S3 Storage. Version 0.3 implements it with
database transfer disabled by default. The reference producer, validator,
clean-ZIP rejection tests, MinIO S3 round-trip, and isolated MariaDB/Moodle
restore gate pass locally and in GitHub Actions. Real-AWS database recovery
remains an operator validation requirement.

The contract is independent of Docker. A container, systemd timer, Kubernetes
Job, or managed export adapter may produce the same artifact.

## Responsibility boundary

The producer:

- obtains database credentials outside Moodle settings;
- creates a transactionally consistent dump with the database vendor utility;
- compresses the dump;
- calculates the final ciphertext or payload size and SHA-256;
- publishes the payload first and the manifest last; and
- cannot access AWS credentials or restore a database.

The plugin:

- never receives database credentials;
- never runs administrator-provided commands;
- treats the completed payload as opaque;
- validates the manifest and filesystem boundary;
- uploads and reads back both payload and manifest;
- records transfer status without storing secrets; and
- cannot restore a database.

The restore executor uses separate credentials and must target an isolated
database. No web request may start a restore.

## Directory and publication protocol

A producer writes directly below one configured hand-off directory. Nested
directories, symlinks, hard-link aliases, and non-regular payloads are not part
of v1.

The publication sequence is:

1. Create temporary files with unpredictable names in the hand-off directory.
2. Restrict files before writing database content or credentials.
3. Create a consistent SQL dump and close it.
4. Compress the dump and close the compressed stream.
5. Calculate byte size and lowercase SHA-256 from the final payload.
6. Atomically rename the payload to its final name on the same filesystem.
7. Write the complete manifest to a temporary file.
8. Atomically rename the manifest to its final name last.

A manifest is eligible only after its final rename. The producer never modifies
a published payload or manifest. Failed temporary artifacts are removed.

## Names

artifactid is 32 lowercase hexadecimal characters generated from a
cryptographically secure random source.

recoverysetid has this form:

    YYYYMMDDTHHMMSSZ-<16 lowercase hexadecimal characters>

The payload name has this form:

    moodle-db-YYYYMMDDTHHMMSSZ-<16 lowercase hexadecimal characters>.sql.gz

The manifest name is the payload name followed by:

    .manifest.json

Names contain no database name, hostname, tenant name, credentials, or personal
data.

## Manifest schema

The manifest is a UTF-8 JSON object no larger than 16384 bytes. Version 1 has
exactly these fields:

    {
      "schema": "tool_secure_s3_storage.artifact/v1",
      "artifactid": "0123456789abcdef0123456789abcdef",
      "type": "database",
      "createdat": "2026-08-18T03:00:00Z",
      "payload": "moodle-db-20260818T030000Z-0123456789abcdef.sql.gz",
      "bytes": 123456,
      "sha256": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
      "format": "mariadb-sql",
      "compression": "gzip",
      "encryption": "none",
      "recoverysetid": "20260818T030000Z-0123456789abcdef"
    }

Validation rules:

- schema, type, format, compression, and encryption equal the values above;
- createdat is UTC with whole-second precision;
- bytes is a positive JSON integer;
- sha256 is exactly 64 lowercase hexadecimal characters;
- artifactid, recoverysetid, payload, and manifest filename match their grammar;
- the payload timestamp and identifier match recoverysetid;
- the manifest contains no unknown fields;
- the manifest and payload resolve inside the configured hand-off directory;
- both are regular, readable, non-symlink files;
- the payload size and streamed SHA-256 match the manifest; and
- file identity, size, and modification time remain unchanged while opened.

Version 1 records encryption as none so the initial restore gate can validate
the complete dump path. Client-side encryption requires a new reviewed contract
or an explicitly compatible schema revision. Destination S3 encryption,
Versioning, Object Lock, lifecycle, and replication remain operator controls.

## S3 layout

The plugin stores one recovery artifact under:

    <configured-prefix>/database/v1/YYYY/MM/DD/<artifactid>/

The directory contains:

    <payload filename>
    manifest.json

The manifest is uploaded only after the payload has passed remote size,
metadata, and streamed SHA-256 verification. A manifest upload failure leaves
the local artifact authoritative and must not be recorded as success.

## Local permissions

The reference producer uses a dedicated volume. It writes with umask 0077,
then publishes the completed payload and manifest as root:www-data with mode
0640. The Moodle Cron container mounts this volume read-only. The Moodle web
container does not mount it.

Credential option files remain private to the producer, use mode 0600, and are
removed on success and failure.

## Restore acceptance gate

An accepted database implementation must prove all of the following:

1. Produce a real dump from the supported MariaDB version.
2. Verify manifest size and SHA-256.
3. Import into a newly initialized isolated database.
4. Start or invoke a fresh matching Moodle application against that database.
5. Read Moodle version and representative site records.
6. Repeat after downloading the artifact and manifest from S3-compatible
   storage.
7. Remove only uniquely named test resources.

Passing upload checks alone is not recovery evidence.
