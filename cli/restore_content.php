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

/**
 * Restores one database-matched content inventory into an empty isolated filedir.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'manifest' => null,
        'database-manifest' => null,
        'objects' => null,
        'target' => null,
        'help' => false,
    ],
    [
        'h' => 'help',
    ]
);

$help = <<<HELP
Restore one verified Moodle content recovery set into an empty isolated filedir.

Options:
  --manifest=PATH   Local content .jsonl.gz.manifest.json
  --database-manifest=PATH  Matching local v2 database manifest
  --objects=PATH    Local root containing aa/bb/<contenthash> objects
  --target=PATH     Empty isolated destination filedir
  -h, --help        Show this help

This command refuses the live Moodle data directory and never overwrites files.
It does not modify the database or download from S3.
HELP;

if ($options['help']) {
    echo $help . PHP_EOL;
    exit(0);
}
if ($unrecognized) {
    cli_error('Unknown options: ' . implode(', ', $unrecognized));
}
foreach (['manifest', 'database-manifest', 'objects', 'target'] as $required) {
    if (!is_string($options[$required]) || $options[$required] === '') {
        cli_error('Missing --' . $required . '. Use --help for usage.');
    }
}

try {
    $scanner = new \tool_secure_s3_storage\local\content_recovery_scanner();
    $artifact = $scanner->validate_manifest($options['manifest']);
    $databaseartifact = (new \tool_secure_s3_storage\local\database_artifact_v2_scanner())
        ->validate_manifest($options['database-manifest']);
    if (!hash_equals($databaseartifact['recoverysetid'], $artifact['recoverysetid'])) {
        throw new \RuntimeException('Database and content recovery-set identifiers do not match.');
    }

    $objectsroot = realpath($options['objects']);
    if ($objectsroot === false || is_link($options['objects']) || !is_dir($objectsroot)) {
        throw new \RuntimeException('Content object source directory is invalid.');
    }
    $objectsroot = rtrim($objectsroot, DIRECTORY_SEPARATOR);

    $target = $options['target'];
    if (file_exists($target) || is_link($target)) {
        $target = realpath($target);
        if ($target === false || is_link($options['target']) || !is_dir($target)) {
            throw new \RuntimeException('Content restore target is invalid.');
        }
    } else {
        $parent = realpath(dirname($target));
        if ($parent === false || is_link(dirname($target)) || !is_dir($parent)) {
            throw new \RuntimeException('Content restore target parent is invalid.');
        }
        $target = $parent . DIRECTORY_SEPARATOR . basename($target);
        if (!mkdir($target, 0700)) {
            throw new \RuntimeException('Unable to create the content restore target.');
        }
        $target = realpath($target);
        if ($target === false) {
            throw new \RuntimeException('Unable to resolve the content restore target.');
        }
    }

    $dataroot = realpath($CFG->dataroot);
    if (
        $dataroot === false ||
        $target === $dataroot ||
        str_starts_with($target . DIRECTORY_SEPARATOR, rtrim($dataroot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
    ) {
        throw new \RuntimeException('Refusing to restore inside the live Moodle data directory.');
    }
    if (!is_readable($target) || !is_writable($target)) {
        throw new \RuntimeException('Content restore target is not readable and writable.');
    }
    $directoryentries = scandir($target);
    if ($directoryentries === false) {
        throw new \RuntimeException('Unable to inspect the content restore target.');
    }
    $entries = array_values(array_diff($directoryentries, ['.', '..']));
    if ($entries !== []) {
        throw new \RuntimeException('Content restore target must be empty.');
    }

    $restored = 0;
    $restoredbytes = 0;
    $cursor = '';
    do {
        $batch = $scanner->read_batch($artifact, $cursor, 1000);
        foreach ($batch['objects'] as $entry) {
            $contenthash = $entry['contenthash'];
            $relative = substr($contenthash, 0, 2) . DIRECTORY_SEPARATOR .
                substr($contenthash, 2, 2) . DIRECTORY_SEPARATOR . $contenthash;
            $source = $objectsroot . DIRECTORY_SEPARATOR . $relative;
            if (
                realpath($source) !== $source ||
                !str_starts_with($source, $objectsroot . DIRECTORY_SEPARATOR) ||
                is_link($source) || !is_file($source) || !is_readable($source)
            ) {
                throw new \RuntimeException('A required content object is missing or outside its boundary.');
            }

            $sourcestat = lstat($source);
            $sha1 = hash_file('sha1', $source);
            if (
                $sourcestat === false || (int)$sourcestat['nlink'] !== 1 ||
                (int)$sourcestat['size'] !== $entry['filesize'] ||
                $sha1 === false || !hash_equals($contenthash, $sha1)
            ) {
                throw new \RuntimeException('A required content object failed integrity verification.');
            }

            $directory = $target . DIRECTORY_SEPARATOR . substr($contenthash, 0, 2) .
                DIRECTORY_SEPARATOR . substr($contenthash, 2, 2);
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create a restored content directory.');
            }
            $canonicaldirectory = realpath($directory);
            if (
                $canonicaldirectory === false || is_link($directory) ||
                !str_starts_with(
                    $canonicaldirectory . DIRECTORY_SEPARATOR,
                    $target . DIRECTORY_SEPARATOR
                )
            ) {
                throw new \RuntimeException('Restored content directory boundary verification failed.');
            }

            $destination = $canonicaldirectory . DIRECTORY_SEPARATOR . $contenthash;
            $temporary = $canonicaldirectory . DIRECTORY_SEPARATOR . '.' . $contenthash . '.part';
            if (file_exists($destination) || is_link($destination) || file_exists($temporary)) {
                throw new \RuntimeException('Refusing to overwrite a restored content object.');
            }
            if (!copy($source, $temporary)) {
                throw new \RuntimeException('Unable to copy a restored content object.');
            }
            chmod($temporary, 0600);
            $copiedsha1 = hash_file('sha1', $temporary);
            $copiedsize = filesize($temporary);
            if (
                $copiedsha1 === false || !hash_equals($contenthash, $copiedsha1) ||
                $copiedsize === false || (int)$copiedsize !== $entry['filesize']
            ) {
                unlink($temporary);
                throw new \RuntimeException('Restored content object verification failed.');
            }
            if (!rename($temporary, $destination)) {
                unlink($temporary);
                throw new \RuntimeException('Unable to publish a restored content object.');
            }

            $restored++;
            $restoredbytes += $entry['filesize'];
            $cursor = $contenthash;
        }
    } while ($batch['hasmore']);

    echo json_encode([
        'contentRestoreVerified' => true,
        'recoverysetid' => $artifact['recoverysetid'],
        'objects' => $restored,
        'bytes' => $restoredbytes,
        'target' => $target,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (\Throwable $exception) {
    cli_error('Content recovery validation or restore failed.');
}
