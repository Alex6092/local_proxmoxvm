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
 * Web diagnostic page (site administrators only).
 *
 * Runs the same connection/capacity check as the CLI, using Moodle's own
 * Proxmox client - so the result reflects exactly what provisioning sees.
 *
 * Access: /local/proxmoxvm/diagnose.php
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_admin();

$PAGE->set_url(new moodle_url('/local/proxmoxvm/diagnose.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_proxmoxvm') . ' - diagnostic');
$PAGE->set_heading(get_string('pluginname', 'local_proxmoxvm') . ' - diagnostic');

echo $OUTPUT->header();
echo html_writer::tag('pre', s(implode("\n", \local_proxmoxvm\diagnostics::run())),
    ['style' => 'padding:1em;background:#f5f5f5;border:1px solid #ddd;overflow:auto;']);
echo html_writer::div(
    html_writer::link(new moodle_url('/admin/settings.php', ['section' => 'local_proxmoxvm']),
        get_string('settings', 'moodle')),
    'mt-3'
);
echo $OUTPUT->footer();
