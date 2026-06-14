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
 * Integrated noVNC console for a user's own VM.
 *
 * The browser only ever talks to Moodle: it connects a websocket to a
 * same-origin path (default /pvews) that the web server reverse-proxies to the
 * Proxmox vncwebsocket endpoint, injecting the API token. Proxmox is never
 * exposed to the browser. The per-VM VNC ticket is minted here after an
 * ownership check, so a student can only reach their own console.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_proxmoxvm\vm_manager;

require_login();
$context = context_system::instance();
require_capability('local/proxmoxvm:console', $context);

$recordid = required_param('id', PARAM_INT);
$record = vm_manager::get_owned_record($recordid, $USER->id);
if (!$record) {
    throw new moodle_exception('recordnotfound', 'local_proxmoxvm');
}
if (!get_config('local_proxmoxvm', 'enableconsole')) {
    throw new moodle_exception('errorconsoledisabled', 'local_proxmoxvm');
}

$PAGE->set_url(new moodle_url('/local/proxmoxvm/console.php', ['id' => $recordid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('console_title', 'local_proxmoxvm', format_string($record->name)));

$backurl = (new moodle_url('/local/proxmoxvm/index.php'))->out(false);

$error = '';
$ticket = null;
try {
    $ticket = vm_manager::console_ticket($record);
} catch (\Throwable $e) {
    $error = $e->getMessage();
}

echo $OUTPUT->header();

if ($error !== '' || empty($ticket['ticket'])) {
    echo $OUTPUT->notification($error !== '' ? $error : get_string('console_authfailed', 'local_proxmoxvm'),
        \core\output\notification::NOTIFY_ERROR);
    echo html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    exit;
}

$wspath = (string) get_config('local_proxmoxvm', 'consolewspath');
if ($wspath === '') {
    $wspath = '/pvews';
}

// Toolbar + screen.
echo html_writer::start_div('local-proxmoxvm-console',
    ['style' => 'display:flex;flex-direction:column;height:80vh;border:1px solid #000;']);
echo html_writer::start_div('local-proxmoxvm-toolbar',
    ['style' => 'display:flex;gap:.5rem;align-items:center;padding:.4rem .6rem;background:#222;color:#eee;']);
echo html_writer::tag('strong', format_string($record->name));
echo html_writer::tag('span', get_string('console_connecting', 'local_proxmoxvm'),
    ['id' => 'proxmoxvm-status', 'style' => 'opacity:.8;']);
echo html_writer::tag('button', get_string('console_ctrlaltdel', 'local_proxmoxvm'),
    ['id' => 'proxmoxvm-cad', 'type' => 'button', 'class' => 'btn btn-sm btn-outline-light',
     'style' => 'margin-left:auto;']);
echo html_writer::link($backurl, get_string('back'),
    ['class' => 'btn btn-sm btn-outline-light']);
echo html_writer::end_div();
echo html_writer::div('', 'local-proxmoxvm-screen',
    ['id' => 'proxmoxvm-screen', 'style' => 'flex:1;background:#000;']);
echo html_writer::end_div();

// Values for the noVNC module (JSON-encoded for safe injection).
$novncurljson = json_encode((new moodle_url('/local/proxmoxvm/thirdparty/novnc/core/rfb.js'))->out(false));
$wspathjson = json_encode($wspath);
$nodejson = json_encode($ticket['node']);
$vmidjson = json_encode($ticket['vmid']);
$portjson = json_encode($ticket['port']);
$ticketjson = json_encode($ticket['ticket']);
$connectedjson = json_encode(get_string('console_connected', 'local_proxmoxvm'));
$disconnectedjson = json_encode(get_string('console_disconnected', 'local_proxmoxvm'));
$authfailedjson = json_encode(get_string('console_authfailed', 'local_proxmoxvm'));

$module = <<<JS
import RFB from {$novncurljson};

const statusEl = document.getElementById('proxmoxvm-status');
const screenEl = document.getElementById('proxmoxvm-screen');

const wsUrl = "wss://" + location.host + {$wspathjson}
    + "/nodes/" + {$nodejson} + "/qemu/" + {$vmidjson}
    + "/vncwebsocket?port=" + {$portjson}
    + "&vncticket=" + encodeURIComponent({$ticketjson});

let rfb = null;
try {
    rfb = new RFB(screenEl, wsUrl, { credentials: { password: {$ticketjson} } });
    rfb.scaleViewport = true;
    rfb.addEventListener("connect", function() { statusEl.textContent = {$connectedjson}; });
    rfb.addEventListener("disconnect", function() { statusEl.textContent = {$disconnectedjson}; });
    rfb.addEventListener("securityfailure", function() { statusEl.textContent = {$authfailedjson}; });
} catch (e) {
    statusEl.textContent = "" + e;
}

const cad = document.getElementById('proxmoxvm-cad');
if (cad) {
    cad.addEventListener("click", function() { if (rfb) { rfb.sendCtrlAltDel(); } });
}
JS;

echo '<script type="module">' . "\n" . $module . "\n" . '</script>';

echo $OUTPUT->footer();
