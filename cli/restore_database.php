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
 * Restores one built-in database artifact into an empty isolated database.
 *
 * Target credentials are accepted only through environment variables so the
 * password is not exposed in shell history or the process argument list.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
define('ABORT_AFTER_CONFIG', true);

require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/setuplib.php');
require_once($CFG->libdir . '/dmllib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/sessionlib.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/dml/moodle_database.php');
require_once($CFG->libdir . '/dtllib.php');

$version = null;
require($CFG->dirroot . '/version.php');
$moodlecodeversion = (int)$version;
$CFG->version = $moodlecodeversion;
unset($version);

[$options, $unrecognized] = cli_get_params(
    ['manifest' => null, 'help' => false],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error('Unknown option: ' . implode(', ', $unrecognized));
}

$help = <<<'HELP'
Restore a Secure S3 Storage built-in database backup into an EMPTY isolated
MariaDB/MySQL database. This command never restores into the live Moodle
database and does not download from S3.

Required option:
  --manifest=PATH   Local v2 .xml.gz.manifest.json downloaded with its payload

Required environment variables:
  SECURE_S3_RESTORE_DBHOST
  SECURE_S3_RESTORE_DBNAME
  SECURE_S3_RESTORE_DBUSER
  SECURE_S3_RESTORE_DBPASSWORD

Optional environment variables:
  SECURE_S3_RESTORE_DBTYPE   mariadb (default) or mysqli
  SECURE_S3_RESTORE_DBPREFIX mdl_ (default)
  SECURE_S3_RESTORE_DBPORT
  SECURE_S3_RESTORE_DBSOCKET

The target database must already exist and contain no tables. Use the same
Moodle code/schema version recorded by the artifact. Never target production.
HELP;

if ($options['help']) {
    echo $help . PHP_EOL;
    exit(0);
}
if (!is_string($options['manifest']) || $options['manifest'] === '') {
    cli_error('Missing --manifest. Use --help for usage.');
}

$required = [
    'SECURE_S3_RESTORE_DBHOST',
    'SECURE_S3_RESTORE_DBNAME',
    'SECURE_S3_RESTORE_DBUSER',
    'SECURE_S3_RESTORE_DBPASSWORD',
];
foreach ($required as $name) {
    if (getenv($name) === false || getenv($name) === '') {
        cli_error('Missing required restore environment variable: ' . $name);
    }
}

$dbtype = (string)(getenv('SECURE_S3_RESTORE_DBTYPE') ?: 'mariadb');
if (!in_array($dbtype, ['mariadb', 'mysqli'], true)) {
    cli_error('Only mariadb and mysqli restore targets are currently supported.');
}
$dbname = (string)getenv('SECURE_S3_RESTORE_DBNAME');
if (hash_equals((string)$CFG->dbname, $dbname)) {
    cli_error('Refusing to restore into the configured live Moodle database name.');
}
$prefix = (string)(getenv('SECURE_S3_RESTORE_DBPREFIX') ?: 'mdl_');
if (!preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $prefix)) {
    cli_error('Invalid target table prefix.');
}

try {
    $artifact = (new \tool_secure_s3_storage\local\database_artifact_v2_scanner())
        ->validate_manifest((string)$options['manifest']);
} catch (Throwable) {
    cli_error('Artifact manifest or payload validation failed.');
}
if ($artifact['moodleversion'] !== $moodlecodeversion) {
    cli_error('Artifact Moodle schema version does not match this Moodle code version.');
}

$targetdb = moodle_database::get_driver_instance($dbtype, 'native');
$dboptions = [];
if (($port = getenv('SECURE_S3_RESTORE_DBPORT')) !== false && $port !== '') {
    if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
        cli_error('Invalid target database port.');
    }
    $dboptions['dbport'] = (int)$port;
}
if (($socket = getenv('SECURE_S3_RESTORE_DBSOCKET')) !== false && $socket !== '') {
    $dboptions['dbsocket'] = $socket;
}

try {
    $targetdb->connect(
        (string)getenv('SECURE_S3_RESTORE_DBHOST'),
        (string)getenv('SECURE_S3_RESTORE_DBUSER'),
        (string)getenv('SECURE_S3_RESTORE_DBPASSWORD'),
        $dbname,
        $prefix,
        $dboptions
    );
} catch (Throwable) {
    cli_error('Unable to connect to the isolated restore database.');
}
if ($targetdb->get_tables()) {
    cli_error('Refusing to restore because the target database is not empty.');
}

$xmlpath = rtrim($CFG->tempdir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR .
    'secure-s3-restore-' . bin2hex(random_bytes(16)) . '.xml';
$input = gzopen($artifact['payloadpath'], 'rb');
$output = fopen($xmlpath, 'xb');
if ($input === false || $output === false) {
    if (is_resource($input)) {
        gzclose($input);
    }
    cli_error('Unable to create the private restore working file.');
}
chmod($xmlpath, 0600);

try {
    while (!gzeof($input)) {
        $chunk = gzread($input, 1024 * 1024);
        if ($chunk === false || ($chunk !== '' && fwrite($output, $chunk) !== strlen($chunk))) {
            throw new RuntimeException('Unable to decompress the restore payload.');
        }
    }
    gzclose($input);
    $input = null;
    fclose($output);
    $output = null;

    $importer = new file_xml_database_importer($xmlpath, $targetdb);
    $importer->set_transaction_mode('pertable');
    $importer->import_database();
    echo json_encode([
        'restored' => true,
        'artifactid' => $artifact['artifactid'],
        'targetdb' => $dbname,
        'moodleversion' => $artifact['moodleversion'],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) {
    cli_error('Isolated database restore failed (' . (new ReflectionClass($exception))->getShortName() . ').');
} finally {
    if (is_resource($input)) {
        gzclose($input);
    }
    if (is_resource($output)) {
        fclose($output);
    }
    if (is_file($xmlpath) && !is_link($xmlpath)) {
        unlink($xmlpath);
    }
    $targetdb->dispose();
}
