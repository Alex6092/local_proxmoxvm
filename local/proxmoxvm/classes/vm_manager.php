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

namespace local_proxmoxvm;

use local_proxmoxvm\proxmox\client;
use local_proxmoxvm\proxmox\node_selector;
use local_proxmoxvm\task\provision_vm;
use local_proxmoxvm\task\deprovision_vm;

defined('MOODLE_INTERNAL') || die();

/**
 * Business logic for provisioning, deprovisioning and controlling VMs.
 *
 * Heavy Proxmox calls are always run from adhoc/scheduled tasks, never from
 * web requests or event observers.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vm_manager {

    /** Provisioning states. */
    const STATE_PENDING = 'pending';
    const STATE_PROVISIONING = 'provisioning';
    const STATE_READY = 'ready';
    const STATE_ERROR = 'error';
    const STATE_DEPROVISIONING = 'deprovisioning';

    /** VM origins. */
    const ORIGIN_AUTO = 'auto';
    const ORIGIN_MANUAL = 'manual';

    /** @var string DB table. */
    const TABLE = 'local_proxmoxvm';

    /** Reserved name of the protected initial snapshot (student cannot delete it). */
    const SNAPSHOT_INITIAL = 'initial';

    // -- Queries. -------------------------------------------------------------

    /**
     * All VM records owned by a user.
     *
     * @param int $userid
     * @return array
     */
    public static function get_user_vms(int $userid): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['userid' => $userid], 'timecreated ASC');
    }

    /**
     * The user's automatically-provisioned VM, if any.
     *
     * @param int $userid
     * @return \stdClass|false
     */
    public static function get_user_auto_vm(int $userid) {
        global $DB;
        return $DB->get_record(self::TABLE, ['userid' => $userid, 'origin' => self::ORIGIN_AUTO]);
    }

    /**
     * Fetch a record but only if it belongs to the given user (ownership check).
     *
     * @param int $recordid
     * @param int $userid
     * @return \stdClass|false
     */
    public static function get_owned_record(int $recordid, int $userid) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $recordid, 'userid' => $userid]);
    }

    // -- Provisioning. --------------------------------------------------------

    /**
     * Create a pending record and queue the provisioning task.
     *
     * @param int $userid
     * @param string $origin
     * @param int|null $createdby
     * @return int|null New record id, or null if skipped (already provisioned).
     */
    public static function request_provision(int $userid, string $origin = self::ORIGIN_AUTO, ?int $createdby = null) {
        global $DB;

        // One automatic VM per user maximum (a teacher may add manual extras).
        if ($origin === self::ORIGIN_AUTO && self::get_user_auto_vm($userid)) {
            return null;
        }

        $now = time();
        $record = (object) [
            'userid'         => $userid,
            'origin'         => $origin,
            'createdby'      => $createdby,
            'provisionstate' => self::STATE_PENDING,
            'timecreated'    => $now,
            'timemodified'   => $now,
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);

        $task = new provision_vm();
        $task->set_custom_data(['recordid' => $record->id]);
        \core\task\manager::queue_adhoc_task($task, true);

        return $record->id;
    }

    /**
     * Provision the VM for a record. Idempotent enough to be retried.
     *
     * @param int $recordid
     * @throws \Throwable Rethrows so the adhoc task is retried.
     */
    public static function do_provision(int $recordid): void {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $recordid]);
        if (!$record || $record->provisionstate === self::STATE_READY) {
            return;
        }

        $client = new client();
        $cores = max(1, (int) get_config('local_proxmoxvm', 'cores'));
        $memory = max(64, (int) get_config('local_proxmoxvm', 'memory'));

        try {
            // Step 1: clone (only if not already cloned).
            if (empty($record->vmid)) {
                $selector = new node_selector($client);
                $choice = $selector->pick($memory);
                if (!$choice) {
                    self::notify_cluster_full();
                    throw new \moodle_exception('errornocapacity', 'local_proxmoxvm');
                }

                $newid = $client->get_next_vmid();
                $name = self::build_vm_name($record->userid);

                $record->node = $choice->node;
                $record->template = (string) $choice->templateid;
                $record->vmid = $newid;
                $record->name = $name;
                $record->provisionstate = self::STATE_PROVISIONING;
                $record->timemodified = time();
                $DB->update_record(self::TABLE, $record);

                $upid = $client->clone_vm($choice->node, $choice->templateid, $newid, $name);
                $client->wait_for_task($choice->node, $upid, 300);
            }

            // Step 2: configure resources and ensure the guest agent is on.
            $client->set_config($record->node, (int) $record->vmid, [
                'cores'  => $cores,
                'memory' => $memory,
                'onboot' => 1,
                'agent'  => 1,
            ]);

            // Step 2b: protected initial snapshot for student self-reset (taken
            // while stopped, before first boot, so rollback is a clean reset).
            self::ensure_initial_snapshot($client, $record);

            // Step 3: autostart.
            if (get_config('local_proxmoxvm', 'autostart')) {
                $status = $client->vm_current_status($record->node, (int) $record->vmid);
                if (($status['status'] ?? '') !== 'running') {
                    $upid = $client->start_vm($record->node, (int) $record->vmid);
                    $client->wait_for_task($record->node, $upid, 60);
                }
                $record->status = 'running';
            }

            // Step 4: best-effort IP from the guest agent (reconcile catches it later).
            $ip = self::try_get_ip($client, $record->node, (int) $record->vmid);
            if ($ip !== null) {
                $record->ipaddress = $ip;
            }

            $record->provisionstate = self::STATE_READY;
            $record->lasterror = null;
            $record->statuschecked = time();
            $record->timemodified = time();
            $DB->update_record(self::TABLE, $record);

        } catch (\Throwable $e) {
            // Keep PENDING (so retries re-select a node) while no VMID exists,
            // otherwise mark ERROR but still let the task retry.
            $record->provisionstate = empty($record->vmid) ? self::STATE_PENDING : self::STATE_ERROR;
            $record->lasterror = $e->getMessage();
            $record->timemodified = time();
            $DB->update_record(self::TABLE, $record);
            throw $e;
        }
    }

    // -- Deprovisioning. ------------------------------------------------------

    /**
     * Mark a record for deletion and queue the task.
     *
     * @param int $recordid
     */
    public static function request_deprovision(int $recordid): void {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $recordid]);
        if (!$record) {
            return;
        }
        $record->provisionstate = self::STATE_DEPROVISIONING;
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        $task = new deprovision_vm();
        $task->set_custom_data(['recordid' => $record->id]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Stop and delete the VM, then drop the record.
     *
     * @param int $recordid
     * @throws \Throwable Rethrows so the adhoc task is retried.
     */
    public static function do_deprovision(int $recordid): void {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $recordid]);
        if (!$record) {
            return;
        }

        if (!empty($record->vmid) && !empty($record->node)) {
            $client = new client();

            // Stop first if running (ignore failures - it may already be gone).
            try {
                $status = $client->vm_current_status($record->node, (int) $record->vmid);
                if (($status['status'] ?? '') === 'running') {
                    $upid = $client->stop_vm($record->node, (int) $record->vmid);
                    $client->wait_for_task($record->node, $upid, 60);
                }
            } catch (\Throwable $ignore) {
                // VM might not exist anymore; deletion below confirms.
                $ignore = null;
            }

            try {
                $upid = $client->delete_vm($record->node, (int) $record->vmid);
                $client->wait_for_task($record->node, $upid, 120);
            } catch (\Throwable $e) {
                // Swallow only if the VM truly no longer exists; otherwise retry.
                $stillexists = false;
                try {
                    $client->vm_current_status($record->node, (int) $record->vmid);
                    $stillexists = true;
                } catch (\Throwable $gone) {
                    $stillexists = false;
                }
                if ($stillexists) {
                    $record->lasterror = $e->getMessage();
                    $record->timemodified = time();
                    $DB->update_record(self::TABLE, $record);
                    throw $e;
                }
            }
        }

        $DB->delete_records(self::TABLE, ['id' => $record->id]);
    }

    // -- Control (used by the UI in M2). --------------------------------------

    /**
     * Power action on a VM record.
     *
     * @param \stdClass $record
     * @param string $action start|stop|forcestop|reboot
     * @return string|null UPID.
     */
    public static function power(\stdClass $record, string $action) {
        $client = new client();
        switch ($action) {
            case 'start':
                return $client->start_vm($record->node, (int) $record->vmid);
            case 'stop':
                return $client->shutdown_vm($record->node, (int) $record->vmid);
            case 'forcestop':
                return $client->stop_vm($record->node, (int) $record->vmid);
            case 'reboot':
                return $client->reboot_vm($record->node, (int) $record->vmid);
            default:
                throw new \coding_exception('Unknown power action: ' . $action);
        }
    }

    /**
     * Snapshot overview for a VM: the student's own snapshots (excluding the
     * synthetic "current" entry and the protected initial snapshot), and whether
     * the protected initial snapshot exists.
     *
     * @param \stdClass $record
     * @return array{student: array, hasinitial: bool}
     */
    public static function snapshot_overview(\stdClass $record): array {
        $client = new client();
        $student = [];
        $hasinitial = false;
        foreach ($client->list_snapshots($record->node, (int) $record->vmid) as $s) {
            $name = $s['name'] ?? '';
            if ($name === 'current') {
                continue;
            }
            if ($name === self::SNAPSHOT_INITIAL) {
                $hasinitial = true;
                continue;
            }
            $student[] = $s;
        }
        return ['student' => $student, 'hasinitial' => $hasinitial];
    }

    /**
     * Create a RAM-less snapshot, enforcing the configured quota.
     *
     * @param \stdClass $record
     * @param string $name
     * @param string $description
     * @return string|null UPID.
     * @throws \moodle_exception when the quota is reached.
     */
    public static function snapshot_create(\stdClass $record, string $name, string $description = '') {
        // Sanitise the name server-side so students need not worry about valid
        // characters: collapse any run of invalid chars to a single underscore.
        $name = preg_replace('/[^A-Za-z0-9]+/', '_', trim((string) $name));
        $name = trim($name, '_');
        if ($name === '') {
            $name = 'snap_' . date('Ymd_His');
        } else if (!preg_match('/^[A-Za-z]/', $name)) {
            $name = 's_' . $name;
        }
        $name = substr($name, 0, 40);
        if (strtolower($name) === self::SNAPSHOT_INITIAL) {
            throw new \moodle_exception('errorreservedname', 'local_proxmoxvm');
        }

        $overview = self::snapshot_overview($record);
        $quota = (int) get_config('local_proxmoxvm', 'snapshotquota');
        if ($quota > 0 && count($overview['student']) >= $quota) {
            throw new \moodle_exception('errorsnapquota', 'local_proxmoxvm', '', $quota);
        }

        $client = new client();
        return $client->create_snapshot($record->node, (int) $record->vmid, $name, $description);
    }

    /**
     * Roll back to a snapshot. Stops the VM if needed, then restores its prior
     * running state.
     *
     * @param \stdClass $record
     * @param string $name
     */
    public static function snapshot_rollback(\stdClass $record, string $name): void {
        $client = new client();
        $status = $client->vm_current_status($record->node, (int) $record->vmid);
        $wasrunning = (($status['status'] ?? '') === 'running');

        if ($wasrunning) {
            $upid = $client->stop_vm($record->node, (int) $record->vmid);
            $client->wait_for_task($record->node, $upid, 60);
        }
        $upid = $client->rollback_snapshot($record->node, (int) $record->vmid, $name);
        $client->wait_for_task($record->node, $upid, 300);
        if ($wasrunning) {
            $upid = $client->start_vm($record->node, (int) $record->vmid);
            $client->wait_for_task($record->node, $upid, 60);
        }
    }

    /**
     * Delete a snapshot.
     *
     * @param \stdClass $record
     * @param string $name
     */
    public static function snapshot_delete(\stdClass $record, string $name): void {
        if ($name === self::SNAPSHOT_INITIAL) {
            throw new \moodle_exception('errorcannotdeleteinitial', 'local_proxmoxvm');
        }
        $client = new client();
        $upid = $client->delete_snapshot($record->node, (int) $record->vmid, $name);
        $client->wait_for_task($record->node, $upid, 120);
    }

    /**
     * Create the protected initial snapshot once (idempotent). Taken while the
     * VM is stopped, just after cloning, for a clean factory-reset point.
     *
     * @param client $client
     * @param \stdClass $record
     */
    protected static function ensure_initial_snapshot(client $client, \stdClass $record): void {
        foreach ($client->list_snapshots($record->node, (int) $record->vmid) as $s) {
            if (($s['name'] ?? '') === self::SNAPSHOT_INITIAL) {
                return;
            }
        }
        $upid = $client->create_snapshot($record->node, (int) $record->vmid,
            self::SNAPSHOT_INITIAL, 'Initial state (auto, protected)');
        $client->wait_for_task($record->node, $upid, 120);
    }

    /**
     * Refresh cached status and IP for a record (used by the reconcile task).
     *
     * @param \stdClass $record
     */
    public static function refresh_status(\stdClass $record): void {
        global $DB;
        if (empty($record->vmid) || empty($record->node)) {
            return;
        }
        try {
            $client = new client();
            $status = $client->vm_current_status($record->node, (int) $record->vmid);
            $record->status = $status['status'] ?? null;
            if (($record->status ?? '') === 'running') {
                $ip = self::try_get_ip($client, $record->node, (int) $record->vmid, 1, 0);
                if ($ip !== null) {
                    $record->ipaddress = $ip;
                }
            }
            $record->statuschecked = time();
            $record->timemodified = time();
            $DB->update_record(self::TABLE, $record);
        } catch (\Throwable $ignore) {
            // Node temporarily unreachable: keep cached values.
            $ignore = null;
        }
    }

    // -- Helpers. -------------------------------------------------------------

    /**
     * Build a DNS-friendly, traceable VM name for a user.
     *
     * @param int $userid
     * @return string
     */
    protected static function build_vm_name(int $userid): string {
        global $DB;
        $username = (string) $DB->get_field('user', 'username', ['id' => $userid]);
        $clean = preg_replace('/[^a-z0-9]+/', '-', strtolower($username));
        $clean = trim($clean, '-');
        $name = 'moodle-' . $userid . ($clean !== '' ? '-' . $clean : '');
        return substr($name, 0, 63);
    }

    /**
     * Try to read the VM's first non-loopback IPv4 from the guest agent.
     *
     * @param client $client
     * @param string $node
     * @param int $vmid
     * @param int $attempts
     * @param int $sleep Seconds between attempts.
     * @return string|null
     */
    protected static function try_get_ip(client $client, string $node, int $vmid, int $attempts = 6, int $sleep = 5) {
        for ($i = 0; $i < $attempts; $i++) {
            try {
                $data = $client->agent_get_interfaces($node, $vmid);
                $result = $data['result'] ?? $data;
                if (is_array($result)) {
                    foreach ($result as $iface) {
                        if (($iface['name'] ?? '') === 'lo') {
                            continue;
                        }
                        foreach (($iface['ip-addresses'] ?? []) as $addr) {
                            if (($addr['ip-address-type'] ?? '') === 'ipv4') {
                                $ip = $addr['ip-address'] ?? '';
                                if ($ip !== '' && strpos($ip, '127.') !== 0) {
                                    return $ip;
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Guest agent not ready yet.
                $e = null;
            }
            if ($i < $attempts - 1 && $sleep > 0) {
                sleep($sleep);
            }
        }
        return null;
    }

    /**
     * Notify teachers/managers that the cluster is full (throttled to hourly).
     */
    protected static function notify_cluster_full(): void {
        $last = (int) get_config('local_proxmoxvm', 'lastfullnotice');
        if (time() - $last < HOURSECS) {
            return;
        }
        set_config('lastfullnotice', time(), 'local_proxmoxvm');

        $recipients = get_users_by_capability(\context_system::instance(), 'local/proxmoxvm:manage');
        foreach ($recipients as $user) {
            $message = new \core\message\message();
            $message->component = 'local_proxmoxvm';
            $message->name = 'clusterfull';
            $message->courseid = SITEID;
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $user;
            $message->subject = get_string('clusterfull_subject', 'local_proxmoxvm');
            $message->fullmessage = get_string('clusterfull_body', 'local_proxmoxvm');
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = text_to_html(get_string('clusterfull_body', 'local_proxmoxvm'));
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            message_send($message);
        }
    }
}
