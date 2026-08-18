# Development roadmap

Secure S3 Storage is being developed in small, recoverable steps. Each step must
be restored successfully before the next storage feature is enabled.

The plugin remains self-contained. An existing ObjectFS plugin may be studied as
a prior implementation, but it will not be a required dependency.

## Scope rule

Security takes priority over feature count. A phase remains disabled by default
until its restore gate passes. The plugin will not store long-lived AWS keys,
restore a production site from a web request, or automatically delete verified
backups. Bucket administration, IAM administration, and encryption-key custody
remain outside the plugin. Optional convenience features that widen permissions
or failure impact will be omitted unless a recovery requirement and an isolated
test justify them.

## Current: course backup transfer

```text
Moodle standard course backup -> .mbz -> Secure S3 Storage -> Amazon S3
```

This is implemented and has been verified with MinIO and Amazon S3, including a
downloaded archive restored as a separate Moodle course.

**Why first:** Moodle already creates the archive, so S3 transfer and recovery
could be proved without changing Moodle's database or live file storage.

## Current development: database backup

```text
MariaDB -> consistent dump -> gzip + manifest -> Secure S3 Storage -> S3
```

The reference producer now creates a compressed dump without giving
database-administrator credentials to the Moodle web process. The 0.3
development plugin validates the exact v1 manifest, uploads and reads back the
payload and completion manifest, and records the result. A MinIO download into
a separate volume has been restored into an isolated MariaDB and read by a fresh
Moodle container.

**Why next:** Course archives do not contain all site state. A database backup is
the smallest next step toward full-site recovery.

**Remaining release gate:** A clean plugin ZIP must pass upgrade, invalid-manifest,
remote-corruption, MinIO restore, and AWS IAM-separated recovery tests before
database transfer is released.

## Then: content backup

```text
moodledata/filedir -> consistent snapshot -> Secure S3 Storage -> S3 backup
```

Database and content artifacts will share a recovery-set identifier. The first
implementation protects the existing local content pool and does not change
Moodle's normal file storage.

**Why after the database:** Moodle stores file meaning and references in the
database. Content objects alone cannot reconstruct the site.

**Complete when:** An isolated environment restores a matched database and
content set and verifies representative Moodle files.

## Then: native S3 content storage

```text
Moodle File API -> Secure S3 Storage filesystem -> S3 primary bucket
```

Secure S3 Storage will implement or own the required Moodle File System API
adapter. It will use the AWS SDK default credential provider chain and will not
provide settings for long-lived AWS access keys. No external ObjectFS plugin
will be required.

**Why after backup:** Changing live storage has a larger failure impact than
copying completed backup artifacts. A proven database and content recovery path
must exist before migration begins.

**Complete when:** Migration, normal read/write/delete behavior, rollback,
credential refresh, S3 failure behavior, upgrades, and large-file performance
pass in isolated Moodle 5.2 tests.

## Finally: protect content whose primary store is S3

```text
S3 primary bucket -> Versioning / replication / Object Lock
                  -> independent backup bucket or account
```

AWS storage-native protection will make the copy. Secure S3 Storage will connect
the protected objects to a database recovery set and report whether the required
content is recoverable.

**Why required:** A live S3 bucket is primary storage, not a backup. Deletion,
credential compromise, lifecycle mistakes, account failure, or regional failure
can otherwise affect the only copy.

**Complete when:** A database recovery set can be restored against the
independent S3 copy without using the primary bucket.

## Responsibilities that remain with the operator

The plugin does not place `.env`, `config.php`, private encryption keys,
custom Moodle code, or infrastructure credentials into S3. The deployment
operator protects those materials separately and records their retrieval in the
disaster-recovery runbook.

Detailed trust boundaries, artifact hand-off rules, and repository ownership are
defined in [`backup-architecture.md`](backup-architecture.md).
