<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade steps for block_student_engagement.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute block_student_engagement upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_student_engagement_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026031000) {
        $table = new xmldb_table('block_student_engagement_cache');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('active_students', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('inactive_students', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('most_active_userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('most_active_interactions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Stores a JSON array of inactive user IDs (e.g. [12,34,56]).
        $table->add_field('inactive_userids', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('last_calculated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Enforce one cache record per course and support ordering/filtering by recency.
        $courseindex = new xmldb_index('courseid_uix', XMLDB_INDEX_UNIQUE, ['courseid']);
        if (!$dbman->index_exists($table, $courseindex)) {
            $dbman->add_index($table, $courseindex);
        }

        $lastcalcindex = new xmldb_index('last_calculated_ix', XMLDB_INDEX_NOTUNIQUE, ['last_calculated']);
        if (!$dbman->index_exists($table, $lastcalcindex)) {
            $dbman->add_index($table, $lastcalcindex);
        }

        upgrade_block_savepoint(true, 2026031000, 'student_engagement');
    }

    return true;
}

