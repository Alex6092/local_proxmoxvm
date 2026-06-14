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

defined('MOODLE_INTERNAL') || die();

/**
 * Connection / capacity diagnostic, shared by the CLI and web entry points.
 *
 * Uses the exact same client and code path as provisioning, so its result
 * reflects what Moodle really sees (unlike an external curl with a different
 * TLS stack).
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diagnostics {

    /**
     * Run the diagnostic and return a list of human-readable lines.
     *
     * Never throws; failures are reported as lines.
     *
     * @return string[]
     */
    public static function run(): array {
        $out = [];

        $host = (string) get_config('local_proxmoxvm', 'apihost');
        $port = (int) get_config('local_proxmoxvm', 'apiport');
        $tokenid = (string) get_config('local_proxmoxvm', 'tokenid');
        $verifytls = (bool) get_config('local_proxmoxvm', 'verifytls');
        $memory = max(64, (int) get_config('local_proxmoxvm', 'memory'));

        $out[] = "Hote        : {$host}:{$port}";
        $out[] = "Token ID    : {$tokenid}";
        $out[] = 'Verif TLS   : ' . ($verifytls ? 'oui' : 'non');
        $out[] = "RAM par VM  : {$memory} Mo";
        $out[] = '';

        // Parsed node map.
        $map = node_selector::get_node_map();
        $out[] = 'Mapping configure (noeud | template | storage) :';
        if (empty($map)) {
            $out[] = '  (VIDE ou non parse - verifie le reglage "Noeuds et templates")';
        } else {
            foreach ($map as $m) {
                $out[] = "  - {$m->node} | template {$m->templateid} | {$m->storage}";
            }
        }
        $out[] = '';

        // Build the client.
        try {
            $client = new client();
        } catch (\Throwable $e) {
            $out[] = 'Plugin non configure : ' . $e->getMessage();
            return $out;
        }

        // Call GET /nodes exactly like provisioning does.
        $out[] = 'Appel de GET /nodes ...';
        $t0 = microtime(true);
        try {
            $nodes = $client->get_nodes();
        } catch (\Throwable $e) {
            $elapsed = round(microtime(true) - $t0, 1);
            $out[] = "  ECHEC apres {$elapsed} s : " . $e->getMessage();
            $out[] = '';
            $out[] = '=> Connexion/auth en echec cote Moodle. Verifie hote, port, jeton,';
            $out[] = '   et le reseau entre le serveur Moodle et le cluster Proxmox.';
            return $out;
        }
        $elapsed = round(microtime(true) - $t0, 1);
        $out[] = "  OK en {$elapsed} s.";
        $out[] = '';

        if (empty($nodes)) {
            $out[] = 'Liste de noeuds VIDE.';
            $out[] = '=> Le jeton s\'authentifie mais ne voit AUCUN noeud : probleme de droits.';
            $out[] = '   - separation de privileges du jeton activee sans ACL dediee, ou';
            $out[] = '   - role/ACL ne couvrant pas /nodes (Sys.Audit manquant).';
            return $out;
        }

        // List nodes seen.
        $byname = [];
        $out[] = 'Noeuds vus par le jeton :';
        foreach ($nodes as $n) {
            $name = $n['node'] ?? '?';
            $status = $n['status'] ?? '?';
            $maxmem = (int) ($n['maxmem'] ?? 0);
            $mem = (int) ($n['mem'] ?? 0);
            $freemb = (int) (($maxmem - $mem) / 1048576);
            $maxmb = (int) ($maxmem / 1048576);
            $out[] = "  - {$name} | {$status} | RAM libre ~{$freemb} Mo / {$maxmb} Mo";
            $byname[$name] = $n;
        }

        // Detect the "stats not visible" case (all memory reported as 0).
        $allzeromem = true;
        foreach ($byname as $n) {
            if ((int) ($n['maxmem'] ?? 0) > 0) {
                $allzeromem = false;
                break;
            }
        }
        if ($allzeromem) {
            $out[] = '';
            $out[] = '!! RAM a 0 sur TOUS les noeuds = statistiques non lisibles par le jeton.';
            $out[] = '   => Il manque le privilege Sys.Audit au jeton (role restreint ou';
            $out[] = '      separation de privileges sans ACL dediee au jeton).';
        }
        $out[] = '';

        // Cross-check the map against reality.
        $out[] = 'Verification mapping vs noeuds reels :';
        $eligible = 0;
        foreach ($map as $m) {
            if (!isset($byname[$m->node])) {
                $out[] = "  [X] '{$m->node}' : INTROUVABLE dans la liste (nom de noeud incorrect ?)";
                continue;
            }
            $n = $byname[$m->node];
            $status = $n['status'] ?? '?';
            $freemb = (int) (((int) ($n['maxmem'] ?? 0) - (int) ($n['mem'] ?? 0)) / 1048576);
            if ($status !== 'online') {
                $out[] = "  [X] '{$m->node}' : statut '{$status}' (attendu 'online')";
            } else if ($freemb < $memory) {
                $out[] = "  [X] '{$m->node}' : ~{$freemb} Mo libres < {$memory} Mo requis";
            } else {
                $out[] = "  [OK] '{$m->node}' : eligible (~{$freemb} Mo libres)";
                $eligible++;
            }
        }
        $out[] = '';
        $out[] = $eligible > 0
            ? "=> {$eligible} noeud(s) eligible(s). Le provisionnement devrait fonctionner."
            : '=> Aucun noeud eligible : c\'est la cause du message "pas de capacite".';

        return $out;
    }
}
