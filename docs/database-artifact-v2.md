# Database artifact contract v2

Version 2 is produced by the plugin's built-in database producer. It is kept
separate from the external MariaDB SQL artifact contract (v1).

The payload name is:

    moodle-db-YYYYMMDDTHHMMSSZ-<32 lowercase hexadecimal characters>.xml.gz

The manifest name is the payload name followed by `.manifest.json`. The
manifest is published last and contains exactly:

    {
      "schema": "tool_secure_s3_storage.artifact/v2",
      "artifactid": "0123456789abcdef0123456789abcdef",
      "type": "database",
      "createdat": "2026-08-18T03:00:00Z",
      "payload": "moodle-db-20260818T030000Z-0123456789abcdef0123456789abcdef.xml.gz",
      "bytes": 123456,
      "sha256": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
      "format": "moodle-dtl-xml",
      "compression": "gzip",
      "encryption": "none",
      "recoverysetid": "20260818T030000Z-0123456789abcdef0123456789abcdef",
      "moodleversion": 2026042002,
      "moodlerelease": "5.2.2 (Build: 20260810)",
      "dbtype": "mariadb"
    }

The scanner rejects unknown fields, invalid names or relationships, links,
hard-linked files, changed file identity or metadata, incorrect byte counts,
and incorrect streamed SHA-256 values. S3 objects are stored under
`<prefix>/database/v2/YYYY/MM/DD/<artifactid>/`.

The XML uses Moodle core's Database Transfer Library (DTL). Import must target
an empty isolated database whose Moodle schema version matches the recorded
version. Decompression and import are recovery operations and are never
started by a Moodle web request.

`encryption` is `none` in v2. The local directory is private below
`moodledata`; S3 transport uses TLS and destination encryption remains an S3
policy. Client-side encryption requires a separately reviewed contract version
with explicit key-recovery procedures.

## Isolated restore command

The plugin includes `cli/restore_database.php`. Download the manifest and
payload into the same private directory, create an empty isolated database,
and provide target connection details through `SECURE_S3_RESTORE_DB*`
environment variables. The password is not accepted as a command-line option.

```sh
export SECURE_S3_RESTORE_DBHOST=restore-db
export SECURE_S3_RESTORE_DBNAME=moodle_restore_test
export SECURE_S3_RESTORE_DBUSER=moodle_restore
export SECURE_S3_RESTORE_DBPASSWORD='use-a-secret-source'

php admin/tool/secure_s3_storage/cli/restore_database.php \
  --manifest=/private/recovery/moodle-db-TIMESTAMP-ID.xml.gz.manifest.json
```

The command refuses the configured live Moodle database name and any target
that already contains tables. It validates the exact manifest, payload
identity, size, SHA-256, and Moodle schema version before importing. Recovery
testing must still use isolated infrastructure and independently protected
credentials.