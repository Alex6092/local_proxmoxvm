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
 * Adhoc task: roll a VM back to a snapshot (stop, rollback, restart) in the
 * background, since the operation can take a while.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class snapshot_rollback extends \core\task\adhoc_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task_snapshotrollback', 'local_proxmoxvm');
    }

    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data->recordid) || empty($data->snapname)) {
            return;
        }
        $record = $DB->get_record(vm_manager::TABLE, ['id' => $data->recordid]);
        if (!$record) {
            return;
        }
        vm_manager::snapshot_rollback($record, (string) $data->snapname);
    }
}
