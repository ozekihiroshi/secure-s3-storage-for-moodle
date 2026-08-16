<?php
// This file is part of Secure S3 Storage for Moodle.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Version metadata for Secure S3 Storage.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'tool_secure_s3_storage';
$plugin->version = 2026081602;
$plugin->requires = 2026042000;
$plugin->supported = [502, 502];
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '0.2.0';
