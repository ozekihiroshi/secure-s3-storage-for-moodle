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

## Current: database backup

```text
Standard: Moodle DB -> built-in DTL XML producer -> gzip + manifest -> S3
Advanced: MariaDB -> external native producer -> gzip + manifest -> S3
```

Version 0.4 validates and transfers an external native MariaDB dump without
giving producer credentials to Moodle PHP and provides a plugin-only
MariaDB/MySQL producer using a repeatable-read, read-only transaction and
Moodle's streaming DTL exporter. Both paths converge on strict manifest
validation, S3 read-back, transfer audit, and an isolated recovery boundary.

**Why next:** Course archives do not contain all site state. A database backup is
the smallest next step toward full-site recovery.

**Release evidence:** A clean plugin ZIP passes upgrade, invalid-manifest,
remote-corruption, MinIO restore, and AWS IAM-separated transfer tests.

## Current development: content backup

```text
moodledata/filedir -> consistent snapshot -> Secure S3 Storage -> S3 backup
```

The built-in database producer captures a sorted inventory of non-empty Moodle
content hashes inside the same repeatable-read transaction as the DTL export.
The database artifact and content inventory share one recovery-set identifier.
The content task verifies each canonical file-pool path, size, Moodle SHA-1, and
SHA-256 before uploading to a deterministic immutable key and reading it back.
It publishes the inventory and completion manifest only after every referenced
object succeeds.

The task never deletes local or remote content and does not change Moodle's
normal file storage. It remains independently disabled by default until the
clean-ZIP matched DB/content restore and corruption rejection gate passes.

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
