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
 * Event observers.
 *
 * Provisioning is triggered by cohort membership; deletion by cohort
 * removal and, as a safety net, by user deletion.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\cohort_member_added',
        'callback'  => '\local_proxmoxvm\event\observer::cohort_member_added',
    ],
    [
        'eventname' => '\core\event\cohort_member_removed',
        'callback'  => '\local_proxmoxvm\event\observer::cohort_member_removed',
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback'  => '\local_proxmoxvm\event\observer::user_deleted',
    ],
];
