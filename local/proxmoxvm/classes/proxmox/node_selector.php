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
 * Chooses the least-loaded node (by free RAM) that hosts a template.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class node_selector {

    /** @var client */
    protected $client;

    /**
     * @param client $client
     */
    public function __construct(client $client) {
        $this->client = $client;
    }

    /**
     * Parse the "nodemap" setting.
     *
     * Each non-empty, non-comment line has the form:
     *   node|templatevmid|storage[|maxvms]
     *
     * @return array Array of stdClass{node, templateid, storage, maxvms}.
     */
    public static function get_node_map(): array {
        $raw = (string) get_config('local_proxmoxvm', 'nodemap');
        $map = [];
        foreach (preg_split('/\R/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }
            $map[] = (object) [
                'node'       => $parts[0],
                'templateid' => (int) $parts[1],
                'storage'    => $parts[2] ?? '',
                'maxvms'     => isset($parts[3]) ? (int) $parts[3] : 0,
            ];
        }
        return $map;
    }

    /**
     * Pick the configured node with the most free RAM able to host the VM.
     *
     * @param int $memorymb RAM (MB) the new VM will need.
     * @return \stdClass|null {node, templateid, storage} or null if none fits.
     */
    public function pick(int $memorymb) {
        $map = self::get_node_map();
        if (empty($map)) {
            return null;
        }

        $byname = [];
        foreach ($this->client->get_nodes() as $n) {
            if (!empty($n['node'])) {
                $byname[$n['node']] = $n;
            }
        }

        $needed = $memorymb * 1024 * 1024; // Bytes.
        $best = null;
        $bestfree = -1;

        foreach ($map as $entry) {
            $info = $byname[$entry->node] ?? null;
            if ($info === null || ($info['status'] ?? '') !== 'online') {
                continue;
            }
            $free = (int) ($info['maxmem'] ?? 0) - (int) ($info['mem'] ?? 0);
            if ($free < $needed) {
                continue;
            }
            if ($free > $bestfree) {
                $bestfree = $free;
                $best = (object) [
                    'node'       => $entry->node,
                    'templateid' => $entry->templateid,
                    'storage'    => $entry->storage,
                ];
            }
        }

        return $best;
    }
}
