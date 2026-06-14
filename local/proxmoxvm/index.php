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
 * User VM dashboard: status, IP, power controls and snapshots.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_proxmoxvm\vm_manager;

require_login();
$context = context_system::instance();
require_capability('local/proxmoxvm:view', $context);

$indexurl = new moodle_url('/local/proxmoxvm/index.php');

$PAGE->set_url($indexurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mymachine', 'local_proxmoxvm'));
$PAGE->set_heading(get_string('mymachine', 'local_proxmoxvm'));

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$recordid = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

if ($action && confirm_sesskey()) {
    require_capability('local/proxmoxvm:control', $context);

    $record = vm_manager::get_owned_record($recordid, $USER->id);
    if (!$record) {
        throw new moodle_exception('recordnotfound', 'local_proxmoxvm');
    }

    // Destructive actions get a confirmation step first.
    if (in_array($action, ['snapshotrollback', 'snapshotdelete', 'reset'], true) && !$confirm) {
        if ($action === 'reset') {
            $snapname = vm_manager::SNAPSHOT_INITIAL;
            $msgkey = 'confirmreset';
        } else {
            $snapname = required_param('snapname', PARAM_TEXT);
            $msgkey = ($action === 'snapshotrollback') ? 'confirmrollback' : 'confirmsnapshotdelete';
        }
        $continue = new moodle_url($indexurl, [
            'action' => $action,
            'id' => $record->id,
            'snapname' => $snapname,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(get_string($msgkey, 'local_proxmoxvm', s($snapname)), $continue, $indexurl);
        echo $OUTPUT->footer();
        exit;
    }

    try {
        switch ($action) {
            case 'start':
            case 'stop':
            case 'reboot':
                vm_manager::power($record, $action);
                redirect($indexurl, get_string('actionqueued', 'local_proxmoxvm'), null,
                    \core\output\notification::NOTIFY_SUCCESS);
                break;

            case 'snapshotcreate':
                $snapname = required_param('snapname', PARAM_TEXT);
                $snapdesc = optional_param('snapdesc', '', PARAM_TEXT);
                vm_manager::snapshot_create($record, $snapname, $snapdesc);
                redirect($indexurl, get_string('snapshotcreated', 'local_proxmoxvm'), null,
                    \core\output\notification::NOTIFY_SUCCESS);
                break;

            case 'snapshotdelete':
                $snapname = required_param('snapname', PARAM_TEXT);
                vm_manager::snapshot_delete($record, $snapname);
                redirect($indexurl, get_string('snapshotdeleted', 'local_proxmoxvm'), null,
                    \core\output\notification::NOTIFY_SUCCESS);
                break;

            case 'reset':
            case 'snapshotrollback':
                $snapname = ($action === 'reset')
                    ? vm_manager::SNAPSHOT_INITIAL
                    : required_param('snapname', PARAM_TEXT);
                $task = new \local_proxmoxvm\task\snapshot_rollback();
                $task->set_custom_data(['recordid' => $record->id, 'snapname' => $snapname]);
                \core\task\manager::queue_adhoc_task($task);
                redirect($indexurl,
                    get_string($action === 'reset' ? 'resetqueued' : 'rollbackqueued', 'local_proxmoxvm'),
                    null, \core\output\notification::NOTIFY_SUCCESS);
                break;

            default:
                redirect($indexurl);
        }
    } catch (\Throwable $e) {
        redirect($indexurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

// -- Gather data for display. -------------------------------------------------

$vms = vm_manager::get_user_vms($USER->id);
$sshuser = trim((string) get_config('local_proxmoxvm', 'sshuser'));
$snapquota = (int) get_config('local_proxmoxvm', 'snapshotquota');

$vmcontext = [];
foreach ($vms as $vm) {
    if ($vm->provisionstate === vm_manager::STATE_READY) {
        vm_manager::refresh_status($vm);
    }

    $snaps = [];
    $snaperror = '';
    $canreset = false;
    if ($vm->provisionstate === vm_manager::STATE_READY) {
        try {
            $overview = vm_manager::snapshot_overview($vm);
            $canreset = $overview['hasinitial'];
            foreach ($overview['student'] as $s) {
                $snaps[] = (object) [
                    'name' => $s['name'] ?? '',
                    'description' => $s['description'] ?? '',
                ];
            }
        } catch (\Throwable $e) {
            $snaperror = $e->getMessage();
        }
    }

    $statuskey = in_array($vm->status, ['running', 'stopped'], true) ? $vm->status : 'unknown';
    $isrunning = ($vm->status === 'running');
    $ip = (string) ($vm->ipaddress ?? '');

    $vmcontext[] = (object) [
        'id' => $vm->id,
        'vmid' => $vm->vmid,
        'name' => $vm->name,
        'node' => $vm->node,
        'isready' => $vm->provisionstate === vm_manager::STATE_READY,
        'ispending' => in_array($vm->provisionstate,
            [vm_manager::STATE_PENDING, vm_manager::STATE_PROVISIONING], true),
        'iserror' => $vm->provisionstate === vm_manager::STATE_ERROR,
        'statelabel' => get_string('state_' . $vm->provisionstate, 'local_proxmoxvm'),
        'isrunning' => $isrunning,
        'statuslabel' => get_string('status_' . $statuskey, 'local_proxmoxvm'),
        'badgeclass' => $isrunning ? 'badge bg-success' : 'badge bg-secondary',
        'ip' => $ip,
        'hasip' => $ip !== '',
        'sshhint' => ($ip !== '' && $sshuser !== '') ? "ssh {$sshuser}@{$ip}" : '',
        'lasterror' => $vm->lasterror,
        'snapshots' => $snaps,
        'hassnapshots' => !empty($snaps),
        'snaperror' => $snaperror,
        'quotareached' => ($snapquota > 0 && count($snaps) >= $snapquota),
        'canreset' => $canreset,
    ];
}

$templatecontext = (object) [
    'hasvm' => !empty($vmcontext),
    'vms' => $vmcontext,
    'sesskey' => sesskey(),
    'actionurl' => $indexurl->out(false),
    'canmanage' => has_capability('local/proxmoxvm:manage', $context),
    'manageurl' => (new moodle_url('/local/proxmoxvm/manage.php'))->out(false),
];

// Auto-refresh the status every 20s for liveness, but pause while the user is
// filling in a form (focused field, or any text field with content) so their
// snapshot name/description is never lost on reload.
$refreshjs = <<<'JS'
(function() {
    setInterval(function() {
        var ae = document.activeElement;
        if (ae && /^(INPUT|TEXTAREA|SELECT)$/.test(ae.tagName)) {
            return;
        }
        var fields = document.querySelectorAll('.local-proxmoxvm-dashboard input[type=text]');
        for (var i = 0; i < fields.length; i++) {
            if (fields[i].value !== '') {
                return;
            }
        }
        window.location.reload();
    }, 20000);
})();
JS;
$PAGE->requires->js_init_code($refreshjs, true);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_proxmoxvm/dashboard', $templatecontext);
echo $OUTPUT->footer();
