# Backup and recovery architecture

## Status and purpose

This document is the architectural source of truth for extending Secure S3
Storage beyond its current course-backup transfer scope. It defines ownership,
trust boundaries, and a future artifact hand-off contract shared with the
companion `moodle-rescue` reference deployment.

The contract is a design target unless a feature is explicitly described as
implemented. Version 0.2 implements only the legacy course archive path in
[`operations.md`](operations.md); it does not ingest database or content
artifacts.

The current normative controls remain in
[`security-model.md`](security-model.md). Before another artifact type is
enabled, that model, automated tests, privacy declarations, and release
documentation must be updated.

## Recovery domains

The long-term design protects four distinct domains:

1. Moodle course archives (`.mbz`) for selective course recovery.
2. A consistent database dump for site state, users, configuration, and file
   metadata.
3. Moodle content objects, normally `moodledata/filedir` or an equivalent
   Moodle-aware object store.
4. Deployment configuration, secrets, custom code, and infrastructure state.

These domains have different producers and restore procedures. Protecting one
does not make the others recoverable. In particular, S3 content objects are not
usable as a Moodle site without the corresponding database.

## Design principles

- **Feature ownership is separate from privileged execution.** Secure S3
  Storage can own backup policy, artifact inventory, transfer, verification,
  and status without holding database-owner or host-root privileges.
- **Producers create; the plugin transports.** A producer publishes an immutable
  artifact only after it is complete. The plugin must not read a database while
  it is being dumped or a content archive while it is being written.
- **Credentials remain narrow.** Moodle runtime, database dump, restore,
  replication, and client-side encryption credentials have independent owners
  and lifecycles.
- **Recovery is the acceptance test.** Upload success is necessary but not
  sufficient. Every supported artifact type requires an isolated restore gate.
- **Destructive operations are explicit.** A web request must never overwrite a
  production database or file pool. Restore execution belongs to a controlled
  CLI or isolated recovery environment.
- **Primary storage is not automatically a backup.** S3 Versioning,
  replication, retention, and independent credentials are selected according
  to the site's threat and recovery model.

## Roles

### Producer

Creates a consistent artifact and publishes it only after the payload is closed
and its metadata is complete. Examples are Moodle's course-backup task, a
`mariadb-dump` sidecar, and a coordinated content snapshot process.

### Transfer controller

The Secure S3 Storage scheduled task validates the hand-off boundary, streams
eligible artifacts to S3, verifies remote integrity, records an audit result,
and suppresses duplicate transfers.

### Storage service

Amazon S3 or a compatible test service stores immutable artifacts below a
dedicated prefix. Bucket policy, encryption at rest, Versioning, Object Lock,
replication, and lifecycle configuration remain operator-controlled AWS
responsibilities.

### Restore executor

An administrator-controlled, non-web process downloads, verifies, decrypts when
necessary, and restores an artifact into an explicitly selected target. Its
credentials are not available to the Moodle web process.

### Deployment operator

Owns `.env`, `config.php`, database credentials, encryption-key custody,
container or host configuration, monitoring, and the disaster-recovery
runbook. These materials are not plugin-managed backup artifacts.

## Responsibility matrix

| Recovery domain | Feature owner | Producer | Transfer controller | Restore executor | Primary privileges |
| --- | --- | --- | --- | --- | --- |
| Course archive | Secure S3 Storage and Moodle automated backup | Moodle backup task | Secure S3 Storage Cron task | Moodle restore CLI or UI under administrator control | Moodle course backup access and dedicated course S3 prefix |
| Database dump | Secure S3 Storage backup policy; deployment supplies the engine adapter | Dedicated database backup process | Secure S3 Storage Cron task | Offline or isolated database recovery process | Narrow dump credentials and dedicated database S3 prefix |
| Content backup | Secure S3 Storage backup policy; deployment supplies the snapshot adapter | Coordinated filesystem snapshot process | Secure S3 Storage Cron task | Isolated content recovery process | Content-pool read access and dedicated content-backup S3 prefix |
| Primary S3 content storage | Secure S3 Storage planned feature | Moodle File API and the plugin-owned S3 filesystem | Not a backup transfer | Moodle through the same plugin | Dedicated primary-content bucket access |
| Configuration and secrets | Deployment operator | Deployment operator | Operator-selected encrypted configuration system | Deployment operator | Kept outside Moodle, plugin storage, release ZIPs, and Git |
| Custom code and container definitions | Deployment operator | Reproducible build and source control | Release/deployment system | Rebuild and redeploy process | Read-only releases and source control |

This division treats database and content protection as Secure S3 Storage
product capabilities while keeping environment-specific privileged acquisition
outside the Moodle web process.

## Artifact hand-off contract

### Current compatibility path

Version 0.2 scans stable top-level `.mbz` files in the configured source
directory. This remains the compatibility adapter for Moodle automated course
backups until a manifest-based producer is implemented.

### Future manifest path

Database and content producers use a versioned, manifest-based hand-off:

1. Write the payload under a temporary, ineligible name.
2. Close it and calculate its byte size and SHA-256 digest.
3. Atomically rename it to its final name on the same filesystem.
4. Write and atomically publish a small manifest last.
5. Treat the published manifest and payload as immutable.

The transfer controller accepts only regular files whose canonical paths remain
inside the configured hand-off directory. It rejects symlinks, traversal,
unsupported schema versions or types, unsafe names, and mismatched sizes or
digests.

An illustrative manifest is:

```json
{
  "schema": "tool_secure_s3_storage.artifact/v1",
  "artifactid": "018f4c72-4f0e-7f16-9d5c-b8c4f616be2f",
  "type": "database",
  "createdat": "2026-08-17T03:00:00Z",
  "payload": "moodle-db-20260817T030000Z.sql.gz.age",
  "bytes": 123456789,
  "sha256": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
  "format": "mariadb-sql",
  "compression": "gzip",
  "encryption": "age",
  "recoverysetid": "20260817T030000Z-daily"
}
```

This is not yet a stable public schema. Field lengths, allowed values, filename
grammar, timestamp handling, and upgrade rules must be specified in tests
before implementation. A manifest must never contain passwords, tokens,
private keys, connection strings, signed URLs, or personal data copied from
the payload.

## Destination and IAM boundary

Each recovery domain must be independently authorizable. A deployment may use
separate buckets or separate prefixes.

```text
<deployment-prefix>/course/...
<deployment-prefix>/database/...
<deployment-prefix>/content/...
<deployment-prefix>/manifests/...
```

The current version-0.2 course key grammar remains valid. A future migration
must not reinterpret or delete legacy objects.

Moodle Cron needs only the object operations required to upload and verify
enabled domains. A restore identity may be read-only. Replication and retention
identities should be separate where the AWS design permits it. No plugin role
may administer the bucket, change Object Lock or KMS policy, or assume the
restore environment's database role.

## Database backup boundary

Database backup is a planned product capability, but Moodle PHP must not
implement a general SQL exporter or execute administrator-supplied shell text.

The initial producer is a dedicated deployment process using the database
vendor's supported dump utility. For the MariaDB reference deployment it:

- receives its database credential outside Moodle settings;
- has only the privileges required for the tested dump method;
- writes to a producer-only staging directory;
- publishes through the artifact contract;
- cannot read AWS credentials or restore a database; and
- emits no secrets in filenames, manifests, logs, or process arguments where
  avoidable.

Compression and client-side encryption belong to the producer. The plugin
treats an encrypted payload as opaque and verifies its ciphertext digest.
Encryption keys never enter Moodle settings or the manifest.

The restore gate must initialize an empty supported database and prove that a
fresh Moodle application can read the restored schema. It must never target the
running production database.

## Content protection boundary

Moodle's database gives content-addressed file objects their meaning. Database
and content recovery therefore require a coordinated recovery set.

The first content phase protects an existing `moodledata/filedir` without
changing primary storage. Its producer must use a maintenance or quiescence
window, or a storage snapshot method whose consistency properties are
documented. Database and content artifacts that belong together share a
`recoverysetid`.

Moving the live file pool to S3 is a later Secure S3 Storage feature. It will
implement the required Moodle File System API adapter inside this plugin and
will not require another ObjectFS plugin. Existing implementations may be
studied as prior art but are not runtime dependencies. Mounting S3 as a POSIX
`moodledata` directory remains outside this design. Adoption requires
compatibility, migration, rollback, performance, failure-mode, cost, and
recovery testing.

When live content is already in S3, another bucket or replica is still needed
when the recovery objective includes deletion, credential compromise, or
regional/account failure. Storage-native Versioning, replication, and Object
Lock are preferred over making the Moodle plugin copy the entire live pool.

## Restore and evidence rules

- Remote checksum verification proves transport integrity, not recoverability.
- Restore downloads remain outside every monitored producer directory.
- The restore executor verifies the manifest and ciphertext digest before
  decryption or import.
- Database and content artifacts form a recovery set only when identifiers and
  documented capture methods are compatible.
- Automated restore gates use isolated projects, databases, prefixes, networks,
  and volumes.
- Cleanup removes only resources created by that unique test run.
- Production evidence remains until its named owner closes the record.

## Repository ownership

This repository owns:

- the artifact contract and validation code;
- Moodle administration, capability, privacy, audit, and scheduled-task logic;
- S3 upload, read-back, duplicate suppression, and future managed retention;
- administrator-visible recovery inventory and health; and
- plugin unit, integration, upgrade, and release-ZIP gates.

The `moodle-rescue` repository owns:

- Docker and host reference producers;
- database, volume, network, and secret wiring;
- example IAM and AWS deployment boundaries;
- isolated full-path restore gates; and
- deployment-specific operations and recovery instructions.

A `moodle-rescue` adapter does not require every plugin user to run Docker.
The hand-off contract also permits a systemd timer, Kubernetes job, managed
database export, or another operator-controlled producer.

## Planned delivery order

1. Review and stabilize the database manifest and threat model.
2. Implement the MariaDB producer and isolated restore gate in
   `moodle-rescue`.
3. Add manifest validation, database transfer, audit, and status to the plugin.
4. Validate AWS IAM separation and recovery from a released plugin ZIP.
5. Design coordinated database and content recovery sets.
6. Implement and validate current-file-pool backup.
7. Implement and validate the plugin-owned, IAM-first S3 primary-content
   filesystem without an external plugin dependency.
