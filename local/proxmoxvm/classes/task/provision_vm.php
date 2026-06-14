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

namespace local_proxmoxvm\task;

use local_proxmoxvm\vm_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task: clone and configure a VM for a record.
 *
 * On failure the exception propagates so Moodle retries the task with an
 * increasing back-off - which doubles as the "wait for capacity" queue.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provision_vm extends \core\task\adhoc_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task_provision', 'local_proxmoxvm');
    }

    public function execute(): void {
        $data = $this->get_custom_data();
        if (empty($data->recordid)) {
            return;
        }
        vm_manager::do_provision((int) $data->recordid);
    }
}
