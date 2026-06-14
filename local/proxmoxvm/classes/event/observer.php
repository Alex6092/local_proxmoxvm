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

namespace local_proxmoxvm\event;

use local_proxmoxvm\vm_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers. Each handler only does fast DB work (creating a record and
 * queuing a task) and never throws, to avoid disturbing the triggering action.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * A user was added to a cohort: provision a VM if that cohort is enabled.
     *
     * @param \core\event\cohort_member_added $event
     */
    public static function cohort_member_added(\core\event\cohort_member_added $event): void {
        try {
            if (!self::cohort_is_enabled((int) $event->objectid)) {
                return;
            }
            vm_manager::request_provision((int) $event->relateduserid, vm_manager::ORIGIN_AUTO);
        } catch (\Throwable $e) {
            debugging('local_proxmoxvm cohort_member_added: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * A user was removed from a cohort: deprovision their automatic VM if they
     * are no longer in any enabled cohort.
     *
     * @param \core\event\cohort_member_removed $event
     */
    public static function cohort_member_removed(\core\event\cohort_member_removed $event): void {
        try {
            if (!self::cohort_is_enabled((int) $event->objectid)) {
                return;
            }
            $userid = (int) $event->relateduserid;
            if (self::user_in_any_enabled_cohort($userid)) {
                return;
            }
            $vm = vm_manager::get_user_auto_vm($userid);
            if ($vm) {
                vm_manager::request_deprovision((int) $vm->id);
            }
        } catch (\Throwable $e) {
            debugging('local_proxmoxvm cohort_member_removed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * A user was deleted: deprovision all of their VMs (safety net).
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        try {
            foreach (vm_manager::get_user_vms((int) $event->objectid) as $vm) {
                vm_manager::request_deprovision((int) $vm->id);
            }
        } catch (\Throwable $e) {
            debugging('local_proxmoxvm user_deleted: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // -- Helpers. -------------------------------------------------------------

    /**
     * IDs of cohorts configured to trigger provisioning.
     *
     * @return int[]
     */
    protected static function get_enabled_cohorts(): array {
        $raw = (string) get_config('local_proxmoxvm', 'cohorts');
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    /**
     * @param int $cohortid
     * @return bool
     */
    protected static function cohort_is_enabled(int $cohortid): bool {
        return in_array($cohortid, self::get_enabled_cohorts(), true);
    }

    /**
     * Whether the user is still a member of any enabled cohort.
     *
     * @param int $userid
     * @return bool
     */
    protected static function user_in_any_enabled_cohort(int $userid): bool {
        global $DB;
        $enabled = self::get_enabled_cohorts();
        if (empty($enabled)) {
            return false;
        }
        list($insql, $params) = $DB->get_in_or_equal($enabled, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        return $DB->record_exists_select(
            'cohort_members',
            "userid = :userid AND cohortid $insql",
            $params
        );
    }
}
