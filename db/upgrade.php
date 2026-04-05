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
 * @Author Bastian Coquedano
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

    if ($oldversion < 2026040400) {
        $cachetable = new xmldb_table('block_student_engagement_cache');

        $atriskcount = new xmldb_field('at_risk_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($cachetable, $atriskcount)) {
            $dbman->add_field($cachetable, $atriskcount);
        }

        $criticalriskcount = new xmldb_field('critical_risk_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($cachetable, $criticalriskcount)) {
            $dbman->add_field($cachetable, $criticalriskcount);
        }

        $averagecompletion = new xmldb_field('average_completion_percent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($cachetable, $averagecompletion)) {
            $dbman->add_field($cachetable, $averagecompletion);
        }

        $risklastcalculated = new xmldb_field('risk_last_calculated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($cachetable, $risklastcalculated)) {
            $dbman->add_field($cachetable, $risklastcalculated);
        }

        $risktable = new xmldb_table('block_student_engagement_risk');
        $risktable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $risktable->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $risktable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $risktable->add_field('current_grade', XMLDB_TYPE_NUMBER, '10,5', null, null, null, null);
        $risktable->add_field('pass_grade', XMLDB_TYPE_NUMBER, '10,5', null, null, null, null);
        $risktable->add_field('grade_gap', XMLDB_TYPE_NUMBER, '10,5', null, null, null, null);
        $risktable->add_field('completion_percent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $risktable->add_field('days_inactive', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $risktable->add_field('recent_events', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $risktable->add_field('attendance_percent', XMLDB_TYPE_NUMBER, '10,5', null, null, null, null);
        $risktable->add_field('engagement_score', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $risktable->add_field('risk_score', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $risktable->add_field('risk_level', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $risktable->add_field('risk_flags', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $risktable->add_field('last_calculated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $risktable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        if (!$dbman->table_exists($risktable)) {
            $dbman->create_table($risktable);
        }

        $courseuserindex = new xmldb_index('course_user_uix', XMLDB_INDEX_UNIQUE, ['courseid', 'userid']);
        if (!$dbman->index_exists($risktable, $courseuserindex)) {
            $dbman->add_index($risktable, $courseuserindex);
        }

        $courserisklevelindex = new xmldb_index('course_risk_level_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'risk_level']);
        if (!$dbman->index_exists($risktable, $courserisklevelindex)) {
            $dbman->add_index($risktable, $courserisklevelindex);
        }

        $courseriskscoreindex = new xmldb_index('course_risk_score_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'risk_score']);
        if (!$dbman->index_exists($risktable, $courseriskscoreindex)) {
            $dbman->add_index($risktable, $courseriskscoreindex);
        }

        $risklastcalcindex = new xmldb_index('last_calculated_ix', XMLDB_INDEX_NOTUNIQUE, ['last_calculated']);
        if (!$dbman->index_exists($risktable, $risklastcalcindex)) {
            $dbman->add_index($risktable, $risklastcalcindex);
        }

        upgrade_block_savepoint(true, 2026040400, 'student_engagement');
    }

    return true;
}
