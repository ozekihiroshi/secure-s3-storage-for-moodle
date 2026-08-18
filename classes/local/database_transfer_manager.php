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
 * Transfers validated database artifacts without receiving database credentials.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class database_transfer_manager {
    /** @var database_artifact_scanner Database artifact scanner. */
    private database_artifact_scanner $scanner;

    /** @var database_artifact_v2_scanner Built-in artifact scanner. */
    private database_artifact_v2_scanner $v2scanner;

    /** @var transfer_repository Transfer audit repository. */
    private transfer_repository $repository;

    /**
     * Creates the database transfer coordinator.
     */
    public function __construct() {
        $this->scanner = new database_artifact_scanner();
        $this->v2scanner = new database_artifact_v2_scanner();
        $this->repository = new transfer_repository();
    }

    /**
     * Executes one database artifact scan.
     *
     * @param configuration $configuration validated database artifact configuration
     * @return array{found: int, observed: int, transferred: int, failed: int}
     */
    public function execute(configuration $configuration): array {
        $result = ['found' => 0, 'observed' => 0, 'transferred' => 0, 'failed' => 0];
        $artifacts = $this->scanner->scan($configuration);
        $artifacts = array_merge($artifacts, $this->v2scanner->scan($configuration));
        usort($artifacts, static fn(array $a, array $b): int => strcmp($a['filename'], $b['filename']));
        $result['found'] = count($artifacts);
        $gateway = new s3_gateway($configuration);

        foreach ($artifacts as $artifact) {
            $recordid = $this->repository->observe($artifact, 0);
            if ($recordid === null) {
                $result['observed']++;
                continue;
            }
            if ($recordid === false) {
                continue;
            }

            $payloadhandle = null;
            $manifesthandle = null;
            try {
                [$payloadhandle, $payloadhash] = $this->open_verified_file(
                    $artifact['payloadpath'],
                    $artifact['payloadstat'],
                    $artifact['payloadsha256']
                );
                [$manifesthandle, $manifesthash] = $this->open_verified_file(
                    $artifact['path'],
                    $artifact['manifeststat']
                );

                $date = $artifact['compacttime'];
                $basekey = $configuration->prefix . 'database/v' . $artifact['contractversion'] . '/' .
                    substr($date, 0, 4) . '/' . substr($date, 4, 2) . '/' . substr($date, 6, 2) . '/' .
                    $artifact['artifactid'] . '/';
                $payloadkey = $basekey . $artifact['payload'];
                $manifestkey = $basekey . 'manifest.json';

                $this->repository->mark_attempt($recordid, $payloadhash, $manifestkey);
                $gateway->upload_and_verify(
                    $payloadhandle,
                    $artifact['payloadbytes'],
                    $payloadhash,
                    $payloadkey,
                    $artifact['contenttype'],
                    $artifact['formatmetadata']
                );
                $gateway->upload_and_verify(
                    $manifesthandle,
                    $artifact['size'],
                    $manifesthash,
                    $manifestkey,
                    'application/json',
                    'secure-s3-artifact-manifest-v' . $artifact['contractversion']
                );
                $this->repository->mark_success($recordid);
                $result['transferred']++;
                mtrace(get_string('task_database_transferred', 'tool_secure_s3_storage', $artifact['payload']));
            } catch (\Throwable $exception) {
                $errorcategory = (new \ReflectionClass($exception))->getShortName();
                $this->repository->mark_failure($recordid, $errorcategory);
                $result['failed']++;
                mtrace(get_string('task_database_failed', 'tool_secure_s3_storage', $artifact['payload']));
            } finally {
                if (is_resource($payloadhandle)) {
                    fclose($payloadhandle);
                }
                if (is_resource($manifesthandle)) {
                    fclose($manifesthandle);
                }
            }
        }

        return $result;
    }

    /**
     * Opens a file by identity and streams it through SHA-256.
     *
     * @param string $path canonical file path
     * @param array $expectedstat expected lstat result
     * @param string|null $expectedhash optional manifest-declared SHA-256
     * @return array{0: resource, 1: string}
     */
    private function open_verified_file(string $path, array $expectedstat, ?string $expectedhash = null): array {
        clearstatcache(true, $path);
        if (is_link($path) || realpath($path) !== $path) {
            throw new \RuntimeException('Database artifact boundary verification failed.');
        }

        $before = lstat($path);
        $handle = fopen($path, 'rb');
        if ($before === false || $handle === false) {
            throw new \RuntimeException('Unable to open database artifact.');
        }

        try {
            $opened = fstat($handle);
            if (
                $opened === false ||
                (int)$before['nlink'] !== 1 ||
                (int)$opened['dev'] !== (int)$expectedstat['dev'] ||
                (int)$opened['ino'] !== (int)$expectedstat['ino'] ||
                (int)$opened['size'] !== (int)$expectedstat['size'] ||
                (int)$opened['mtime'] !== (int)$expectedstat['mtime']
            ) {
                throw new \RuntimeException('Database artifact changed before checksum.');
            }

            $hash = hash_init('sha256');
            while (!feof($handle)) {
                $chunk = fread($handle, 1024 * 1024);
                if ($chunk === false) {
                    throw new \RuntimeException('Unable to read database artifact.');
                }
                if ($chunk !== '') {
                    hash_update($hash, $chunk);
                }
            }

            $after = fstat($handle);
            if (
                $after === false ||
                (int)$after['size'] !== (int)$expectedstat['size'] ||
                (int)$after['mtime'] !== (int)$expectedstat['mtime']
            ) {
                throw new \RuntimeException('Database artifact changed during checksum.');
            }

            $checksum = hash_final($hash);
            if ($expectedhash !== null && !hash_equals($expectedhash, $checksum)) {
                throw new \RuntimeException('Database artifact checksum does not match its manifest.');
            }
            if (!rewind($handle)) {
                throw new \RuntimeException('Unable to rewind database artifact.');
            }

            return [$handle, $checksum];
        } catch (\Throwable $exception) {
            fclose($handle);
            throw $exception;
        }
    }
}
