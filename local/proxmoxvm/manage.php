<?php
// This file is part of the local_proxmoxvm plugin for Moodle.
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Teacher/manager page: list all VMs and create extra VMs for students.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_proxmoxvm\vm_manager;

require_login();
$context = context_system::instance();
require_capability('local/proxmoxvm:manage', $context);

$manageurl = new moodle_url('/local/proxmoxvm/manage.php');

$PAGE->set_url($manageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('managevms', 'local_proxmoxvm'));
$PAGE->set_heading(get_string('managevms', 'local_proxmoxvm'));

$action = optional_param('action', '', PARAM_ALPHANUMEXT);

if ($action === 'createextra' && confirm_sesskey()) {
    $userid = required_param('userid', PARAM_INT);
    if ($userid && $DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
        vm_manager::request_provision($userid, vm_manager::ORIGIN_MANUAL, $USER->id);
        redirect($manageurl, get_string('vmcreatequeued', 'local_proxmoxvm'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($manageurl, get_string('error'), null, \core\output\notification::NOTIFY_ERROR);
}

if ($action === 'delete' && confirm_sesskey()) {
    $recordid = required_param('id', PARAM_INT);
    $doconfirm = optional_param('confirm', 0, PARAM_BOOL);
    $record = $DB->get_record(vm_manager::TABLE, ['id' => $recordid], '*', MUST_EXIST);
    $vmuser = $DB->get_record('user', ['id' => $record->userid]);

    if (!$doconfirm) {
        $continue = new moodle_url($manageurl, [
            'action' => 'delete', 'id' => $recordid, 'confirm' => 1, 'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirmdeletevm', 'local_proxmoxvm', $vmuser ? fullname($vmuser) : '#' . $record->userid),
            $continue, $manageurl);
        echo $OUTPUT->footer();
        exit;
    }

    vm_manager::request_deprovision($recordid);
    redirect($manageurl, get_string('vmdeletequeued', 'local_proxmoxvm'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// -- Create an extra VM for a student. ----------------------------------------

echo $OUTPUT->heading(get_string('createextravm', 'local_proxmoxvm'), 3);

$enabled = array_values(array_filter(array_map('intval',
    explode(',', (string) get_config('local_proxmoxvm', 'cohorts')))));
$useroptions = [];
if ($enabled) {
    list($insql, $params) = $DB->get_in_or_equal($enabled);
    $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
              FROM {user} u
              JOIN {cohort_members} cm ON cm.userid = u.id
             WHERE cm.cohortid $insql AND u.deleted = 0
          ORDER BY u.lastname, u.firstname";
    foreach ($DB->get_records_sql($sql, $params) as $u) {
        $useroptions[$u->id] = fullname($u) . ' (' . $u->email . ')';
    }
}

if ($useroptions) {
    echo html_writer::start_tag('form',
        ['method' => 'post', 'action' => $manageurl->out(false), 'class' => 'form-inline mb-4']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'createextra']);
    echo html_writer::select($useroptions, 'userid', '',
        ['' => get_string('choosedots')], ['class' => 'me-2']);
    echo html_writer::tag('button', get_string('createextravm', 'local_proxmoxvm'),
        ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
} else {
    echo html_writer::div(get_string('noeligibleusers', 'local_proxmoxvm'), 'alert alert-info');
}

// -- All VMs. -----------------------------------------------------------------

echo $OUTPUT->heading(get_string('allvms', 'local_proxmoxvm'), 3);

$records = $DB->get_records(vm_manager::TABLE, null, 'timecreated DESC');
if (!$records) {
    echo html_writer::div(get_string('novmsyet', 'local_proxmoxvm'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('col_user', 'local_proxmoxvm'),
        get_string('field_vmid', 'local_proxmoxvm'),
        get_string('col_node', 'local_proxmoxvm'),
        get_string('col_origin', 'local_proxmoxvm'),
        get_string('col_state', 'local_proxmoxvm'),
        get_string('field_ip', 'local_proxmoxvm'),
        get_string('col_actions', 'local_proxmoxvm'),
    ];
    foreach ($records as $r) {
        $vmuser = $DB->get_record('user', ['id' => $r->userid]);
        $deleteurl = new moodle_url($manageurl,
            ['action' => 'delete', 'id' => $r->id, 'sesskey' => sesskey()]);
        $table->data[] = [
            $vmuser ? fullname($vmuser) : '#' . $r->userid,
            $r->vmid ?: '—',
            $r->node ?: '—',
            get_string('origin_' . $r->origin, 'local_proxmoxvm'),
            get_string('state_' . $r->provisionstate, 'local_proxmoxvm'),
            $r->ipaddress ?: '—',
            html_writer::link($deleteurl, get_string('deletevm', 'local_proxmoxvm'),
                ['class' => 'btn btn-sm btn-outline-danger']),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
