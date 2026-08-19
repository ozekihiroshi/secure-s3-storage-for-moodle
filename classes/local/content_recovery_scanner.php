<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_secure_s3_storage\local;

/**
 * Validates built-in content recovery manifests and inventories.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class content_recovery_scanner {
    /** Maximum accepted completion-manifest size. */
    private const MAX_MANIFEST_BYTES = 16384;

    /** Exact completion-manifest fields. */
    private const MANIFEST_FIELDS = [
        'compression',
        'contentbytes',
        'createdat',
        'databaseartifactid',
        'hashalgorithm',
        'inventory',
        'inventorybytes',
        'inventorysha256',
        'objectcount',
        'recoverysetid',
        'schema',
        'type',
    ];

    /**
     * Finds and validates locally published content recovery manifests.
     *
     * @return array[] validated recovery-set artifacts
     */
    public function scan(): array {
        $directory = database_backup_producer::get_builtin_directory();
        if (!is_dir($directory) || is_link($directory)) {
            return [];
        }

        $prefix = realpath($directory);
        if ($prefix === false) {
            return [];
        }
        $prefix = rtrim($prefix, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $artifacts = [];
        foreach (new \DirectoryIterator($prefix) as $entry) {
            if (
                $entry->isDot() || $entry->isLink() || !$entry->isFile() ||
                !str_ends_with($entry->getFilename(), '.jsonl.gz.manifest.json')
            ) {
                continue;
            }
            try {
                $artifact = $this->validate_manifest($entry->getPathname(), $prefix);
                $databasepath = $prefix . 'moodle-db-' . $artifact['recoverysetid'] .
                    '.xml.gz.manifest.json';
                $databaseartifact = (new database_artifact_v2_scanner())
                    ->validate_manifest($databasepath);
                $matchesrecoveryset = hash_equals(
                    $databaseartifact['recoverysetid'],
                    $artifact['recoverysetid']
                );
                if (!$matchesrecoveryset) {
                    throw new \RuntimeException('Database and content recovery sets do not match.');
                }
                $artifact['databasesourcehash'] = $databaseartifact['sourcehash'];
                $artifacts[] = $artifact;
            } catch (\Throwable) {
                mtrace(get_string(
                    'task_content_manifest_rejected',
                    'tool_secure_s3_storage',
                    $entry->getFilename()
                ));
            }
        }

        usort($artifacts, static fn(array $a, array $b): int => strcmp($a['filename'], $b['filename']));
        return $artifacts;
    }

    /**
     * Validates one content completion manifest and its inventory.
     *
     * @param string $manifestpath manifest candidate
     * @param string|null $directoryprefix canonical directory prefix
     * @return array validated recovery-set artifact
     */
    public function validate_manifest(string $manifestpath, ?string $directoryprefix = null): array {
        $manifestpath = realpath($manifestpath);
        if ($manifestpath === false || is_link($manifestpath)) {
            throw new \RuntimeException('Content recovery manifest boundary verification failed.');
        }

        $directory = realpath(dirname($manifestpath));
        $directoryprefix ??= $directory === false
            ? ''
            : rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (
            $directory === false || $directoryprefix === '' ||
            !str_starts_with($manifestpath, $directoryprefix)
        ) {
            throw new \RuntimeException('Content recovery manifest boundary verification failed.');
        }

        $manifeststat = lstat($manifestpath);
        if (
            $manifeststat === false || !is_file($manifestpath) || !is_readable($manifestpath) ||
            (int)$manifeststat['nlink'] !== 1 || (int)$manifeststat['size'] < 1 ||
            (int)$manifeststat['size'] > self::MAX_MANIFEST_BYTES
        ) {
            throw new \RuntimeException('Content recovery manifest is invalid.');
        }

        $filename = basename($manifestpath);
        if (
            !preg_match(
                '/^moodle-content-(\d{8}T\d{6}Z)-([0-9a-f]{32})\.jsonl\.gz\.manifest\.json$/D',
                $filename,
                $matches
            )
        ) {
            throw new \RuntimeException('Content recovery manifest name is invalid.');
        }

        $json = file_get_contents($manifestpath);
        $afterread = lstat($manifestpath);
        if (
            $json === false || $afterread === false ||
            (int)$afterread['dev'] !== (int)$manifeststat['dev'] ||
            (int)$afterread['ino'] !== (int)$manifeststat['ino'] ||
            (int)$afterread['size'] !== (int)$manifeststat['size'] ||
            (int)$afterread['mtime'] !== (int)$manifeststat['mtime']
        ) {
            throw new \RuntimeException('Content recovery manifest changed during validation.');
        }

        $manifest = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || array_is_list($manifest)) {
            throw new \RuntimeException('Content recovery manifest root is invalid.');
        }
        $fields = array_keys($manifest);
        sort($fields);
        $expectedfields = self::MANIFEST_FIELDS;
        sort($expectedfields);
        if ($fields !== $expectedfields) {
            throw new \RuntimeException('Content recovery manifest fields are invalid.');
        }

        foreach (
            [
                'schema',
                'type',
                'createdat',
                'recoverysetid',
                'databaseartifactid',
                'inventory',
                'inventorysha256',
                'hashalgorithm',
                'compression',
            ] as $field
        ) {
            if (!is_string($manifest[$field])) {
                throw new \RuntimeException('Content recovery manifest field types are invalid.');
            }
        }
        if (
            !is_int($manifest['inventorybytes']) || $manifest['inventorybytes'] < 1 ||
            !is_int($manifest['objectcount']) || $manifest['objectcount'] < 0 ||
            !is_int($manifest['contentbytes']) || $manifest['contentbytes'] < 0
        ) {
            throw new \RuntimeException('Content recovery manifest counters are invalid.');
        }

        $created = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $manifest['createdat']);
        $compacttime = $created === false ? '' : $created->format('Ymd\THis\Z');
        $expectedrecoverysetid = $matches[1] . '-' . $matches[2];
        $expectedinventory = 'moodle-content-' . $expectedrecoverysetid . '.jsonl.gz';
        if (
            $manifest['schema'] !== 'tool_secure_s3_storage.content-recovery/v1' ||
            $manifest['type'] !== 'content' ||
            $manifest['hashalgorithm'] !== 'sha1' ||
            $manifest['compression'] !== 'gzip' ||
            $compacttime !== $matches[1] ||
            $manifest['databaseartifactid'] !== $matches[2] ||
            $manifest['recoverysetid'] !== $expectedrecoverysetid ||
            $manifest['inventory'] !== $expectedinventory ||
            !preg_match('/^[0-9a-f]{64}$/D', $manifest['inventorysha256'])
        ) {
            throw new \RuntimeException('Content recovery manifest values are invalid.');
        }

        $inventorypath = $directoryprefix . $manifest['inventory'];
        if (
            realpath($inventorypath) !== $inventorypath ||
            !str_starts_with($inventorypath, $directoryprefix) ||
            is_link($inventorypath) || !is_file($inventorypath) || !is_readable($inventorypath)
        ) {
            throw new \RuntimeException('Content inventory boundary verification failed.');
        }
        $inventorystat = lstat($inventorypath);
        $inventorysha256 = hash_file('sha256', $inventorypath);
        if (
            $inventorystat === false || (int)$inventorystat['nlink'] !== 1 ||
            (int)$inventorystat['size'] !== $manifest['inventorybytes'] ||
            $inventorysha256 === false ||
            !hash_equals($manifest['inventorysha256'], $inventorysha256)
        ) {
            throw new \RuntimeException('Content inventory verification failed.');
        }

        return [
            'path' => $manifestpath,
            'filename' => $filename,
            'size' => (int)$manifeststat['size'],
            'mtime' => (int)$manifeststat['mtime'],
            'stat' => $manifeststat,
            'sourcehash' => hash('sha256', 'content-recovery:v1:' . $manifest['recoverysetid']),
            'inventorypath' => $inventorypath,
            'inventorystat' => $inventorystat,
            'inventorysha256' => $manifest['inventorysha256'],
            'inventorybytes' => $manifest['inventorybytes'],
            'objectcount' => $manifest['objectcount'],
            'contentbytes' => $manifest['contentbytes'],
            'recoverysetid' => $manifest['recoverysetid'],
            'artifactid' => $manifest['databaseartifactid'],
            'compacttime' => $matches[1],
        ];
    }

    /**
     * Validates the complete inventory and returns the next bounded object batch.
     *
     * @param array $artifact validated recovery-set artifact
     * @param string $after exclusive content-hash cursor
     * @param int $limit maximum objects returned
     * @return array{objects: array[], hasmore: bool}
     */
    public function read_batch(array $artifact, string $after, int $limit): array {
        if ($after !== '' && !preg_match('/^[0-9a-f]{40}$/D', $after)) {
            throw new \RuntimeException('Invalid content inventory cursor.');
        }
        if ($limit < 1 || $limit > 1000) {
            throw new \RuntimeException('Invalid content inventory batch size.');
        }

        $handle = gzopen($artifact['inventorypath'], 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open the content inventory.');
        }

        $objects = [];
        $objectcount = 0;
        $contentbytes = 0;
        $previous = '';
        $hasmore = false;
        try {
            $headerline = gzgets($handle, 16384);
            if ($headerline === false || !str_ends_with($headerline, "\n")) {
                throw new \RuntimeException('Content inventory header is invalid.');
            }
            $header = json_decode(rtrim($headerline, "\r\n"), true, 4, JSON_THROW_ON_ERROR);
            if (
                !is_array($header) || array_is_list($header) ||
                array_keys($header) !== ['schema', 'recoverysetid'] ||
                $header['schema'] !== 'tool_secure_s3_storage.content-inventory/v1' ||
                $header['recoverysetid'] !== $artifact['recoverysetid']
            ) {
                throw new \RuntimeException('Content inventory header values are invalid.');
            }

            while (!gzeof($handle)) {
                $line = gzgets($handle, 16384);
                if ($line === false) {
                    if (gzeof($handle)) {
                        break;
                    }
                    throw new \RuntimeException('Unable to read the content inventory.');
                }
                if (!str_ends_with($line, "\n")) {
                    throw new \RuntimeException('Content inventory line is truncated.');
                }
                $entry = json_decode(rtrim($line, "\r\n"), true, 4, JSON_THROW_ON_ERROR);
                if (
                    !is_array($entry) || array_is_list($entry) ||
                    array_keys($entry) !== ['contenthash', 'filesize'] ||
                    !is_string($entry['contenthash']) ||
                    !preg_match('/^[0-9a-f]{40}$/D', $entry['contenthash']) ||
                    !is_int($entry['filesize']) || $entry['filesize'] < 1 ||
                    ($previous !== '' && strcmp($entry['contenthash'], $previous) <= 0)
                ) {
                    throw new \RuntimeException('Content inventory entry is invalid.');
                }

                $previous = $entry['contenthash'];
                $objectcount++;
                $contentbytes += $entry['filesize'];
                if (strcmp($entry['contenthash'], $after) > 0) {
                    if (count($objects) < $limit) {
                        $objects[] = $entry;
                    } else {
                        $hasmore = true;
                    }
                }
            }
        } finally {
            gzclose($handle);
        }

        if (
            $objectcount !== $artifact['objectcount'] ||
            $contentbytes !== $artifact['contentbytes']
        ) {
            throw new \RuntimeException('Content inventory totals do not match its manifest.');
        }

        return ['objects' => $objects, 'hasmore' => $hasmore];
    }
}
