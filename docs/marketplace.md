# Moodle Marketplace submission information

## Short description

Transfer verified Moodle course backups to Amazon S3 without storing long-lived
AWS access keys in Moodle.

## Full description

Secure S3 Storage is an administrator tool for Moodle 5.2. It discovers stable
top-level `.mbz` course backup files in a configured server directory, streams
them to a dedicated Amazon S3 bucket prefix, and verifies each remote object by
size, metadata, and SHA-256 read-back before marking the transfer complete.

The plugin does not create or schedule course backups. The site administrator
must configure Moodle automated course backups to write to the source
directory, ensure that the Moodle web and Cron processes share that storage,
and run Moodle Cron regularly.

The plugin uses the AWS SDK default credential provider chain. Deployments can
therefore use IAM roles, container credentials, web identity, or another
runtime-provided AWS credential source without saving access keys in Moodle.
Transfers are disabled by default, local archives are preserved, retries are
idempotent, and the plugin never performs remote retention or deletion.

The plugin handles Moodle course backup archives only. Full-site recovery also
requires independent backups of the database, `moodledata`, configuration, and
locally installed code.

Course backup archives can contain participant identifiers, activity content,
submissions, grades, and other personal data selected by Moodle backup settings.
The site operator chooses and controls the S3 destination and is responsible for
its region, encryption, access policy, retention, legal basis, and applicable
privacy notices.

## URLs

- Source: https://github.com/ozekihiroshi/secure-s3-storage-for-moodle
- Issues: https://github.com/ozekihiroshi/secure-s3-storage-for-moodle/issues
- Documentation: https://github.com/ozekihiroshi/secure-s3-storage-for-moodle#readme
- Security: https://github.com/ozekihiroshi/secure-s3-storage-for-moodle/security/policy
- QA environment: https://github.com/ozekihiroshi/moodle-rescue

## Submission notes

- Component: `tool_secure_s3_storage`
- Install path: `admin/tool/secure_s3_storage`
- Supported Moodle versions: 5.2
- Maturity: alpha
- License: GNU GPL v3 or later
- Distribution: free
- External service: Amazon S3 using runtime-provided AWS credentials
- Subscription: an AWS account and applicable S3 charges may be required
- Privacy: selected course backup content is transferred to the configured S3
  destination; transfer audit metadata is stored in Moodle
- Dependencies: the official ZIP bundles the pinned AWS SDK; administrators do
  not run Composer during installation

## Reviewer validation

The companion `ozekihiroshi/moodle-rescue` repository provides an automated
review path using local MinIO credentials. It creates a Moodle course backup,
transfers and verifies it, installs only the generated plugin ZIP into an empty
Moodle environment, downloads the object, verifies its SHA-256 digest, and
restores the course. Reviewers do not need production AWS credentials.

Real Amazon S3 validation with an EC2 instance role and no static access keys is
documented in that repository. Never include live credentials, backup archives,
or production configuration in a Marketplace submission.
