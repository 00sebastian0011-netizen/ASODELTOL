<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade steps for local_asodeltolstaff.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade local_asodeltolstaff.
 *
 * @param int $oldversion Installed plugin version.
 * @return bool
 */
function xmldb_local_asodeltolstaff_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026102201) {
        $table = new xmldb_table('local_asstaff_history');
        $field = new xmldb_field('subjectuserid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'actorid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $records = $DB->get_recordset('local_asstaff_history', ['subjectuserid' => null], 'id',
            'id, sessionid, entitytype, entityid');
        foreach ($records as $record) {
            $subjectuserid = null;
            if ($record->entitytype === 'attendance') {
                $subjectuserid = $DB->get_field('local_asstaff_attendance', 'studentid', ['id' => $record->entityid]);
            }
            if ($subjectuserid === false || $subjectuserid === null) {
                $subjectuserid = $DB->get_field('local_asstaff_session', 'trainerid', ['id' => $record->sessionid]);
            }
            if ($subjectuserid !== false && $subjectuserid !== null) {
                $DB->set_field('local_asstaff_history', 'subjectuserid', (int) $subjectuserid, ['id' => $record->id]);
            }
        }
        $records->close();

        $index = new xmldb_index('subjectuseridx', XMLDB_INDEX_NOTUNIQUE, ['subjectuserid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026102201, 'local', 'asodeltolstaff');
    }

    if ($oldversion < 2026102202) {
        upgrade_plugin_savepoint(true, 2026102202, 'local', 'asodeltolstaff');
    }

    if ($oldversion < 2026102400) {
        // Reports, calendar integration and help use existing native storage.
        // No schema, grades, roles or enrollment data are changed by this release.
        upgrade_plugin_savepoint(true, 2026102400, 'local', 'asodeltolstaff');
    }

    if ($oldversion < 2026102401) {
        // publishown is a new, ungranted-by-default capability. Existing content,
        // role assignments and academic rules remain untouched.
        upgrade_plugin_savepoint(true, 2026102401, 'local', 'asodeltolstaff');
    }

    if ($oldversion < 2026102402) {
        // New native notification providers. Custom fields and support recipient
        // require an explicit administrator action, never automatic role grants.
        upgrade_plugin_savepoint(true, 2026102402, 'local', 'asodeltolstaff');
    }

    if ($oldversion < 2026102504) {
        // Add the pending activity message provider; no role or course data changes.
        upgrade_plugin_savepoint(true, 2026102504, 'local', 'asodeltolstaff');
    }
    if ($oldversion < 2026102506) {
        $schema = new xmldb_file(__DIR__ . '/install.xml');
        if (!$schema->loadXMLStructure()) {
            throw new coding_exception('Support schema could not be loaded');
        }
        foreach (['local_asstaff_case', 'local_asstaff_caseevent'] as $name) {
            $table = $schema->getStructure()->getTable($name);
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }
        }
        upgrade_plugin_savepoint(true, 2026102506, 'local', 'asodeltolstaff');
    }
    if ($oldversion < 2026102700) {
        // Accounts are provisioned by staff. New manual users keep
        // policyagreed=0 and tool_policy requests the compulsory consent on
        // their first login, avoiding the duplicate pre-signup consent path.
        set_config('registerauth', '');
        upgrade_plugin_savepoint(true, 2026102700, 'local', 'asodeltolstaff');
    }
    if ($oldversion < 2026102801) {
        // Create SENA without assignments and clone Interventoria's effective
        // role definition. No users, enrolments, grades or exports are changed.
        $auditor = $DB->get_record('role', ['shortname' => 'asodeltol_auditor']);
        $sena = $DB->get_record('role', ['shortname' => 'sena']);
        if ($auditor && !$sena) {
            $senaid = create_role('SENA', 'sena', 'Supervision institucional de solo lectura');
            $caps = $DB->get_records('role_capabilities', ['roleid' => $auditor->id]);
            foreach ($caps as $cap) {
                assign_capability($cap->capability, (int) $cap->permission, $senaid,
                    (int) $cap->contextid, true);
            }
            $sena = $DB->get_record('role', ['id' => $senaid], '*', MUST_EXIST);
        }
        if ($auditor && $sena) {
            // Both supervision roles need the same read-only profile boundary.
            // These are role definitions only; neither role is assigned here.
            $readonlyallows = [
                'moodle/user:viewdetails',
                'moodle/user:viewhiddendetails',
                'moodle/course:viewparticipants',
                'moodle/site:accessallgroups',
                'moodle/grade:viewall',
                'local/asodeltolstaff:viewevidence',
            ];
            foreach ([$auditor->id, $sena->id] as $roleid) {
                foreach ($readonlyallows as $capability) {
                    assign_capability($capability, CAP_ALLOW, $roleid,
                        (int) context_system::instance()->id, true);
                }
            }
        }
        upgrade_plugin_savepoint(true, 2026102801, 'local', 'asodeltolstaff');
    }
    if ($oldversion < 2026102900) {
        // Do not use CAP_PROHIBIT for supervision roles: Moodle's prohibit
        // wins across a multi-role account and can suppress an administrator's
        // legitimate allow. Absence of an allow is the safe read-only default.
        $auditor = $DB->get_record('role', ['shortname' => 'asodeltol_auditor']);
        $sena = $DB->get_record('role', ['shortname' => 'sena']);
        $sensitive = [
            'moodle/user:update',
            'moodle/user:editprofile',
            'moodle/course:update',
            'moodle/course:manageactivities',
            'moodle/grade:edit',
            'moodle/grade:export',
            'local/asodeltolstaff:export',
        ];
        foreach ([$auditor, $sena] as $role) {
            if (!$role) {
                continue;
            }
            foreach ($sensitive as $capability) {
                unassign_capability($capability, $role->id,
                    (int) context_system::instance()->id, false);
            }
        }
        upgrade_plugin_savepoint(true, 2026102900, 'local', 'asodeltolstaff');
    }
    if ($oldversion < 2026102901) {
        // Move the profile gate from a late output hook to after_require_login.
        // No schema or user data is changed in this hotfix.
        upgrade_plugin_savepoint(true, 2026102901, 'local', 'asodeltolstaff');
    }
    return true;
}
