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
 * Admin settings.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    global $DB;

    $settings = new admin_settingpage('local_proxmoxvm', get_string('pluginname', 'local_proxmoxvm'));
    $ADMIN->add('localplugins', $settings);

    if (!during_initial_install()) {
        // -- Connection. ------------------------------------------------------
        $settings->add(new admin_setting_heading(
            'local_proxmoxvm/hdr_conn',
            get_string('settings_connection', 'local_proxmoxvm'),
            get_string('settings_connection_desc', 'local_proxmoxvm')
        ));
        $settings->add(new admin_setting_configtext(
            'local_proxmoxvm/apihost',
            get_string('apihost', 'local_proxmoxvm'),
            get_string('apihost_desc', 'local_proxmoxvm'),
            '172.29.255.1',
            PARAM_HOST
        ));
        $settings->add(new admin_setting_configtext(
            'local_proxmoxvm/apiport',
            get_string('apiport', 'local_proxmoxvm'),
            '',
            '8006',
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'local_proxmoxvm/tokenid',
            get_string('tokenid', 'local_proxmoxvm'),
            get_string('tokenid_desc', 'local_proxmoxvm'),
            '',
            PARAM_RAW_TRIMMED
        ));
        $settings->add(new admin_setting_configpasswordunmask(
            'local_proxmoxvm/tokensecret',
            get_string('tokensecret', 'local_proxmoxvm'),
            get_string('tokensecret_desc', 'local_proxmoxvm'),
            ''
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_proxmoxvm/verifytls',
            get_string('verifytls', 'local_proxmoxvm'),
            get_string('verifytls_desc', 'local_proxmoxvm'),
            0
        ));

        // -- Provisioning. ----------------------------------------------------
        $settings->add(new admin_setting_heading(
            'local_proxmoxvm/hdr_prov',
            get_string('settings_provisioning', 'local_proxmoxvm'),
            ''
        ));
        $settings->add(new admin_setting_configtextarea(
            'local_proxmoxvm/nodemap',
            get_string('nodemap', 'local_proxmoxvm'),
            get_string('nodemap_desc', 'local_proxmoxvm'),
            '',
            PARAM_RAW
        ));
        $settings->add(new admin_setting_configtext(
            'local_proxmoxvm/cores',
            get_string('cores', 'local_proxmoxvm'),
            get_string('cores_desc', 'local_proxmoxvm'),
            '1',
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'local_proxmoxvm/memory',
            get_string('memory', 'local_proxmoxvm'),
            get_string('memory_desc', 'local_proxmoxvm'),
            '1024',
            PARAM_INT
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_proxmoxvm/autostart',
            get_string('autostart', 'local_proxmoxvm'),
            get_string('autostart_desc', 'local_proxmoxvm'),
            1
        ));
        $settings->add(new admin_setting_configtext(
            'local_proxmoxvm/snapshotquota',
            get_string('snapshotquota', 'local_proxmoxvm'),
            get_string('snapshotquota_desc', 'local_proxmoxvm'),
            '3',
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_proxmoxvm/sshuser',
            get_string('sshuser', 'local_proxmoxvm'),
            get_string('sshuser_desc', 'local_proxmoxvm'),
            '',
            PARAM_ALPHANUMEXT
        ));

        // Cohorts that trigger automatic provisioning.
        $cohortoptions = [];
        try {
            $cohorts = $DB->get_records('cohort', null, 'name ASC', 'id, name, idnumber');
            foreach ($cohorts as $c) {
                $label = format_string($c->name);
                if ($c->idnumber !== '' && $c->idnumber !== null) {
                    $label .= ' [' . $c->idnumber . ']';
                }
                $cohortoptions[$c->id] = $label;
            }
        } catch (\Throwable $e) {
            $cohortoptions = [];
        }
        $settings->add(new admin_setting_configmulticheckbox(
            'local_proxmoxvm/cohorts',
            get_string('cohorts', 'local_proxmoxvm'),
            get_string('cohorts_desc', 'local_proxmoxvm'),
            [],
            $cohortoptions
        ));

        // -- Console. ---------------------------------------------------------
        $settings->add(new admin_setting_heading(
            'local_proxmoxvm/hdr_console',
            get_string('settings_console', 'local_proxmoxvm'),
            ''
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_proxmoxvm/enableconsole',
            get_string('enableconsole', 'local_proxmoxvm'),
            get_string('enableconsole_desc', 'local_proxmoxvm'),
            1
        ));
    }
}
