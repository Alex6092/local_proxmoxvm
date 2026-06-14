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
 * Scheduled task: refresh cached status/IP for ready VMs.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task_reconcile', 'local_proxmoxvm');
    }

    public function execute(): void {
        global $DB;

        if (!get_config('local_proxmoxvm', 'apihost') || !get_config('local_proxmoxvm', 'tokenid')) {
            return;
        }

        $records = $DB->get_records(vm_manager::TABLE, ['provisionstate' => vm_manager::STATE_READY]);
        foreach ($records as $record) {
            vm_manager::refresh_status($record);
        }
    }
}
