<?php
// This file is part of Secure S3 Storage for Moodle.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Verifies that a release tag matches version.php metadata.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$expected = $argv[1] ?? '';
if (!preg_match('/^\d+\.\d+\.\d+$/D', $expected)) {
    fwrite(STDERR, "Expected a semantic release version.\n");
    exit(1);
}

$contents = file_get_contents(dirname(__DIR__) . '/version.php');
if ($contents === false) {
    fwrite(STDERR, "Unable to read version.php.\n");
    exit(1);
}

$releasepattern = <<<'REGEX'
/\$plugin->release\s*=\s*'([^']+)'/
REGEX;
if (!preg_match($releasepattern, $contents, $matches)) {
    fwrite(STDERR, "Unable to read the plugin release metadata.\n");
    exit(1);
}

if (!hash_equals($expected, $matches[1])) {
    fwrite(STDERR, "Release tag does not match version.php.\n");
    exit(1);
}

if (!preg_match('/\$plugin->version\s*=\s*(\d{10});/', $contents)) {
    fwrite(STDERR, "Plugin version must be a ten-digit Moodle build number.\n");
    exit(1);
}

echo "Verified release metadata for {$expected}.\n";
