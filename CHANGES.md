# Changelog

## 0.3.0-dev - Unreleased

- Add the strict database artifact v1 manifest and filename contract.
- Add independently disabled database artifact settings and scheduled transfer.
- Validate regular non-symlink payload/manifest pairs, exact fields, size,
  identity, UTC timestamps, and streamed SHA-256 before transfer.
- Upload and read back the database payload first and `manifest.json` last below
  `database/v1/YYYY/MM/DD/<artifactid>/`.
- Keep DB credentials, dump execution, and all restore capability outside Moodle
  PHP; use the companion deployment's isolated MinIO restore gate.
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
