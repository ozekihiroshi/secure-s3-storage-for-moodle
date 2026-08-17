# Moodle Marketplace readiness

## Automated evidence

- Component and install path: `tool_secure_s3_storage` at
  `admin/tool/secure_s3_storage`.
- Supported Moodle version: 5.2.
- Maturity: alpha.
- License: GPL-3.0-or-later.
- Public source, issue tracker, documentation, security policy, and tagged
  self-contained release ZIP are available on GitHub.
- The ZIP release gate installs the generated archive into an empty Moodle
  environment and verifies backup transfer, download, digest, and restore.
- Moodle Plugin CI checks PHP syntax, Moodle coding style, PHPDoc, plugin
  validation, upgrade savepoints, and PHPUnit installation on MariaDB and
  PostgreSQL at the supported PHP range endpoints.
- The Privacy API declares the local transfer audit table and the external S3
  archive destination.
- `thirdpartylibs.xml`, Composer lock data, licenses, and runtime reconstruction
  instructions accompany the bundled AWS SDK dependencies.

## Reviewer reproduction

Reviewers do not need production AWS credentials. The companion
`ozekihiroshi/moodle-rescue` repository provisions local MinIO credentials,
creates a real Moodle course backup, installs the release ZIP into an empty
Moodle instance, downloads the object, verifies its SHA-256 digest, and
restores the course.

Real Amazon S3 validation using an EC2 instance role without static access keys
is documented in that repository. Live AWS credentials, Moodle archives, and
production configuration must never be attached to a Marketplace submission.

## Manual submission items

- Confirm that the Marketplace component name is available.
- Create or sign in to the Moodle Marketplace provider account.
- Decide whether to rename the GitHub repository to the recommended
  `moodle-tool_secure_s3_storage` convention; this does not change the component.
- Confirm the Marketplace transition policy for the bundled Japanese language
  pack and move it to AMOS when the component becomes registered.
- Upload the release ZIP asset, not GitHub's automatic source archive.
- Enter the short and full descriptions from `docs/marketplace.md`.
- Add the source, issues, documentation, and security URLs.
- Capture a settings-page screenshot showing no secrets or production
  identifiers, and an optional scheduled-task success screenshot.
- State clearly that an AWS account and S3 charges may be required.
- Keep the listing marked alpha until Marketplace review and broader failure,
  concurrency, upgrade, and lifecycle tests are complete.
