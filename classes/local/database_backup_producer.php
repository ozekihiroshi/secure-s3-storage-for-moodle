<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace tool_secure_s3_storage\local;

/**
 * Creates a private, completed Moodle database export for later S3 transfer.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class database_backup_producer {
    /** Built-in producer mode. */
    public const MODE_BUILTIN = 'builtin';

    /** External producer mode. */
    public const MODE_EXTERNAL = 'external';

    /** Artifact contract emitted by this producer. */
    private const SCHEMA = 'tool_secure_s3_storage.artifact/v2';

    /**
     * Returns the configured producer mode.
     *
     * @return string
     */
    public static function get_mode(): string {
        $mode = (string)get_config('tool_secure_s3_storage', 'databaseproducermode');
        return in_array($mode, [self::MODE_BUILTIN, self::MODE_EXTERNAL], true)
            ? $mode
            : self::MODE_BUILTIN;
    }

    /**
     * Creates and atomically publishes one built-in database artifact.
     *
     * @return string final payload filename
     */
    public function produce(): string {
        global $CFG, $DB;

        if (!in_array($CFG->dbtype, ['mariadb', 'mysqli'], true)) {
            throw new \RuntimeException('The built-in database producer currently supports MariaDB/MySQL only.');
        }

        $directory = $this->prepare_directory();

        require_once($CFG->libdir . '/dtllib.php');

        $created = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $compacttime = $created->format('Ymd\THis\Z');
        $artifactid = bin2hex(random_bytes(16));
        $payload = 'moodle-db-' . $compacttime . '-' . $artifactid . '.xml.gz';
        $payloadpath = $directory . DIRECTORY_SEPARATOR . $payload;
        $manifestpath = $payloadpath . '.manifest.json';
        $token = bin2hex(random_bytes(12));
        $xmltemp = $directory . DIRECTORY_SEPARATOR . '.' . $token . '.xml.part';
        $gziptemp = $directory . DIRECTORY_SEPARATOR . '.' . $token . '.xml.gz.part';
        $manifesttemp = $directory . DIRECTORY_SEPARATOR . '.' . $token . '.manifest.part';

        try {
            $DB->execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $DB->execute('SET TRANSACTION READ ONLY');
            $transaction = $DB->start_delegated_transaction();
            try {
                $exporter = new \file_xml_database_exporter($xmltemp, $DB);
                $exporter->export_database('Secure S3 Storage built-in database backup');
                $transaction->allow_commit();
            } catch (\Throwable $exception) {
                $transaction->rollback($exception);
            }

            $this->assert_private_regular_file($xmltemp);
            [$bytes, $sha256] = $this->compress_and_hash($xmltemp, $gziptemp);

            $manifest = [
                'schema' => self::SCHEMA,
                'artifactid' => $artifactid,
                'type' => 'database',
                'createdat' => $created->format('Y-m-d\TH:i:s\Z'),
                'payload' => $payload,
                'bytes' => $bytes,
                'sha256' => $sha256,
                'format' => 'moodle-dtl-xml',
                'compression' => 'gzip',
                'encryption' => 'none',
                'recoverysetid' => $compacttime . '-' . $artifactid,
                'moodleversion' => (int)$CFG->version,
                'moodlerelease' => (string)$CFG->release,
                'dbtype' => (string)$CFG->dbtype,
            ];

            $json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            if (file_put_contents($manifesttemp, $json, LOCK_EX) !== strlen($json)) {
                throw new \RuntimeException('Unable to write the database artifact manifest.');
            }
            chmod($manifesttemp, 0600);

            if (!rename($gziptemp, $payloadpath)) {
                throw new \RuntimeException('Unable to publish the database artifact payload.');
            }
            if (!rename($manifesttemp, $manifestpath)) {
                throw new \RuntimeException('Unable to publish the database artifact manifest.');
            }

            return $payload;
        } finally {
            foreach ([$xmltemp, $gziptemp, $manifesttemp] as $temporary) {
                if (is_file($temporary) && !is_link($temporary)) {
                    unlink($temporary);
                }
            }
        }
    }

    /**
     * Returns the fixed private directory used by the built-in producer.
     *
     * @return string
     */
    public static function get_builtin_directory(): string {
        global $CFG;
        return rtrim($CFG->dataroot, DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR . 'tool_secure_s3_storage' . DIRECTORY_SEPARATOR . 'database';
    }

    /**
     * Creates and verifies the private artifact directory.
     *
     * @return string canonical directory
     */
    public function prepare_directory(): string {
        global $CFG;

        $directory = self::get_builtin_directory();
        $dataroot = realpath($CFG->dataroot);
        if ($dataroot === false || is_link($CFG->dataroot)) {
            throw new \RuntimeException('Moodle data directory is invalid.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create the private database backup directory.');
        }
        chmod(dirname($directory), 0700);
        chmod($directory, 0700);

        $canonical = realpath($directory);
        $boundary = rtrim($dataroot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (
            $canonical === false ||
            is_link($directory) ||
            !str_starts_with($canonical . DIRECTORY_SEPARATOR, $boundary) ||
            !is_writable($canonical)
        ) {
            throw new \RuntimeException('Private database backup directory boundary verification failed.');
        }

        return $canonical;
    }

    /**
     * Compresses one XML export while calculating the final SHA-256.
     *
     * @param string $source XML source
     * @param string $destination temporary gzip destination
     * @return array{0: int, 1: string}
     */
    private function compress_and_hash(string $source, string $destination): array {
        $input = fopen($source, 'rb');
        $output = gzopen($destination, 'wb6');
        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            throw new \RuntimeException('Unable to open database backup compression streams.');
        }

        try {
            while (!feof($input)) {
                $chunk = fread($input, 1024 * 1024);
                if ($chunk === false || ($chunk !== '' && gzwrite($output, $chunk) !== strlen($chunk))) {
                    throw new \RuntimeException('Unable to compress the database backup.');
                }
            }
        } finally {
            fclose($input);
            gzclose($output);
        }

        chmod($destination, 0600);
        $this->assert_private_regular_file($destination);
        $bytes = filesize($destination);
        $sha256 = hash_file('sha256', $destination);
        if ($bytes === false || $bytes < 1 || $sha256 === false) {
            throw new \RuntimeException('Unable to verify the compressed database backup.');
        }
        return [(int)$bytes, $sha256];
    }

    /**
     * Rejects links and non-private intermediate files.
     *
     * @param string $path file path
     */
    private function assert_private_regular_file(string $path): void {
        $stat = lstat($path);
        if ($stat === false || is_link($path) || !is_file($path) || (int)$stat['nlink'] !== 1) {
            throw new \RuntimeException('Database backup intermediate file is invalid.');
        }
        if (((int)$stat['mode'] & 0077) !== 0) {
            chmod($path, 0600);
            clearstatcache(true, $path);
            $stat = lstat($path);
            if ($stat === false || ((int)$stat['mode'] & 0077) !== 0) {
                throw new \RuntimeException('Database backup intermediate file permissions are unsafe.');
            }
        }
    }
}
