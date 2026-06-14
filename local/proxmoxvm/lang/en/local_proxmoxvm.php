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
 * English strings for local_proxmoxvm.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apihost'] = 'Proxmox host';
$string['apihost_desc'] = 'Hostname or IP of the Proxmox node that answers the API (e.g. the main node 172.29.255.1).';
$string['apiport'] = 'API port';
$string['autostart'] = 'Start VM after creation';
$string['autostart_desc'] = 'Power the VM on automatically once it has been cloned and configured.';
$string['clusterfull_body'] = 'A virtual machine could not be created because no Proxmox node currently has enough free memory. The request has been queued and will be retried automatically. Free up resources or add capacity.';
$string['clusterfull_subject'] = 'Proxmox cluster full: VM creation queued';
$string['cohorts'] = 'Provisioning cohorts';
$string['cohorts_desc'] = 'Users added to any of these cohorts get a VM automatically; removing them from all of these cohorts deletes their automatic VM.';
$string['cores'] = 'vCPU cores';
$string['cores_desc'] = 'Number of virtual CPU cores assigned to each VM.';
$string['enableconsole'] = 'Enable integrated console';
$string['enableconsole_desc'] = 'Allow users to open the noVNC console of their VM from within Moodle (requires the browser to reach the Proxmox host).';
$string['errorapi'] = 'Proxmox API error: {$a}';
$string['errorconnection'] = 'Could not connect to the Proxmox API: {$a}';
$string['errornocapacity'] = 'No Proxmox node currently has enough free memory to host a new VM.';
$string['errornotconfigured'] = 'The Proxmox VM plugin is not configured (host, token ID and secret are required).';
$string['errorsnapquota'] = 'Snapshot limit reached ({$a} maximum). Delete an existing snapshot first.';
$string['errortask'] = 'Proxmox task failed: {$a}';
$string['errortasktimeout'] = 'Timed out waiting for a Proxmox task to finish.';
$string['memory'] = 'RAM (MB)';
$string['memory_desc'] = 'Amount of RAM in megabytes assigned to each VM.';
$string['messageprovider:clusterfull'] = 'Proxmox cluster full notifications';
$string['nodemap'] = 'Nodes and templates';
$string['nodemap_desc'] = 'One line per node available for provisioning, in the form <code>node|templatevmid|storage</code> (an optional fourth field <code>|maxvms</code> is reserved). Lines starting with # are ignored. Example:<br><code>pve-node1|9000|local-lvm</code><br><code>pve-node2|9001|local-lvm</code>';
$string['pluginname'] = 'Proxmox VM';
$string['privacy:metadata:local_proxmoxvm'] = 'Information about the Proxmox VM provisioned for each user.';
$string['privacy:metadata:local_proxmoxvm:ipaddress'] = 'The last known IP address of the user\'s VM.';
$string['privacy:metadata:local_proxmoxvm:node'] = 'The Proxmox node hosting the user\'s VM.';
$string['privacy:metadata:local_proxmoxvm:timecreated'] = 'The time the VM record was created.';
$string['privacy:metadata:local_proxmoxvm:userid'] = 'The ID of the user who owns the VM.';
$string['privacy:metadata:local_proxmoxvm:vmid'] = 'The Proxmox VMID of the user\'s VM.';
$string['privacy:metadata:proxmox'] = 'To create and manage the VM, the username is used to name the VM on the Proxmox cluster.';
$string['privacy:metadata:proxmox:username'] = 'The Moodle username, used to build a traceable VM name.';
$string['proxmoxvm:console'] = 'Access the integrated console of your own VM';
$string['proxmoxvm:control'] = 'Power and snapshot your own VM';
$string['proxmoxvm:manage'] = 'Manage other users\' VMs and create extra VMs';
$string['proxmoxvm:view'] = 'View your own VM information';
$string['settings_connection'] = 'Proxmox connection';
$string['settings_connection_desc'] = 'Credentials used by Moodle to talk to the Proxmox API. Use a dedicated API token with a least-privilege role.';
$string['settings_console'] = 'Console';
$string['settings_provisioning'] = 'Provisioning';
$string['snapshotquota'] = 'Snapshot quota';
$string['snapshotquota_desc'] = 'Maximum number of snapshots a user may keep per VM (0 = unlimited).';
$string['task_deprovision'] = 'Delete a Proxmox VM';
$string['task_provision'] = 'Create a Proxmox VM';
$string['task_reconcile'] = 'Refresh Proxmox VM status';
$string['tokenid'] = 'API token ID';
$string['tokenid_desc'] = 'Full token identifier in the form <code>user@realm!tokenname</code> (e.g. <code>moodle@pve!moodle</code>).';
$string['tokensecret'] = 'API token secret';
$string['tokensecret_desc'] = 'The secret UUID shown once when the API token was created.';
$string['verifytls'] = 'Verify TLS certificate';
$string['verifytls_desc'] = 'Verify the Proxmox TLS certificate. Disable only if the cluster uses a self-signed certificate that Moodle cannot validate.';

// M2 - user dashboard & controls.
$string['action_reboot'] = 'Restart';
$string['action_start'] = 'Start';
$string['action_stop'] = 'Shut down';
$string['actionqueued'] = 'Command sent to the VM.';
$string['allvms'] = 'All virtual machines';
$string['col_actions'] = 'Actions';
$string['col_node'] = 'Node';
$string['col_origin'] = 'Origin';
$string['col_state'] = 'State';
$string['col_user'] = 'User';
$string['confirmdeletevm'] = 'Permanently delete the VM of {$a}? This destroys the virtual machine in Proxmox.';
$string['confirmrollback'] = 'Roll the VM back to snapshot "{$a}"? The VM will be stopped and restarted, and any change made since that snapshot will be lost.';
$string['confirmsnapshotdelete'] = 'Delete snapshot "{$a}"?';
$string['createextravm'] = 'Create an extra VM';
$string['createsnapshot'] = 'Create snapshot';
$string['deletesnapshot'] = 'Delete';
$string['deletevm'] = 'Delete VM';
$string['field_description'] = 'Description';
$string['field_ip'] = 'IP address';
$string['field_ssh'] = 'SSH';
$string['field_vmid'] = 'VM ID';
$string['ipunknown'] = 'Not available yet';
$string['managevms'] = 'Manage VMs';
$string['mymachine'] = 'My virtual machine';
$string['noeligibleusers'] = 'No users found in the provisioning cohorts.';
$string['nosnapshots'] = 'No snapshots.';
$string['novm'] = 'You do not have a virtual machine yet.';
$string['novmsyet'] = 'No virtual machine has been created yet.';
$string['origin_auto'] = 'Automatic (cohort)';
$string['origin_manual'] = 'Manual (teacher)';
$string['quotareached'] = 'Snapshot quota reached. Delete a snapshot to create a new one.';
$string['recordnotfound'] = 'Virtual machine not found, or it does not belong to you.';
$string['rollback'] = 'Restore';
$string['rollbackqueued'] = 'Restore started in the background. The VM will restart.';
$string['snapshotcreated'] = 'Snapshot created.';
$string['snapshotdeleted'] = 'Snapshot deleted.';
$string['snapshotname'] = 'Snapshot name';
$string['snapshots'] = 'Snapshots';
$string['sshuser'] = 'SSH username';
$string['sshuser_desc'] = 'Username shown in the "ssh user@ip" hint on the user page (leave empty to hide the hint).';
$string['state_deprovisioning'] = 'Deleting';
$string['state_error'] = 'Error';
$string['state_pending'] = 'Pending';
$string['state_provisioning'] = 'Creating';
$string['state_ready'] = 'Ready';
$string['status_running'] = 'Running';
$string['status_stopped'] = 'Stopped';
$string['status_unknown'] = 'Unknown';
$string['task_snapshotrollback'] = 'Roll back a VM snapshot';
$string['vmcreatequeued'] = 'VM creation requested.';
$string['vmdeletequeued'] = 'VM deletion requested.';
$string['vmerror'] = 'There was a problem creating your virtual machine. An administrator has been notified.';
$string['vmpending'] = 'Your virtual machine is being created. Please check back in a few minutes.';

// Initial snapshot / reset feature.
$string['confirmreset'] = 'Reset the VM to its initial state? All your work on this VM will be permanently lost.';
$string['errorcannotdeleteinitial'] = 'The initial snapshot is protected and cannot be deleted.';
$string['errorreservedname'] = 'This snapshot name is reserved. Please choose another one.';
$string['resetqueued'] = 'Reset started in the background. The VM will be restored and restarted.';
$string['resetvm'] = 'Reset the VM';
$string['resetvm_help'] = 'Restores the VM to its initial state (useful if it no longer boots). Your changes will be lost.';
