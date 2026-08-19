# Changelog

## 0.5.1 - 2026-08-19

- Keep the CLI-only DTL restore disconnected from the configured Moodle
  database while loading the minimum core libraries required for import.
- Compare recovery artifacts with the installed Moodle code build and pass the
  same verified build to the DTL importer.

## 0.5.0 - 2026-08-19

- Add an independently disabled, bounded content-object transfer task for the
  existing Moodle `filedir` pool.
- Capture a content inventory inside the same repeatable-read transaction as
  each built-in database artifact and link both with one `recoverysetid`.
- Verify canonical file-pool paths, size, Moodle SHA-1, and SHA-256 before
  storing immutable objects below deterministic `content/v1/objects/` keys.
- Publish the inventory and its completion manifest only after every referenced
  object has passed S3 read-back verification.
- Add a CLI-only restore command that rejects the live Moodle data directory,
  requires an empty isolated target, and reconstructs canonical filedir paths.
- Require the clean-ZIP matched DB/content restore and corruption-rejection
  release gates before publishing this alpha feature.

## 0.4.0 - 2026-08-19

- Add a plugin-only MariaDB/MySQL database producer using Moodle core DTL XML
  export inside a repeatable-read, read-only transaction.
- Keep the external native-dump producer as an advanced isolation mode and
  preserve that mode automatically when upgrading from 0.3.0.
- Publish private gzip payloads and strict v2 manifests atomically below
  `moodledata`, then validate and transfer them through the existing S3 audit
  path.
- Add a CLI-only recovery command that validates v2 artifacts, rejects the live
  database name and non-empty targets, and reads the target password only from
  an environment variable.
- Keep database backup disabled by default and prohibit web-triggered live
  database restore.
## 0.3.0 - 2026-08-18

- Add the strict database artifact v1 manifest and filename contract.
- Add independently disabled database artifact settings and scheduled transfer.
- Validate regular non-symlink payload/manifest pairs, exact fields, size,
  identity, UTC timestamps, and streamed SHA-256 before transfer.
- Upload and read back the database payload first and `manifest.json` last below
  `database/v1/YYYY/MM/DD/<artifactid>/`.
- Keep DB credentials, dump execution, and all restore capability outside Moodle
  PHP; use the companion deployment's isolated MinIO restore gate.
- Reject abnormal manifests and checksum-corrupt payloads without publishing an
  S3 completion manifest, while preserving the local artifact.
- Pass the clean-ZIP, empty-Moodle release gate through real MariaDB dump
  creation, MinIO transfer, download, isolated restore, and fresh-Moodle read.

## 0.2.3 - 2026-08-18

- Document the complete Moodle automated-backup, shared-directory, Cron, S3
  transfer, local-retention, and recovery operating path.
- Add a security-first development roadmap with a restore gate and explicit
  completion condition for every planned phase.
- Define the responsibility boundary shared by the plugin and the companion
  Docker reference deployment.
- Keep database backup, content backup, and native S3 primary storage clearly
  marked as unimplemented future work with no external ObjectFS dependency.
- Preserve the version 0.2 transfer behavior and alpha maturity for Marketplace
  review.

## 0.2.2 - 2026-08-17

- Add Moodle Plugin CI checks for Moodle 5.2 on MariaDB and PostgreSQL across
  the supported PHP range endpoints.
- Align the Composer dependency platform with the minimum supported PHP 8.2.
- Expand Marketplace, privacy, external-service, and reviewer documentation.
- Include third-party runtime reconstruction instructions inside the generated
  `vendor` directory.
- Rename the transfer table through an upgrade step to follow the full plugin
  component prefix.
- Conform plugin-owned PHP and language files to Moodle coding standards.
- Keep the release at alpha maturity while Marketplace review is pending.

## 0.2.1 - 2026-08-17

- Fix transfer failure reporting so the original exception is preserved and
  Moodle receives a valid `runtime_exception`.
- Confirm the complete backup, Amazon S3 upload, streamed verification,
  download, SHA-256 verification, and new-course restore path on EC2 using an
  instance role without static AWS access keys.
- Document the required least-privilege S3 access and outbound container
  networking in the companion deployment repository.

## 0.2.0 - 2026-08-16

- Add administrator settings for the backup source and S3 destination.
- Transfer stable Moodle course backup archives with idempotent tracking.
- Verify uploaded archives by size, metadata, and streamed SHA-256 read-back.
- Use the AWS SDK default credential provider chain without Moodle-stored keys.
- Add MinIO integration and isolated ZIP installation and restore release gates.
- Bundle the pinned AWS SDK runtime in release ZIP files.
