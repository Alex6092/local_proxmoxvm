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

namespace local_proxmoxvm\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_proxmoxvm\vm_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_proxmoxvm.
 *
 * VM ownership records are stored at system context.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_proxmoxvm', [
            'userid'      => 'privacy:metadata:local_proxmoxvm:userid',
            'vmid'        => 'privacy:metadata:local_proxmoxvm:vmid',
            'node'        => 'privacy:metadata:local_proxmoxvm:node',
            'ipaddress'   => 'privacy:metadata:local_proxmoxvm:ipaddress',
            'timecreated' => 'privacy:metadata:local_proxmoxvm:timecreated',
        ], 'privacy:metadata:local_proxmoxvm');

        $collection->add_external_location_link('proxmox', [
            'username' => 'privacy:metadata:proxmox:username',
        ], 'privacy:metadata:proxmox');

        return $collection;
    }

    /**
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if (vm_manager::get_user_vms($userid)) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context instanceof \context_system) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {local_proxmoxvm}", []);
        }
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!in_array(CONTEXT_SYSTEM, $contextlist->get_contextlevels())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $records = $DB->get_records('local_proxmoxvm', ['userid' => $userid]);
        if (!$records) {
            return;
        }

        $data = [];
        foreach ($records as $record) {
            $data[] = (object) [
                'vmid'        => $record->vmid,
                'node'        => $record->node,
                'name'        => $record->name,
                'status'      => $record->status,
                'ipaddress'   => $record->ipaddress,
                'origin'      => $record->origin,
                'timecreated' => $record->timecreated ? userdate($record->timecreated) : '',
            ];
        }
        writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'local_proxmoxvm')],
            (object) ['vms' => $data]
        );
    }

    /**
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof \context_system) {
            $DB->delete_records('local_proxmoxvm');
        }
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if (in_array(CONTEXT_SYSTEM, $contextlist->get_contextlevels())) {
            $DB->delete_records('local_proxmoxvm', ['userid' => $contextlist->get_user()->id]);
        }
    }

    /**
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!($context instanceof \context_system)) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_proxmoxvm', "userid $insql", $params);
    }
}
