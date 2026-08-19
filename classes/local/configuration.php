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
 * Validated transfer configuration.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class configuration {
    /** @var string normalized AWS region */
    public readonly string $region;

    /** @var string normalized S3 bucket */
    public readonly string $bucket;

    /** @var string normalized dedicated prefix with trailing slash */
    public readonly string $prefix;

    /** @var string canonical source directory */
    public readonly string $sourcedirectory;

    /** @var int required stable observation period */
    public readonly int $stabilityseconds;

    /**
     * Creates validated transfer configuration.
     *
     * @param string $region normalized AWS region
     * @param string $bucket normalized S3 bucket
     * @param string $prefix normalized dedicated prefix with trailing slash
     * @param string $sourcedirectory canonical source directory
     * @param int $stabilityseconds required stable observation period
     */
    private function __construct(
        string $region,
        string $bucket,
        string $prefix,
        string $sourcedirectory,
        int $stabilityseconds,
    ) {
        $this->region = $region;
        $this->bucket = $bucket;
        $this->prefix = $prefix;
        $this->sourcedirectory = $sourcedirectory;
        $this->stabilityseconds = $stabilityseconds;
    }

    /**
     * Loads and validates Moodle plugin settings.
     *
     * @return self
     */
    public static function from_plugin_config(): self {
        return self::from_source_setting('sourcedirectory');
    }

    /**
     * Loads configuration for completed database artifacts.
     *
     * @return self
     */
    public static function from_database_plugin_config(): self {
        if (database_backup_producer::get_mode() === database_backup_producer::MODE_BUILTIN) {
            return self::from_source_directory(database_backup_producer::get_builtin_directory());
        }
        return self::from_source_setting('databaseartifactdirectory');
    }

    /**
     * Loads configuration for the existing Moodle content file pool.
     *
     * @return self
     */
    public static function from_content_pool(): self {
        global $CFG;

        return self::from_source_directory($CFG->dataroot . DIRECTORY_SEPARATOR . 'filedir');
    }

    /**
     * Loads shared S3 settings with one independently configured source directory.
     *
     * @param string $sourcesetting Moodle setting containing the source directory
     * @return self
     */
    private static function from_source_setting(string $sourcesetting): self {
        $config = get_config('tool_secure_s3_storage');

        return self::from_source_directory((string)($config->{$sourcesetting} ?? ''), $config);
    }

    /**
     * Loads shared S3 settings with an explicit source directory.
     *
     * @param string $source source directory
     * @param object|null $config previously loaded plugin configuration
     * @return self
     */
    private static function from_source_directory(string $source, ?object $config = null): self {
        $config ??= get_config('tool_secure_s3_storage');

        $region = strtolower(trim((string)($config->region ?? '')));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $region)) {
            throw new \invalid_parameter_exception('Invalid S3 region configuration.');
        }

        $bucket = strtolower(trim((string)($config->bucket ?? '')));
        if (!self::is_valid_bucket($bucket)) {
            throw new \invalid_parameter_exception('Invalid S3 bucket configuration.');
        }

        $prefix = trim((string)($config->prefix ?? ''));
        if (!self::is_valid_prefix($prefix)) {
            throw new \invalid_parameter_exception('Invalid S3 prefix configuration.');
        }
        $prefix = rtrim($prefix, '/') . '/';

        $source = trim($source);
        if ($source === '' || !str_starts_with($source, '/') || is_link($source)) {
            throw new \invalid_parameter_exception('Invalid backup source configuration.');
        }

        $canonicalsource = realpath($source);
        if ($canonicalsource === false || !is_dir($canonicalsource) || !is_readable($canonicalsource)) {
            throw new \invalid_parameter_exception('Invalid backup source configuration.');
        }

        $stabilityseconds = (int)($config->stabilityseconds ?? 60);
        if ($stabilityseconds < 1 || $stabilityseconds > DAYSECS) {
            throw new \invalid_parameter_exception('Invalid stability period configuration.');
        }

        return new self(
            $region,
            $bucket,
            $prefix,
            rtrim($canonicalsource, DIRECTORY_SEPARATOR),
            $stabilityseconds,
        );
    }

    /**
     * Validates an S3 bucket name without accepting IP address notation.
     *
     * @param string $bucket bucket name
     * @return bool
     */
    private static function is_valid_bucket(string $bucket): bool {
        if (strlen($bucket) < 3 || strlen($bucket) > 63) {
            return false;
        }
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $bucket)) {
            return false;
        }
        if (str_contains($bucket, '..') || str_contains($bucket, '.-') || str_contains($bucket, '-.')) {
            return false;
        }

        return filter_var($bucket, FILTER_VALIDATE_IP) === false;
    }

    /**
     * Validates the plugin-owned object prefix.
     *
     * @param string $prefix object prefix
     * @return bool
     */
    private static function is_valid_prefix(string $prefix): bool {
        if ($prefix === '' || strlen($prefix) > 180 || str_starts_with($prefix, '/')) {
            return false;
        }
        if (preg_match('/[\\x00-\\x1f\\x7f\\\\]/', $prefix) || str_contains($prefix, '//')) {
            return false;
        }

        foreach (explode('/', rtrim($prefix, '/')) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
