# Amazon S3 Lifecycle baseline

## Initial retention policy

The initial policy is **30 days for recovery sets and indefinite retention for
shared content objects**. Apply it at the bucket layer, not from Moodle or the
plugin.

The policy separates four key domains below the plugin's configured prefix:

| Key domain | Purpose | Current-version policy | Noncurrent-version policy |
| --- | --- | --- | --- |
| `v1/` | Moodle course `.mbz` archives | Expire after 30 days | Permanently delete 30 days after becoming noncurrent |
| `database/` | Database recovery payloads and manifests | Expire after 30 days | Permanently delete 30 days after becoming noncurrent |
| `content/v1/recovery-sets/` | Content inventories and completion manifests | Expire after 30 days | Permanently delete 30 days after becoming noncurrent |
| `content/v1/objects/` | Content-addressed, deduplicated Moodle `filedir` objects | No expiration | No expiration |

The database artifacts and content recovery-set metadata with the same
`recoverysetid` form one recoverable site snapshot. Their rules therefore use
the same lifetime. Shared content objects are not recovery-set metadata: one
object can be referenced by many recovery sets, so no age-based deletion rule
may target `content/v1/objects/`.

With S3 Versioning enabled, current-version expiration creates a delete marker
and makes the previous current version noncurrent. The additional 30-day
noncurrent-version window therefore provides rollback protection after the
normal 30-day recovery window. Physical storage can remain billable for about
60 days from initial creation. Lifecycle execution is asynchronous, so 30 days
is an eligibility boundary rather than an exact deletion timestamp.

## Required safety boundary

- Enable and verify S3 Versioning before enabling expiration rules.
- Apply Lifecycle configuration through a separate AWS operator or
  infrastructure-administration identity.
- Do not grant `s3:PutLifecycleConfiguration`, `s3:PutBucketVersioning`, or
  equivalent administration to the Moodle EC2 workload role.
- Keep the plugin's runtime object permissions scoped to its dedicated bucket
  and prefix.
- Do not add an overlapping bucket-wide expiration rule.
- Do not transition the initial baseline to Glacier. First retain a simple,
  directly restorable baseline and measure storage cost and recovery time.
- Run and record an isolated recovery rehearsal before enabling the rules and
  after any key-layout or retention change.

## Lifecycle configuration example

Replace `moodle-test/` with the exact configured plugin prefix. Prefixes do not
start with `/` and do end with `/` in this example.

```json
{
  "Rules": [
    {
      "ID": "secure-s3-course-recovery-30-days",
      "Status": "Enabled",
      "Filter": { "Prefix": "moodle-test/v1/" },
      "Expiration": { "Days": 30 },
      "NoncurrentVersionExpiration": { "NoncurrentDays": 30 }
    },
    {
      "ID": "secure-s3-database-recovery-30-days",
      "Status": "Enabled",
      "Filter": { "Prefix": "moodle-test/database/" },
      "Expiration": { "Days": 30 },
      "NoncurrentVersionExpiration": { "NoncurrentDays": 30 }
    },
    {
      "ID": "secure-s3-content-recovery-metadata-30-days",
      "Status": "Enabled",
      "Filter": { "Prefix": "moodle-test/content/v1/recovery-sets/" },
      "Expiration": { "Days": 30 },
      "NoncurrentVersionExpiration": { "NoncurrentDays": 30 }
    },
    {
      "ID": "secure-s3-abort-incomplete-multipart-uploads",
      "Status": "Enabled",
      "Filter": { "Prefix": "moodle-test/" },
      "AbortIncompleteMultipartUpload": { "DaysAfterInitiation": 7 }
    }
  ]
}
```

There is intentionally no rule for
`moodle-test/content/v1/objects/`. The multipart rule only aborts uploads that
were never completed; it does not remove completed objects.

## Review and staged activation

Before applying the configuration:

1. Confirm the bucket name, configured plugin prefix, and Versioning status.
2. Export and retain the bucket's existing Lifecycle configuration.
3. List representative keys below each of the four domains and confirm that no
   unrelated application uses the selected prefix.
4. Confirm that the latest matching database/content recovery set passes an
   isolated restore rehearsal.
5. Have a second operator review the exact JSON and the three expiration
   prefixes.

After applying it, use the S3 console's Lifecycle view and object metadata to
confirm the calculated expiration for representative recovery artifacts.
Monitor S3 Lifecycle expiration events or inventory rather than treating rule
creation as proof that deletion occurred. Re-run the isolated restore rehearsal
from an object that remains inside the 30-day window.

Changing, disabling, or deleting a Lifecycle rule does not reverse actions
already completed by S3. Treat every activation or modification as a
destructive production change with a recorded approval and rollback plan.

## Validated AWS baseline

On 2026-08-20, this baseline was validated on an EC2 development deployment
using a dedicated test bucket and plugin prefix.

- S3 Versioning was enabled before new recovery artifacts were written.
- Newly written database and content recovery-set objects received non-null
  version IDs.
- The course archive reported the 30-day course rule and an expiration date.
- Database and recovery-set manifests reported their respective 30-day rules.
- A shared content object reported no expiration rule.
- A newly generated, matching database/content recovery set passed
  corrupt-inventory rejection, isolated database restore, isolated
  `filedir` reconstruction, and Moodle File API verification.

## AWS references

- [Expiring objects](https://docs.aws.amazon.com/AmazonS3/latest/userguide/lifecycle-expire-general-considerations.html)
- [Lifecycle configuration elements](https://docs.aws.amazon.com/AmazonS3/latest/userguide/intro-lifecycle-rules.html)
- [Lifecycle configuration examples](https://docs.aws.amazon.com/AmazonS3/latest/userguide/lifecycle-configuration-examples.html)
- [Aborting incomplete multipart uploads](https://docs.aws.amazon.com/AmazonS3/latest/userguide/mpu-abort-incomplete-mpu-lifecycle-config.html)
- [Required permissions for S3 API operations](https://docs.aws.amazon.com/AmazonS3/latest/userguide/using-with-s3-policy-actions.html)
