# Content recovery set v1

## Status

This contract is under development for Secure S3 Storage 0.5. It remains
disabled by default until the clean plugin ZIP passes matched database/content
restore and corruption-rejection gates.

## Consistency point

The built-in database producer opens one repeatable-read, read-only transaction.
Inside that transaction it writes a sorted inventory of the distinct non-empty
content hashes referenced by Moodle's `files` table and exports the database
through Moodle's DTL exporter. The database artifact and content inventory use
the same `recoverysetid`:

```text
YYYYMMDDTHHMMSSZ-<32 lowercase hexadecimal characters>
```

Content-addressed filedir objects are immutable by Moodle convention. An object
deleted or corrupted before it is transferred causes the recovery set to remain
incomplete; its completion manifest is not published.

## Source boundary

The source is the existing non-web-accessible `moodledata/filedir` pool. The
plugin does not change Moodle primary file storage. For each inventory entry the
transfer requires:

- a lowercase 40-character SHA-1 content hash;
- the canonical `filedir/aa/bb/<contenthash>` path;
- a regular, readable, single-link, non-symlink file inside the canonical pool;
- exact agreement with the inventory size;
- streamed SHA-1 agreement with Moodle's content hash; and
- a separately calculated SHA-256 used for S3 read-back verification.

A boundary, size, identity, or digest failure stops the current batch at that
object. The cursor does not advance past a failure.

## Local inventory contract

The gzip inventory name is:

```text
moodle-content-<recoverysetid>.jsonl.gz
```

Its first JSON Lines record contains exactly `schema` and `recoverysetid`.
Each remaining record contains exactly `contenthash` and integer `filesize`,
sorted strictly by content hash with no duplicates.

The sibling `.manifest.json` contains exactly:

```json
{
  "schema": "tool_secure_s3_storage.content-recovery/v1",
  "type": "content",
  "createdat": "2026-08-19T00:00:00Z",
  "recoverysetid": "20260819T000000Z-0123456789abcdef0123456789abcdef",
  "databaseartifactid": "0123456789abcdef0123456789abcdef",
  "inventory": "moodle-content-20260819T000000Z-0123456789abcdef0123456789abcdef.jsonl.gz",
  "inventorybytes": 1234,
  "inventorysha256": "<64 lowercase hexadecimal characters>",
  "objectcount": 42,
  "contentbytes": 1048576,
  "hashalgorithm": "sha1",
  "compression": "gzip"
}
```

Unknown fields, invalid types, inconsistent identifiers, unsafe paths, changed
files, checksum mismatches, unsorted entries, duplicate hashes, and mismatched
totals are rejected.

## S3 layout and completion rule

Shared immutable content objects use:

```text
<prefix>/content/v1/objects/<sha1[0:2]>/<sha1[2:4]>/<sha1>
```

Recovery metadata uses:

```text
<prefix>/content/v1/recovery-sets/YYYY/MM/DD/<artifactid>/inventory.jsonl.gz
<prefix>/content/v1/recovery-sets/YYYY/MM/DD/<artifactid>/manifest.json
```

The plugin first requires the matching database artifact to have passed S3
read-back verification. New or failed content objects are uploaded and read
back in full. Previously verified immutable objects must pass a fresh S3 HEAD
check for size, SHA-256 metadata, and format; a missing or inconsistent object
is re-uploaded and read back. The inventory is then verified, and the completion
manifest is uploaded last. The plugin never deletes local file-pool objects or
successful remote objects.

The internal cursor is only a resumable processing position. Recovery
completeness is represented solely by the remotely verified completion
manifest.

## Isolated restore

`cli/restore_content.php` requires a downloaded v2 database manifest and its
payload, the matching content manifest and inventory, and the content-object
tree. It validates both contracts and their shared `recoverysetid`, refuses the
live Moodle data directory, requires an empty isolated destination,
reconstructs canonical `aa/bb/contenthash` paths, and refuses overwrites.

The database artifact and content manifest must have the same `recoverysetid`.
The recovery gate restores the database into a new database, the file pool into
a new volume, and reads a representative restored file through Moodle's File
API. No web request starts a restore.