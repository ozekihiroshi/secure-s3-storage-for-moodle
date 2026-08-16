# Moodle Marketplace submission information

## Short description

Transfer verified Moodle course backups to Amazon S3 without storing long-lived
AWS access keys in Moodle.

## Full description

Secure S3 Storage is an administrator tool for Moodle 5.2. It discovers stable
top-level `.mbz` course backup files in a configured server directory, streams
them to a dedicated Amazon S3 bucket prefix, and verifies each remote object by
size, metadata, and SHA-256 read-back before marking the transfer complete.

The plugin uses the AWS SDK default credential provider chain. Deployments can
therefore use IAM roles, container credentials, web identity, or another
runtime-provided AWS credential source without saving access keys in Moodle.
Transfers are disabled by default, local archives are preserved, retries are
idempotent, and the plugin never performs remote retention or deletion.

The plugin handles Moodle course backup archives only. Full-site recovery also
requires independent backups of the database, `moodledata`, configuration, and
locally installed code.

## URLs

- Source: https://github.com/ozekihiroshi/secure-s3-storage-for-moodle
- Issues: https://github.com/ozekihiroshi/secure-s3-storage-for-moodle/issues
- Documentation: https://github.com/ozekihiroshi/secure-s3-storage-for-moodle#readme
- Security: https://github.com/ozekihiroshi/secure-s3-storage-for-moodle/security/policy

## Submission notes

- Component: `tool_secure_s3_storage`
- Install path: `admin/tool/secure_s3_storage`
- Supported Moodle versions: 5.2
- Maturity: alpha
- License: GNU GPL v3 or later
- External service: Amazon S3 using runtime-provided AWS credentials
- Subscription: an AWS account and applicable S3 charges may be required
- Privacy: selected course backup content is transferred to the configured S3
  destination; transfer audit metadata is stored in Moodle
