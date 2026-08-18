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
 * Finds and validates completed database artifact manifests.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class database_artifact_scanner {
    /** Maximum accepted manifest size. */
    private const MAX_MANIFEST_BYTES = 16384;

    /** Required manifest fields. */
    private const MANIFEST_FIELDS = [
        'artifactid',
        'bytes',
        'compression',
        'createdat',
        'encryption',
        'format',
        'payload',
        'recoverysetid',
        'schema',
        'sha256',
        'type',
    ];

    /**
     * Returns valid completed artifacts directly below the configured directory.
     *
     * @param configuration $configuration validated database artifact configuration
     * @return array<int, array<string, mixed>>
     */
    public function scan(configuration $configuration): array {
        $artifacts = [];
        $directory = $configuration->sourcedirectory;
        $prefix = $directory . DIRECTORY_SEPARATOR;
        $iterator = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $entry) {
            if (
                $entry->isLink() ||
                !$entry->isFile() ||
                !str_ends_with($entry->getFilename(), '.sql.gz.manifest.json')
            ) {
                continue;
            }

            try {
                $artifact = $this->read_manifest($entry->getPathname(), $prefix);
                $artifacts[] = $artifact;
            } catch (\Throwable $exception) {
                mtrace(get_string('task_database_manifest_rejected', 'tool_secure_s3_storage', $entry->getFilename()));
            }
        }

        usort($artifacts, static fn(array $a, array $b): int => strcmp($a['filename'], $b['filename']));
        return $artifacts;
    }

    /**
     * Reads one bounded manifest and validates its payload boundary and metadata.
     *
     * @param string $manifestpath candidate manifest path
     * @param string $directoryprefix canonical directory plus separator
     * @return array<string, mixed>
     */
    private function read_manifest(string $manifestpath, string $directoryprefix): array {
        $manifestpath = realpath($manifestpath);
        if ($manifestpath === false || !str_starts_with($manifestpath, $directoryprefix) || is_link($manifestpath)) {
            throw new \RuntimeException('Manifest boundary verification failed.');
        }

        $manifeststat = lstat($manifestpath);
        if (
            $manifeststat === false ||
            !is_file($manifestpath) ||
            !is_readable($manifestpath) ||
            (int)$manifeststat['nlink'] !== 1 ||
            (int)$manifeststat['size'] < 1 ||
            (int)$manifeststat['size'] > self::MAX_MANIFEST_BYTES
        ) {
            throw new \RuntimeException('Manifest file is invalid.');
        }

        $filename = basename($manifestpath);
        if (
            !preg_match(
                '/^moodle-db-(\d{8}T\d{6}Z)-([0-9a-f]{16})\.sql\.gz\.manifest\.json$/D',
                $filename,
                $namematches
            )
        ) {
            throw new \RuntimeException('Manifest name is invalid.');
        }

        $json = file_get_contents($manifestpath);
        $afterread = lstat($manifestpath);
        if (
            $json === false ||
            $afterread === false ||
            (int)$afterread['dev'] !== (int)$manifeststat['dev'] ||
            (int)$afterread['ino'] !== (int)$manifeststat['ino'] ||
            (int)$afterread['size'] !== (int)$manifeststat['size'] ||
            (int)$afterread['mtime'] !== (int)$manifeststat['mtime']
        ) {
            throw new \RuntimeException('Manifest changed while being read.');
        }

        $manifest = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || array_is_list($manifest)) {
            throw new \RuntimeException('Manifest must be a JSON object.');
        }

        $fields = array_keys($manifest);
        sort($fields);
        $expectedfields = self::MANIFEST_FIELDS;
        sort($expectedfields);
        if ($fields !== $expectedfields) {
            throw new \RuntimeException('Manifest fields are invalid.');
        }

        $stringfields = [
            'schema', 'artifactid', 'type', 'createdat', 'payload', 'sha256',
            'format', 'compression', 'encryption', 'recoverysetid',
        ];
        foreach ($stringfields as $field) {
            if (!is_string($manifest[$field])) {
                throw new \RuntimeException('Manifest field type is invalid.');
            }
        }
        if (!is_int($manifest['bytes']) || $manifest['bytes'] < 1) {
            throw new \RuntimeException('Manifest byte count is invalid.');
        }

        if (
            $manifest['schema'] !== 'tool_secure_s3_storage.artifact/v1' ||
            $manifest['type'] !== 'database' ||
            $manifest['format'] !== 'mariadb-sql' ||
            $manifest['compression'] !== 'gzip' ||
            $manifest['encryption'] !== 'none' ||
            !preg_match('/^[0-9a-f]{32}$/D', $manifest['artifactid']) ||
            !preg_match('/^[0-9a-f]{64}$/D', $manifest['sha256'])
        ) {
            throw new \RuntimeException('Manifest values are invalid.');
        }

        $created = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $manifest['createdat']);
        if ($created === false || $created->format('Y-m-d\TH:i:s\Z') !== $manifest['createdat']) {
            throw new \RuntimeException('Manifest creation time is invalid.');
        }
        $compacttime = $created->format('Ymd\THis\Z');
        $expectedpayload = 'moodle-db-' . $namematches[1] . '-' . $namematches[2] . '.sql.gz';
        if (
            $namematches[1] !== $compacttime ||
            $manifest['payload'] !== $expectedpayload ||
            $manifest['recoverysetid'] !== $compacttime . '-' . $namematches[2] ||
            $filename !== $manifest['payload'] . '.manifest.json'
        ) {
            throw new \RuntimeException('Manifest name relationships are invalid.');
        }

        $payloadpath = realpath(dirname($manifestpath) . DIRECTORY_SEPARATOR . $manifest['payload']);
        if (
            $payloadpath === false ||
            !str_starts_with($payloadpath, $directoryprefix) ||
            is_link($payloadpath) ||
            !is_file($payloadpath) ||
            !is_readable($payloadpath)
        ) {
            throw new \RuntimeException('Payload boundary verification failed.');
        }

        $payloadstat = lstat($payloadpath);
        if (
            $payloadstat === false ||
            (int)$payloadstat['nlink'] !== 1 ||
            (int)$payloadstat['size'] !== $manifest['bytes']
        ) {
            throw new \RuntimeException('Payload metadata is invalid.');
        }

        return [
            'path' => $manifestpath,
            'sourcehash' => hash('sha256', $manifestpath),
            'filename' => $filename,
            'size' => (int)$manifeststat['size'],
            'mtime' => (int)$manifeststat['mtime'],
            'manifeststat' => $manifeststat,
            'payloadpath' => $payloadpath,
            'payloadstat' => $payloadstat,
            'payload' => $manifest['payload'],
            'payloadbytes' => $manifest['bytes'],
            'payloadsha256' => $manifest['sha256'],
            'artifactid' => $manifest['artifactid'],
            'compacttime' => $compacttime,
        ];
    }
}
