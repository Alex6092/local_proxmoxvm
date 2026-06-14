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
 * Plugin callbacks.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Inject an icon in the top navbar linking to the VM dashboard.
 *
 * Shown to logged-in users who own a VM, and to teachers/managers.
 *
 * @param renderer_base $renderer
 * @return string HTML.
 */
function local_proxmoxvm_render_navbar_output(\renderer_base $renderer): string {
    global $USER, $DB;

    if (during_initial_install() || !isloggedin() || isguestuser()) {
        return '';
    }

    // Guard the whole thing: this runs on every page, including the plugin's own
    // install/upgrade screen where capabilities and the table do not exist yet.
    try {
        $context = context_system::instance();
        $canmanage = has_capability('local/proxmoxvm:manage', $context);
        $canview = has_capability('local/proxmoxvm:view', $context);

        if (!$canmanage && !$canview) {
            return '';
        }

        // Only show to plain users if they actually have a VM.
        if (!$canmanage && !$DB->record_exists('local_proxmoxvm', ['userid' => $USER->id])) {
            return '';
        }
    } catch (\Throwable $e) {
        return '';
    }

    $label = get_string('mymachine', 'local_proxmoxvm');
    $icon = html_writer::tag('i', '', [
        'class' => 'icon fa fa-server fa-fw',
        'aria-hidden' => 'true',
    ]);

    return html_writer::div(
        html_writer::link(
            new moodle_url('/local/proxmoxvm/index.php'),
            $icon,
            ['class' => 'nav-link', 'title' => $label, 'aria-label' => $label]
        ),
        'd-flex align-items-center'
    );
}
