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
 * Operational status and safe manual task queueing.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('tool/secure_s3_storage:manage', context_system::instance());

$pageurl = new moodle_url('/admin/tool/secure_s3_storage/status.php');
admin_externalpage_setup('tool_secure_s3_storage_status');
$PAGE->set_url($pageurl);

$taskdefinitions = [
    'course' => [
        'classname' => '\\tool_secure_s3_storage\\task\\transfer_course_backups',
        'setting' => 'transferenabled',
    ],
    'database' => [
        'classname' => '\\tool_secure_s3_storage\\task\\transfer_database_backups',
        'setting' => 'databasetransferenabled',
    ],
    'content' => [
        'classname' => '\\tool_secure_s3_storage\\task\\transfer_content_objects',
        'setting' => 'contenttransferenabled',
    ],
];

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();
    if (!isset($taskdefinitions[$action])) {
        throw new moodle_exception('invalidmanualtask', 'tool_secure_s3_storage');
    }
    $definition = $taskdefinitions[$action];
    if (!get_config('tool_secure_s3_storage', $definition['setting'])) {
        throw new moodle_exception('manualtaskdisabled', 'tool_secure_s3_storage');
    }

    $task = new \tool_secure_s3_storage\task\manual_transfer();
    $task->set_component('tool_secure_s3_storage');
    $task->set_custom_data((object)['type' => $action]);
    $queued = \core\task\manager::queue_adhoc_task($task, true);
    $message = $queued ? 'manualtaskqueued' : 'manualtaskalreadyqueued';
    redirect($pageurl, get_string($message, 'tool_secure_s3_storage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('operationsstatus', 'tool_secure_s3_storage'));
echo $OUTPUT->notification(
    get_string('operationssecuritynotice', 'tool_secure_s3_storage'),
    \core\output\notification::NOTIFY_INFO
);

$config = get_config('tool_secure_s3_storage');
$configurationtable = new html_table();
$configurationtable->caption = get_string('configurationstatus', 'tool_secure_s3_storage');
$configurationtable->head = [
    get_string('settinglabel', 'tool_secure_s3_storage'),
    get_string('valuelabel', 'tool_secure_s3_storage'),
];
$configurationtable->data = [
    [get_string('region', 'tool_secure_s3_storage'), s($config->region ?? '')],
    [get_string('bucket', 'tool_secure_s3_storage'), s($config->bucket ?? '')],
    [get_string('prefix', 'tool_secure_s3_storage'), s($config->prefix ?? '')],
    [get_string('databaseproducermode', 'tool_secure_s3_storage'), s($config->databaseproducermode ?? 'builtin')],
];
echo html_writer::table($configurationtable);

$scheduletable = new html_table();
$scheduletable->caption = get_string('scheduledoperations', 'tool_secure_s3_storage');
$scheduletable->head = [
    get_string('tasklabel', 'tool_secure_s3_storage'),
    get_string('enabledlabel', 'tool_secure_s3_storage'),
    get_string('schedulelabel', 'tool_secure_s3_storage'),
    get_string('lastrunlabel', 'tool_secure_s3_storage'),
    get_string('nextrunlabel', 'tool_secure_s3_storage'),
    get_string('faildelaylabel', 'tool_secure_s3_storage'),
    get_string('actionlabel', 'tool_secure_s3_storage'),
];

foreach ($taskdefinitions as $type => $definition) {
    $scheduledtask = \core\task\manager::get_scheduled_task($definition['classname']);
    if ($scheduledtask === false) {
        continue;
    }
    $enabled = (bool)get_config('tool_secure_s3_storage', $definition['setting']);
    $schedule = implode(' ', [
        $scheduledtask->get_minute(),
        $scheduledtask->get_hour(),
        $scheduledtask->get_day(),
        $scheduledtask->get_month(),
        $scheduledtask->get_day_of_week(),
    ]);
    $last = $scheduledtask->get_last_run_time();
    $next = $scheduledtask->get_next_run_time();
    $actioncell = get_string('manualtaskdisabledshort', 'tool_secure_s3_storage');
    if ($enabled && !$scheduledtask->get_disabled()) {
        $actioncell = $OUTPUT->single_button(
            new moodle_url($pageurl, ['action' => $type, 'sesskey' => sesskey()]),
            get_string('queuenow', 'tool_secure_s3_storage'),
            'post'
        );
    }
    $scheduletable->data[] = [
        format_string($scheduledtask->get_name()),
        $enabled && !$scheduledtask->get_disabled() ? get_string('yes') : get_string('no'),
        s($schedule),
        $last ? userdate($last) : get_string('never'),
        $next ? userdate($next) : get_string('never'),
        (string)$scheduledtask->get_fail_delay(),
        $actioncell,
    ];
}
echo html_writer::table($scheduletable);
echo html_writer::link(
    new moodle_url('/admin/tool/task/scheduledtasks.php'),
    get_string('editschedules', 'tool_secure_s3_storage')
);

$summarytable = new html_table();
$summarytable->caption = get_string('transfersummary', 'tool_secure_s3_storage');
$summarytable->head = [
    get_string('statuslabel', 'tool_secure_s3_storage'),
    get_string('countlabel', 'tool_secure_s3_storage'),
];
foreach (['success', 'failed', 'uploading', 'observed'] as $status) {
    $summarytable->data[] = [
        get_string('transferstatus_' . $status, 'tool_secure_s3_storage'),
        $DB->count_records('tool_secure_s3_storage_xfer', ['status' => $status]),
    ];
}
echo html_writer::table($summarytable);

$records = $DB->get_records(
    'tool_secure_s3_storage_xfer',
    null,
    'timemodified DESC',
    'id, filename, filesize, status, errormessage, timemodified',
    0,
    20
);
$recenttable = new html_table();
$recenttable->caption = get_string('recenttransfers', 'tool_secure_s3_storage');
$recenttable->head = [
    get_string('filenamelabel', 'tool_secure_s3_storage'),
    get_string('sizelabel', 'tool_secure_s3_storage'),
    get_string('statuslabel', 'tool_secure_s3_storage'),
    get_string('errorlabel', 'tool_secure_s3_storage'),
    get_string('updatedlabel', 'tool_secure_s3_storage'),
];
$knownstatuses = ['success', 'failed', 'uploading', 'observed'];
foreach ($records as $record) {
    $statuslabel = in_array($record->status, $knownstatuses, true)
        ? get_string('transferstatus_' . $record->status, 'tool_secure_s3_storage')
        : s($record->status);
    $recenttable->data[] = [
        s($record->filename),
        display_size((int)$record->filesize),
        $statuslabel,
        s($record->errormessage ?? ''),
        userdate((int)$record->timemodified),
    ];
}
echo html_writer::table($recenttable);

echo $OUTPUT->heading(get_string('retentionpolicy', 'tool_secure_s3_storage'), 3);
echo html_writer::tag('p', get_string('retentionpolicy_desc', 'tool_secure_s3_storage'));
echo html_writer::alist([
    get_string('retention_course', 'tool_secure_s3_storage'),
    get_string('retention_database', 'tool_secure_s3_storage'),
    get_string('retention_content', 'tool_secure_s3_storage'),
    get_string('retention_s3', 'tool_secure_s3_storage'),
]);

echo $OUTPUT->footer();
