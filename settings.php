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
 * Administrative settings.
 *
 * Long-lived AWS credentials are deliberately not configurable here.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'tool_secure_s3_storage',
        get_string('pluginname', 'tool_secure_s3_storage')
    );

    $settings->add(new admin_setting_heading(
        'tool_secure_s3_storage/awsheading',
        get_string('awsconfiguration', 'tool_secure_s3_storage'),
        get_string('awsconfiguration_desc', 'tool_secure_s3_storage')
    ));

    $settings->add(new admin_setting_configtext(
        'tool_secure_s3_storage/region',
        get_string('region', 'tool_secure_s3_storage'),
        get_string('region_desc', 'tool_secure_s3_storage'),
        'ap-northeast-1',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'tool_secure_s3_storage/bucket',
        get_string('bucket', 'tool_secure_s3_storage'),
        get_string('bucket_desc', 'tool_secure_s3_storage'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'tool_secure_s3_storage/prefix',
        get_string('prefix', 'tool_secure_s3_storage'),
        get_string('prefix_desc', 'tool_secure_s3_storage'),
        'moodle/',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_heading(
        'tool_secure_s3_storage/sourceheading',
        get_string('backupsource', 'tool_secure_s3_storage'),
        get_string('backupsource_desc', 'tool_secure_s3_storage')
    ));

    $settings->add(new admin_setting_configtext(
        'tool_secure_s3_storage/sourcedirectory',
        get_string('sourcedirectory', 'tool_secure_s3_storage'),
        get_string('sourcedirectory_desc', 'tool_secure_s3_storage'),
        '/var/moodlebackups',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'tool_secure_s3_storage/stabilityseconds',
        get_string('stabilityseconds', 'tool_secure_s3_storage'),
        get_string('stabilityseconds_desc', 'tool_secure_s3_storage'),
        60,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tool_secure_s3_storage/transferenabled',
        get_string('transferenabled', 'tool_secure_s3_storage'),
        get_string('transferenabled_desc', 'tool_secure_s3_storage'),
        0
    ));

    $settings->add(new admin_setting_heading(
        'tool_secure_s3_storage/databaseheading',
        get_string('databasebackupsource', 'tool_secure_s3_storage'),
        get_string('databasebackupsource_desc', 'tool_secure_s3_storage')
    ));

    $settings->add(new admin_setting_configselect(
        'tool_secure_s3_storage/databaseproducermode',
        get_string('databaseproducermode', 'tool_secure_s3_storage'),
        get_string('databaseproducermode_desc', 'tool_secure_s3_storage'),
        'builtin',
        [
            'builtin' => get_string('databaseproducermode_builtin', 'tool_secure_s3_storage'),
            'external' => get_string('databaseproducermode_external', 'tool_secure_s3_storage'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'tool_secure_s3_storage/databaseartifactdirectory',
        get_string('databaseartifactdirectory', 'tool_secure_s3_storage'),
        get_string('databaseartifactdirectory_desc', 'tool_secure_s3_storage'),
        '/database-artifacts',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tool_secure_s3_storage/databasetransferenabled',
        get_string('databasetransferenabled', 'tool_secure_s3_storage'),
        get_string('databasetransferenabled_desc', 'tool_secure_s3_storage'),
        0
    ));

    $settings->add(new admin_setting_heading(
        'tool_secure_s3_storage/contentheading',
        get_string('contentbackup', 'tool_secure_s3_storage'),
        get_string('contentbackup_desc', 'tool_secure_s3_storage')
    ));

    $settings->add(new admin_setting_configtext(
        'tool_secure_s3_storage/contentbatchsize',
        get_string('contentbatchsize', 'tool_secure_s3_storage'),
        get_string('contentbatchsize_desc', 'tool_secure_s3_storage'),
        100,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tool_secure_s3_storage/contenttransferenabled',
        get_string('contenttransferenabled', 'tool_secure_s3_storage'),
        get_string('contenttransferenabled_desc', 'tool_secure_s3_storage'),
        0
    ));

    $ADMIN->add('tools', $settings);
}
