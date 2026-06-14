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

namespace local_proxmoxvm\proxmox;

defined('MOODLE_INTERNAL') || die();

/**
 * Thin REST client for the Proxmox VE API (token authentication).
 *
 * Compatible with PVE 6.4+. All async endpoints return a UPID string that
 * can be passed to {@see self::wait_for_task()}.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {

    /** @var string Base API URL, e.g. https://host:8006/api2/json. */
    protected $baseurl;

    /** @var string Authorization header value. */
    protected $authheader;

    /** @var bool Whether to verify the TLS certificate. */
    protected $verifytls;

    /**
     * Build a client from the plugin configuration.
     *
     * @throws \moodle_exception when the plugin is not configured.
     */
    public function __construct() {
        global $CFG;
        // The curl class lives in filelib.php, which is not auto-loaded on web
        // requests (only in CLI/cron). Ensure it is available.
        require_once($CFG->libdir . '/filelib.php');

        $host = trim((string) get_config('local_proxmoxvm', 'apihost'));
        $port = (int) get_config('local_proxmoxvm', 'apiport');
        $tokenid = trim((string) get_config('local_proxmoxvm', 'tokenid'));
        $secret = trim((string) get_config('local_proxmoxvm', 'tokensecret'));
        $this->verifytls = (bool) get_config('local_proxmoxvm', 'verifytls');

        if ($host === '' || $tokenid === '' || $secret === '') {
            throw new \moodle_exception('errornotconfigured', 'local_proxmoxvm');
        }
        if ($port <= 0) {
            $port = 8006;
        }

        $this->baseurl = "https://{$host}:{$port}/api2/json";
        // Proxmox token header: "PVEAPIToken=USER@REALM!TOKENID=SECRET".
        $this->authheader = "Authorization: PVEAPIToken={$tokenid}={$secret}";
    }

    /**
     * Perform an API call and return the decoded "data" payload.
     *
     * @param string $method GET|POST|PUT|DELETE
     * @param string $path API path beginning with a slash.
     * @param array $params Request parameters.
     * @return mixed Decoded data (array|string|null).
     * @throws \moodle_exception on transport or HTTP error.
     */
    protected function call(string $method, string $path, array $params = []) {
        // The Proxmox host is a trusted internal address (often RFC1918), so we
        // bypass Moodle's outgoing-request host blacklist for these calls only.
        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader([$this->authheader]);

        $options = [
            'CURLOPT_TIMEOUT'        => 60,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_SSL_VERIFYPEER' => $this->verifytls ? 1 : 0,
            'CURLOPT_SSL_VERIFYHOST' => $this->verifytls ? 2 : 0,
        ];

        $url = $this->baseurl . $path;
        $method = strtoupper($method);

        if ($method === 'GET' || $method === 'DELETE') {
            if (!empty($params)) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params, '', '&');
            }
            if ($method === 'GET') {
                $response = $curl->get($url, [], $options);
            } else {
                $options['CURLOPT_CUSTOMREQUEST'] = 'DELETE';
                $response = $curl->post($url, '', $options);
            }
        } else {
            // POST / PUT: x-www-form-urlencoded body.
            $body = http_build_query($params, '', '&');
            if ($method === 'PUT') {
                $options['CURLOPT_CUSTOMREQUEST'] = 'PUT';
            }
            $curl->setHeader(['Content-Type: application/x-www-form-urlencoded']);
            $response = $curl->post($url, $body, $options);
        }

        if ($curl->errno) {
            throw new \moodle_exception('errorconnection', 'local_proxmoxvm', '', $curl->error);
        }

        $info = $curl->get_info();
        $httpcode = isset($info['http_code']) ? (int) $info['http_code'] : 0;
        if ($httpcode < 200 || $httpcode >= 300) {
            $detail = $httpcode . ' ' . (is_string($response) ? $response : '');
            throw new \moodle_exception('errorapi', 'local_proxmoxvm', '', $detail);
        }

        $decoded = json_decode((string) $response, true);
        if ($decoded === null && trim((string) $response) !== '') {
            throw new \moodle_exception('errorapi', 'local_proxmoxvm', '', 'Invalid JSON response');
        }
        return $decoded['data'] ?? null;
    }

    // -- Cluster / nodes. -----------------------------------------------------

    /**
     * List cluster nodes with their status and memory usage.
     *
     * @return array
     */
    public function get_nodes(): array {
        return (array) $this->call('GET', '/nodes');
    }

    /**
     * Get the next free VMID in the cluster.
     *
     * @return int
     */
    public function get_next_vmid(): int {
        return (int) $this->call('GET', '/cluster/nextid');
    }

    // -- VM lifecycle. --------------------------------------------------------

    /**
     * Create a linked clone of a template.
     *
     * @param string $node Node hosting the template.
     * @param int $templateid Source template VMID.
     * @param int $newid New VMID.
     * @param string $name Name for the new VM.
     * @return string|null UPID of the async clone task.
     */
    public function clone_vm(string $node, int $templateid, int $newid, string $name) {
        return $this->call('POST', "/nodes/{$node}/qemu/{$templateid}/clone", [
            'newid' => $newid,
            'name'  => $name,
            'full'  => 0,
        ]);
    }

    /**
     * Set VM configuration options.
     *
     * @param string $node
     * @param int $vmid
     * @param array $config
     * @return mixed
     */
    public function set_config(string $node, int $vmid, array $config) {
        return $this->call('PUT', "/nodes/{$node}/qemu/{$vmid}/config", $config);
    }

    /**
     * Delete a VM (must be stopped). Purges from backup/replication jobs.
     *
     * @param string $node
     * @param int $vmid
     * @return string|null UPID.
     */
    public function delete_vm(string $node, int $vmid) {
        return $this->call('DELETE', "/nodes/{$node}/qemu/{$vmid}", ['purge' => 1]);
    }

    // -- Power. ---------------------------------------------------------------

    public function start_vm(string $node, int $vmid) {
        return $this->call('POST', "/nodes/{$node}/qemu/{$vmid}/status/start");
    }

    /** Graceful ACPI shutdown (uses guest agent when available). */
    public function shutdown_vm(string $node, int $vmid) {
        return $this->call('POST', "/nodes/{$node}/qemu/{$vmid}/status/shutdown");
    }

    /** Hard stop (pulls the plug). */
    public function stop_vm(string $node, int $vmid) {
        return $this->call('POST', "/nodes/{$node}/qemu/{$vmid}/status/stop");
    }

    public function reboot_vm(string $node, int $vmid) {
        return $this->call('POST', "/nodes/{$node}/qemu/{$vmid}/status/reboot");
    }

    /**
     * Current runtime status of a VM.
     *
     * @return array Includes 'status' (running|stopped), 'qmpstatus', 'uptime'...
     */
    public function vm_current_status(string $node, int $vmid): array {
        return (array) $this->call('GET', "/nodes/{$node}/qemu/{$vmid}/status/current");
    }

    /**
     * Query the guest agent for network interfaces (requires qemu-guest-agent).
     *
     * @return array
     */
    public function agent_get_interfaces(string $node, int $vmid): array {
        return (array) $this->call('GET', "/nodes/{$node}/qemu/{$vmid}/agent/network-get-interfaces");
    }

    /**
     * Open a VNC proxy session for a VM in websocket mode.
     *
     * Must be called on the node that hosts the VM.
     *
     * @param string $node
     * @param int $vmid
     * @return array Includes 'ticket' (also the RFB password) and 'port'.
     */
    public function vncproxy(string $node, int $vmid): array {
        return (array) $this->call('POST', "/nodes/{$node}/qemu/{$vmid}/vncproxy", [
            'websocket' => 1,
        ]);
    }

    // -- Snapshots. -----------------------------------------------------------

    /**
     * Create a snapshot without RAM state (vmstate = 0).
     *
     * @return string|null UPID.
     */
    public function create_snapshot(string $node, int $vmid, string $snapname, string $description = '') {
        return $this->call('POST', "/nodes/{$node}/qemu/{$vmid}/snapshot", [
            'snapname'    => $snapname,
            'vmstate'     => 0,
            'description' => $description,
        ]);
    }

    /**
     * List snapshots of a VM.
     *
     * @return array
     */
    public function list_snapshots(string $node, int $vmid): array {
        return (array) $this->call('GET', "/nodes/{$node}/qemu/{$vmid}/snapshot");
    }

    /**
     * Roll the VM back to a snapshot.
     *
     * @return string|null UPID.
     */
    public function rollback_snapshot(string $node, int $vmid, string $snapname) {
        return $this->call('POST', "/nodes/{$node}/qemu/{$vmid}/snapshot/{$snapname}/rollback");
    }

    /**
     * Delete a snapshot.
     *
     * @return string|null UPID.
     */
    public function delete_snapshot(string $node, int $vmid, string $snapname) {
        return $this->call('DELETE', "/nodes/{$node}/qemu/{$vmid}/snapshot/{$snapname}");
    }

    // -- Tasks. ---------------------------------------------------------------

    /**
     * Read the status of an async task.
     *
     * @return array
     */
    public function task_status(string $node, string $upid): array {
        return (array) $this->call('GET', "/nodes/{$node}/tasks/" . rawurlencode($upid) . "/status");
    }

    /**
     * Block until an async task finishes, or throw on failure/timeout.
     *
     * @param string $node
     * @param string $upid
     * @param int $timeout Seconds.
     * @return bool true on success.
     * @throws \moodle_exception
     */
    public function wait_for_task(string $node, string $upid, int $timeout = 180): bool {
        if (empty($upid) || !is_string($upid)) {
            return true;
        }
        $start = time();
        while ((time() - $start) < $timeout) {
            $status = $this->task_status($node, $upid);
            if (($status['status'] ?? '') === 'stopped') {
                $exit = $status['exitstatus'] ?? '';
                if ($exit !== '' && $exit !== 'OK') {
                    throw new \moodle_exception('errortask', 'local_proxmoxvm', '', $exit);
                }
                return true;
            }
            sleep(2);
        }
        throw new \moodle_exception('errortasktimeout', 'local_proxmoxvm');
    }
}
