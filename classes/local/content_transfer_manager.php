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
 * Transfers one database-matched Moodle content recovery set to S3.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class content_transfer_manager {
    /** Internal content-hash cursor setting. */
    private const CURSOR_SETTING = 'contentcursor';

    /** Internal active recovery-set setting. */
    private const RECOVERY_SET_SETTING = 'contentrecoveryset';

    /** @var transfer_repository Transfer audit repository. */
    private transfer_repository $repository;

    /** @var content_recovery_scanner Recovery-set scanner. */
    private content_recovery_scanner $scanner;

    /**
     * Creates the content transfer coordinator.
     */
    public function __construct() {
        $this->repository = new transfer_repository();
        $this->scanner = new content_recovery_scanner();
    }

    /**
     * Processes one bounded batch from the oldest incomplete recovery set.
     *
     * @param configuration $configuration validated content-pool configuration
     * @param int $batchsize maximum content objects processed per run
     * @return array{found: int, transferred: int, failed: int, waiting: bool, recoverysetcomplete: bool}
     */
    public function execute(configuration $configuration, int $batchsize): array {
        $result = [
            'found' => 0,
            'transferred' => 0,
            'failed' => 0,
            'waiting' => false,
            'recoverysetcomplete' => false,
        ];
        $gateway = new s3_gateway($configuration);

        foreach ($this->scanner->scan() as $artifact) {
            if (!$this->repository->is_success($artifact['databasesourcehash'])) {
                mtrace(get_string(
                    'task_content_database_waiting',
                    'tool_secure_s3_storage',
                    $artifact['recoverysetid']
                ));
                $result['waiting'] = true;
                return $result;
            }
            $manifestrecordid = $this->repository->observe($artifact, 0);
            if ($manifestrecordid === false) {
                continue;
            }
            if ($manifestrecordid === null) {
                throw new \RuntimeException('Content recovery manifest entered an observation delay.');
            }

            $active = (string)get_config(
                'tool_secure_s3_storage',
                self::RECOVERY_SET_SETTING
            );
            $cursor = (string)get_config('tool_secure_s3_storage', self::CURSOR_SETTING);
            if (
                $active !== $artifact['recoverysetid'] ||
                ($cursor !== '' && !preg_match('/^[0-9a-f]{40}$/D', $cursor))
            ) {
                $cursor = '';
                set_config(
                    self::RECOVERY_SET_SETTING,
                    $artifact['recoverysetid'],
                    'tool_secure_s3_storage'
                );
                set_config(self::CURSOR_SETTING, '', 'tool_secure_s3_storage');
            }

            $batch = $this->scanner->read_batch($artifact, $cursor, $batchsize);
            $result['found'] = count($batch['objects']);
            foreach ($batch['objects'] as $entry) {
                $contenthash = $entry['contenthash'];
                unset($recordid);
                try {
                    $object = $this->inspect_object(
                        $configuration,
                        $contenthash,
                        $entry['filesize']
                    );
                    $objectkey = $configuration->prefix . 'content/v1/objects/' .
                        substr($contenthash, 0, 2) . '/' .
                        substr($contenthash, 2, 2) . '/' . $contenthash;
                    $existing = $this->repository->get_success($object['sourcehash']);
                    if ($existing !== null) {
                        $recordid = (int)$existing->id;
                        try {
                            if (
                                (int)$existing->filesize !== $object['size'] ||
                                !is_string($existing->checksum) ||
                                !is_string($existing->objectkey) ||
                                !hash_equals($objectkey, $existing->objectkey)
                            ) {
                                throw new \RuntimeException(
                                    'Completed content transfer audit is inconsistent.'
                                );
                            }
                            $gateway->verify_existing(
                                $object['size'],
                                $existing->checksum,
                                $objectkey,
                                'moodle-filedir-sha1'
                            );
                            set_config(
                                self::CURSOR_SETTING,
                                $contenthash,
                                'tool_secure_s3_storage'
                            );
                            continue;
                        } catch (\Throwable) {
                            $this->repository->mark_failure(
                                $recordid,
                                'RemoteVerificationException'
                            );
                        }
                    } else {
                        $recordid = $this->repository->observe($object, 0);
                        if ($recordid === false) {
                            throw new \RuntimeException(
                                'Completed content transfer audit could not be loaded.'
                            );
                        }
                        if ($recordid === null) {
                            throw new \RuntimeException(
                                'Content object unexpectedly entered an observation delay.'
                            );
                        }
                    }

                    [$handle, $sha256] = $this->open_verified_file(
                        $object['path'],
                        $object['stat'],
                        $contenthash
                    );
                    try {
                        $this->repository->mark_attempt($recordid, $sha256, $objectkey);
                        $gateway->upload_and_verify(
                            $handle,
                            $object['size'],
                            $sha256,
                            $objectkey,
                            'application/octet-stream',
                            'moodle-filedir-sha1'
                        );
                        $this->repository->mark_success($recordid);
                        $result['transferred']++;
                        mtrace(get_string(
                            'task_content_transferred',
                            'tool_secure_s3_storage',
                            $contenthash
                        ));
                    } finally {
                        fclose($handle);
                    }
                    set_config(self::CURSOR_SETTING, $contenthash, 'tool_secure_s3_storage');
                } catch (\Throwable $exception) {
                    if (isset($recordid) && is_int($recordid)) {
                        $category = (new \ReflectionClass($exception))->getShortName();
                        $this->repository->mark_failure($recordid, $category);
                    }
                    $result['failed']++;
                    mtrace(get_string(
                        'task_content_failed',
                        'tool_secure_s3_storage',
                        $contenthash
                    ));
                    return $result;
                }
            }

            if (!$batch['hasmore']) {
                try {
                    $this->publish_recovery_set(
                        $configuration,
                        $gateway,
                        $artifact,
                        $manifestrecordid
                    );
                    set_config(self::RECOVERY_SET_SETTING, '', 'tool_secure_s3_storage');
                    set_config(self::CURSOR_SETTING, '', 'tool_secure_s3_storage');
                    $result['recoverysetcomplete'] = true;
                } catch (\Throwable $exception) {
                    $category = (new \ReflectionClass($exception))->getShortName();
                    $this->repository->mark_failure($manifestrecordid, $category);
                    $result['failed']++;
                }
            }
            return $result;
        }

        return $result;
    }

    /**
     * Uploads the inventory and publishes its completion manifest last.
     *
     * @param configuration $configuration validated content-pool configuration
     * @param s3_gateway $gateway initialized S3 gateway
     * @param array $artifact validated content recovery artifact
     * @param int $recordid manifest transfer audit id
     */
    private function publish_recovery_set(
        configuration $configuration,
        s3_gateway $gateway,
        array $artifact,
        int $recordid,
    ): void {
        [$inventoryhandle, $inventoryhash] = $this->open_verified_file(
            $artifact['inventorypath'],
            $artifact['inventorystat'],
            null,
            $artifact['inventorysha256']
        );
        $manifesthandle = null;
        try {
            [$manifesthandle, $manifesthash] = $this->open_verified_file(
                $artifact['path'],
                $artifact['stat']
            );
            $date = $artifact['compacttime'];
            $basekey = $configuration->prefix . 'content/v1/recovery-sets/' .
                substr($date, 0, 4) . '/' . substr($date, 4, 2) . '/' .
                substr($date, 6, 2) . '/' . $artifact['artifactid'] . '/';
            $manifestkey = $basekey . 'manifest.json';
            $this->repository->mark_attempt($recordid, $manifesthash, $manifestkey);
            $gateway->upload_and_verify(
                $inventoryhandle,
                $artifact['inventorybytes'],
                $inventoryhash,
                $basekey . 'inventory.jsonl.gz',
                'application/gzip',
                'moodle-content-inventory-v1'
            );
            $gateway->upload_and_verify(
                $manifesthandle,
                $artifact['size'],
                $manifesthash,
                $manifestkey,
                'application/json',
                'moodle-content-recovery-v1'
            );
            $this->repository->mark_success($recordid);
            mtrace(get_string(
                'task_content_recovery_set_transferred',
                'tool_secure_s3_storage',
                $artifact['recoverysetid']
            ));
        } finally {
            fclose($inventoryhandle);
            if (is_resource($manifesthandle)) {
                fclose($manifesthandle);
            }
        }
    }

    /**
     * Resolves and validates one canonical file-pool object.
     *
     * @param configuration $configuration validated content-pool configuration
     * @param string $contenthash expected Moodle SHA-1 content hash
     * @param int $expectedsize expected size from the recovery inventory
     * @return array{path: string, sourcehash: string, filename: string, size: int, mtime: int, stat: array}
     */
    private function inspect_object(
        configuration $configuration,
        string $contenthash,
        int $expectedsize,
    ): array {
        if (!preg_match('/^[0-9a-f]{40}$/D', $contenthash) || $expectedsize < 1) {
            throw new \RuntimeException('Invalid Moodle content metadata.');
        }

        $relative = substr($contenthash, 0, 2) . DIRECTORY_SEPARATOR .
            substr($contenthash, 2, 2) . DIRECTORY_SEPARATOR . $contenthash;
        $expectedpath = $configuration->sourcedirectory . DIRECTORY_SEPARATOR . $relative;
        $path = realpath($expectedpath);
        $prefix = $configuration->sourcedirectory . DIRECTORY_SEPARATOR;
        if (
            $path === false || $path !== $expectedpath || !str_starts_with($path, $prefix) ||
            is_link($path) || !is_file($path) || !is_readable($path)
        ) {
            throw new \RuntimeException('Content object boundary verification failed.');
        }

        $stat = lstat($path);
        if (
            $stat === false || (int)$stat['nlink'] !== 1 ||
            (int)$stat['size'] !== $expectedsize
        ) {
            throw new \RuntimeException('Content object size verification failed.');
        }

        return [
            'path' => $path,
            'sourcehash' => hash('sha256', 'content:v1:' . $contenthash),
            'filename' => $contenthash,
            'size' => $expectedsize,
            'mtime' => (int)$stat['mtime'],
            'stat' => $stat,
        ];
    }

    /**
     * Opens one immutable file and verifies its identity and hashes.
     *
     * @param string $path canonical path
     * @param array $expectedstat expected file identity
     * @param string|null $expectedsha1 optional Moodle content hash
     * @param string|null $expectedsha256 optional manifest-declared transport hash
     * @return array{0: resource, 1: string} open stream and SHA-256 digest
     */
    private function open_verified_file(
        string $path,
        array $expectedstat,
        ?string $expectedsha1 = null,
        ?string $expectedsha256 = null,
    ): array {
        clearstatcache(true, $path);
        if (is_link($path) || realpath($path) !== $path) {
            throw new \RuntimeException('Content file boundary verification failed.');
        }

        $before = lstat($path);
        $handle = fopen($path, 'rb');
        if ($before === false || $handle === false) {
            throw new \RuntimeException('Unable to open content file.');
        }

        try {
            $opened = fstat($handle);
            if (
                $opened === false || (int)$before['nlink'] !== 1 ||
                (int)$opened['dev'] !== (int)$expectedstat['dev'] ||
                (int)$opened['ino'] !== (int)$expectedstat['ino'] ||
                (int)$opened['size'] !== (int)$expectedstat['size'] ||
                (int)$opened['mtime'] !== (int)$expectedstat['mtime']
            ) {
                throw new \RuntimeException('Content file changed before checksum.');
            }

            $sha1 = hash_init('sha1');
            $sha256 = hash_init('sha256');
            while (!feof($handle)) {
                $chunk = fread($handle, 1024 * 1024);
                if ($chunk === false) {
                    throw new \RuntimeException('Unable to read content file.');
                }
                if ($chunk !== '') {
                    hash_update($sha1, $chunk);
                    hash_update($sha256, $chunk);
                }
            }

            $after = fstat($handle);
            if (
                $after === false ||
                (int)$after['size'] !== (int)$expectedstat['size'] ||
                (int)$after['mtime'] !== (int)$expectedstat['mtime']
            ) {
                throw new \RuntimeException('Content file changed during checksum.');
            }

            $sha1value = hash_final($sha1);
            $sha256value = hash_final($sha256);
            if ($expectedsha1 !== null && !hash_equals($expectedsha1, $sha1value)) {
                throw new \RuntimeException('Content object does not match its Moodle content hash.');
            }
            if ($expectedsha256 !== null && !hash_equals($expectedsha256, $sha256value)) {
                throw new \RuntimeException('Content file does not match its manifest checksum.');
            }
            if (!rewind($handle)) {
                throw new \RuntimeException('Unable to rewind content file.');
            }

            return [$handle, $sha256value];
        } catch (\Throwable $exception) {
            fclose($handle);
            throw $exception;
        }
    }
}
