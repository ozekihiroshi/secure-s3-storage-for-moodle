# Changelog

## 0.2.0 - 2026-08-16

- Add administrator settings for the backup source and S3 destination.
- Transfer stable Moodle course backup archives with idempotent tracking.
- Verify uploaded archives by size, metadata, and streamed SHA-256 read-back.
- Use the AWS SDK default credential provider chain without Moodle-stored keys.
- Add MinIO integration and isolated ZIP installation and restore release gates.
- Bundle the pinned AWS SDK runtime in release ZIP files.
