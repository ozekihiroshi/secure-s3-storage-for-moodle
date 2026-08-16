<?php
// This file is part of Secure S3 Storage for Moodle.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace tool_secure_s3_storage\local;

use Aws\S3\S3Client;

/**
 * Streams archives to S3 and verifies them by reading the object back.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class s3_gateway {
    /** @var S3Client AWS SDK client */
    private S3Client $client;

    /**
     * Creates a client without passing credentials, preserving the SDK default chain.
     *
     * @param configuration $configuration validated configuration
     */
    public function __construct(private readonly configuration $configuration) {
        global $CFG;

        if (!class_exists(S3Client::class)) {
            $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            $runtimeautoload = $CFG->tool_secure_s3_storage_awssdkautoload ?? '';
            if (is_string($runtimeautoload) && $runtimeautoload !== '') {
                $autoload = $runtimeautoload;
            }
            if (!is_readable($autoload)) {
                throw new \runtime_exception('AWS SDK runtime is unavailable.');
            }
            require_once($autoload);
        }

        $options = [
            'version' => 'latest',
            'region' => $configuration->region,
            'retries' => 3,
            'http' => [
                'connect_timeout' => 10,
                'timeout' => 300,
            ],
        ];

        $endpoint = $CFG->tool_secure_s3_storage_s3endpoint ?? '';
        if ($endpoint !== '') {
            $options['endpoint'] = $this->validate_runtime_endpoint((string)$endpoint);
            $options['use_path_style_endpoint'] = !empty($CFG->tool_secure_s3_storage_pathstyle);
        }

        $this->client = new S3Client($options);
    }

    /**
     * Uploads a stream, then downloads and verifies its length, metadata, and SHA-256.
     *
     * @param resource $handle verified local archive stream
     * @param int $size expected archive size
     * @param string $checksum expected lowercase SHA-256
     * @param string $objectkey normalized plugin-owned object key
     */
    public function upload_and_verify($handle, int $size, string $checksum, string $objectkey): void {
        if (!is_resource($handle)) {
            throw new \runtime_exception('Invalid archive stream.');
        }

        $this->client->putObject([
            'Bucket' => $this->configuration->bucket,
            'Key' => $objectkey,
            'Body' => $handle,
            'ContentLength' => $size,
            'ContentType' => 'application/vnd.moodle.backup',
            'Metadata' => [
                'sha256' => $checksum,
                'format' => 'moodle-mbz',
            ],
        ]);

        try {
            $result = $this->client->getObject([
                'Bucket' => $this->configuration->bucket,
                'Key' => $objectkey,
            ]);

            if ((int)$result['ContentLength'] !== $size) {
                throw new \runtime_exception('Remote object size verification failed.');
            }

            $metadata = array_change_key_case((array)($result['Metadata'] ?? []), CASE_LOWER);
            if (!isset($metadata['sha256']) || !hash_equals($checksum, (string)$metadata['sha256'])) {
                throw new \runtime_exception('Remote object metadata verification failed.');
            }

            $remotehash = hash_init('sha256');
            $body = $result['Body'];
            while (!$body->eof()) {
                $chunk = $body->read(1024 * 1024);
                if ($chunk === '' && !$body->eof()) {
                    throw new \runtime_exception('Remote object read failed.');
                }
                hash_update($remotehash, $chunk);
            }

            if (!hash_equals($checksum, hash_final($remotehash))) {
                throw new \runtime_exception('Remote object checksum verification failed.');
            }
        } catch (\Throwable $exception) {
            try {
                $this->client->deleteObject([
                    'Bucket' => $this->configuration->bucket,
                    'Key' => $objectkey,
                ]);
            } catch (\Throwable) {
                // The local archive remains authoritative; cleanup can be retried safely.
            }
            throw $exception;
        }
    }

    /**
     * Allows a development endpoint only from runtime configuration.
     *
     * @param string $endpoint runtime endpoint
     * @return string normalized endpoint
     */
    private function validate_runtime_endpoint(string $endpoint): string {
        $parts = parse_url($endpoint);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \runtime_exception('Invalid runtime S3 endpoint.');
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new \runtime_exception('Invalid runtime S3 endpoint.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \runtime_exception('Invalid runtime S3 endpoint.');
        }

        return rtrim($endpoint, '/');
    }
}
