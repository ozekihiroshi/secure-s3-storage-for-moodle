# Changelog

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
