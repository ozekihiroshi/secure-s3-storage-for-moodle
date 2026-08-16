<?php
// This file is part of Secure S3 Storage for Moodle.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace tool_secure_s3_storage\local;

/**
 * Coordinates stable-file observations and verified S3 transfers.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class transfer_manager {
    /** @var archive_scanner archive scanner */
    private archive_scanner $scanner;

    /** @var transfer_repository observation repository */
    private transfer_repository $repository;

    /**
     * Creates the transfer coordinator.
     */
    public function __construct() {
        $this->scanner = new archive_scanner();
        $this->repository = new transfer_repository();
    }

    /**
     * Executes one scan.
     *
     * @param configuration $configuration validated configuration
     * @return array{found: int, observed: int, transferred: int, failed: int}
     */
    public function execute(configuration $configuration): array {
        $result = ['found' => 0, 'observed' => 0, 'transferred' => 0, 'failed' => 0];
        $archives = $this->scanner->scan($configuration);
        $result['found'] = count($archives);
        $gateway = new s3_gateway($configuration);

        foreach ($archives as $archive) {
            $recordid = $this->repository->observe($archive, $configuration->stabilityseconds);
            if ($recordid === null) {
                $result['observed']++;
                continue;
            }
            if ($recordid === false) {
                continue;
            }

            $handle = null;
            try {
                [$handle, $checksum] = $this->open_stable_archive($archive);
                $objectkey = $configuration->prefix . 'v1/' . substr($checksum, 0, 2) . '/' . $checksum . '.mbz';
                $this->repository->mark_attempt($recordid, $checksum, $objectkey);
                $gateway->upload_and_verify($handle, $archive['size'], $checksum, $objectkey);
                $this->repository->mark_success($recordid);
                $result['transferred']++;
                mtrace(get_string('task_file_transferred', 'tool_secure_s3_storage', $archive['filename']));
            } catch (\Throwable $exception) {
                $this->repository->mark_failure($recordid, (new \ReflectionClass($exception))->getShortName());
                $result['failed']++;
                mtrace(get_string('task_file_failed', 'tool_secure_s3_storage', $archive['filename']));
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }

        return $result;
    }

    /**
     * Opens one verified file descriptor and streams it through SHA-256.
     *
     * @param array{path: string, sourcehash: string, filename: string, size: int, mtime: int} $archive archive data
     * @return array{0: resource, 1: string} open stream and lowercase SHA-256
     */
    private function open_stable_archive(array $archive): array {
        clearstatcache(true, $archive['path']);
        if (is_link($archive['path']) || realpath($archive['path']) !== $archive['path']) {
            throw new \runtime_exception('Archive boundary verification failed.');
        }

        $before = lstat($archive['path']);
        $handle = fopen($archive['path'], 'rb');
        if ($before === false || $handle === false) {
            throw new \runtime_exception('Unable to open archive.');
        }

        try {
            $opened = fstat($handle);
            if (
                $opened === false ||
                (int)$opened['dev'] !== (int)$before['dev'] ||
                (int)$opened['ino'] !== (int)$before['ino'] ||
                (int)$opened['size'] !== $archive['size'] ||
                (int)$opened['mtime'] !== $archive['mtime']
            ) {
                throw new \runtime_exception('Archive changed before checksum.');
            }

            $hash = hash_init('sha256');
            while (!feof($handle)) {
                $chunk = fread($handle, 1024 * 1024);
                if ($chunk === false) {
                    throw new \runtime_exception('Unable to read archive.');
                }
                if ($chunk !== '') {
                    hash_update($hash, $chunk);
                }
            }

            $after = fstat($handle);
            if (
                $after === false ||
                (int)$after['size'] !== $archive['size'] ||
                (int)$after['mtime'] !== $archive['mtime']
            ) {
                throw new \runtime_exception('Archive changed during checksum.');
            }
            if (!rewind($handle)) {
                throw new \runtime_exception('Unable to rewind archive.');
            }

            return [$handle, hash_final($hash)];
        } catch (\Throwable $exception) {
            fclose($handle);
            throw $exception;
        }
    }
}
