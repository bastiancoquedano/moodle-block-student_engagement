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
 * Privacy provider for block_student_engagement.
 * @Author Bastian Coquedano
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_student_engagement\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider implementation for student engagement data.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    plugin_provider,
    \core_privacy\local\request\core_userlist_provider {

    /** @var string */
    private const CACHE_TABLE = 'block_student_engagement_cache';

    /** @var string */
    private const RISK_TABLE = 'block_student_engagement_risk';

    /**
     * Describe stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::RISK_TABLE, [
            'courseid' => 'privacy:metadata:block_student_engagement_risk:courseid',
            'userid' => 'privacy:metadata:block_student_engagement_risk:userid',
            'current_grade' => 'privacy:metadata:block_student_engagement_risk:current_grade',
            'pass_grade' => 'privacy:metadata:block_student_engagement_risk:pass_grade',
            'grade_gap' => 'privacy:metadata:block_student_engagement_risk:grade_gap',
            'completion_percent' => 'privacy:metadata:block_student_engagement_risk:completion_percent',
            'days_inactive' => 'privacy:metadata:block_student_engagement_risk:days_inactive',
            'recent_events' => 'privacy:metadata:block_student_engagement_risk:recent_events',
            'attendance_percent' => 'privacy:metadata:block_student_engagement_risk:attendance_percent',
            'engagement_score' => 'privacy:metadata:block_student_engagement_risk:engagement_score',
            'risk_score' => 'privacy:metadata:block_student_engagement_risk:risk_score',
            'risk_level' => 'privacy:metadata:block_student_engagement_risk:risk_level',
            'risk_flags' => 'privacy:metadata:block_student_engagement_risk:risk_flags',
            'last_calculated' => 'privacy:metadata:block_student_engagement_risk:last_calculated',
        ], 'privacy:metadata:block_student_engagement_risk');

        $collection->add_database_table(self::CACHE_TABLE, [
            'courseid' => 'privacy:metadata:block_student_engagement_cache:courseid',
            'most_active_userid' => 'privacy:metadata:block_student_engagement_cache:most_active_userid',
            'inactive_userids' => 'privacy:metadata:block_student_engagement_cache:inactive_userids',
            'last_calculated' => 'privacy:metadata:block_student_engagement_cache:last_calculated',
            'risk_last_calculated' => 'privacy:metadata:block_student_engagement_cache:risk_last_calculated',
        ], 'privacy:metadata:block_student_engagement_cache');

        return $collection;
    }

    /**
     * Get contexts that hold data for a specific user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if ($userid <= 0) {
            return $contextlist;
        }

        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
               JOIN {" . self::RISK_TABLE . "} r
                 ON r.courseid = ctx.instanceid
              WHERE ctx.contextlevel = :contextlevel
                AND r.userid = :userid",
            [
                'contextlevel' => CONTEXT_COURSE,
                'userid' => $userid,
            ]
        );

        $cachecourses = [];
        $bymostactive = $DB->get_records(self::CACHE_TABLE, ['most_active_userid' => $userid], '', 'courseid');
        foreach ($bymostactive as $record) {
            $cachecourses[(int)$record->courseid] = true;
        }

        $withinactive = $DB->get_records_select(self::CACHE_TABLE, 'inactive_userids IS NOT NULL AND inactive_userids <> ?', ['']);
        foreach ($withinactive as $record) {
            $inactiveids = self::decode_inactive_userids((string)$record->inactive_userids);
            if (in_array($userid, $inactiveids, true)) {
                $cachecourses[(int)$record->courseid] = true;
            }
        }

        if (!empty($cachecourses)) {
            list($insql, $params) = $DB->get_in_or_equal(array_keys($cachecourses), SQL_PARAMS_NAMED);
            $params['contextlevel'] = CONTEXT_COURSE;
            $contextlist->add_from_sql(
                "SELECT id
                   FROM {context}
                  WHERE contextlevel = :contextlevel
                    AND instanceid {$insql}",
                $params
            );
        }

        return $contextlist;
    }

    /**
     * Collect users with data in a specific context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!($context instanceof context_course)) {
            return;
        }

        $courseid = (int)$context->instanceid;
        if ($courseid <= 0) {
            return;
        }

        $riskrecords = $DB->get_records(self::RISK_TABLE, ['courseid' => $courseid], '', 'userid');
        foreach ($riskrecords as $record) {
            $userid = (int)$record->userid;
            if ($userid > 0) {
                $userlist->add_user($userid);
            }
        }

        $cache = $DB->get_record(self::CACHE_TABLE, ['courseid' => $courseid], 'most_active_userid,inactive_userids', IGNORE_MISSING);
        if (!$cache) {
            return;
        }

        if (!empty($cache->most_active_userid)) {
            $userlist->add_user((int)$cache->most_active_userid);
        }

        foreach (self::decode_inactive_userids((string)$cache->inactive_userids) as $userid) {
            $userlist->add_user($userid);
        }
    }

    /**
     * Export user data for approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        if ($userid <= 0) {
            return;
        }

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof context_course)) {
                continue;
            }

            $courseid = (int)$context->instanceid;
            if ($courseid <= 0) {
                continue;
            }

            $risk = $DB->get_record(
                self::RISK_TABLE,
                ['courseid' => $courseid, 'userid' => $userid],
                'current_grade,pass_grade,grade_gap,completion_percent,days_inactive,recent_events,attendance_percent,engagement_score,' .
                    'risk_score,risk_level,risk_flags,last_calculated',
                IGNORE_MISSING
            );
            if ($risk) {
                $risk->risk_flags = self::decode_flags((string)$risk->risk_flags);
                writer::with_context($context)->export_data(
                    [get_string('privacy:export:risk', 'block_student_engagement')],
                    $risk
                );
            }

            $cache = $DB->get_record(
                self::CACHE_TABLE,
                ['courseid' => $courseid],
                'most_active_userid,inactive_userids,last_calculated,risk_last_calculated',
                IGNORE_MISSING
            );

            if ($cache) {
                $cachedata = new \stdClass();
                $cachedata->is_most_active_student = ((int)$cache->most_active_userid === $userid);
                $cachedata->is_inactive_student = in_array($userid, self::decode_inactive_userids((string)$cache->inactive_userids), true);
                $cachedata->cache_last_calculated = (int)$cache->last_calculated;
                $cachedata->risk_cache_last_calculated = (int)$cache->risk_last_calculated;

                if ($cachedata->is_most_active_student || $cachedata->is_inactive_student) {
                    writer::with_context($context)->export_data(
                        [get_string('privacy:export:cache_references', 'block_student_engagement')],
                        $cachedata
                    );
                }
            }
        }
    }

    /**
     * Delete all plugin data in a context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!($context instanceof context_course)) {
            return;
        }

        $courseid = (int)$context->instanceid;
        if ($courseid <= 0) {
            return;
        }

        $DB->delete_records(self::RISK_TABLE, ['courseid' => $courseid]);
        $DB->delete_records(self::CACHE_TABLE, ['courseid' => $courseid]);
    }

    /**
     * Delete user data from approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        if ($userid <= 0) {
            return;
        }

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof context_course)) {
                continue;
            }

            $courseid = (int)$context->instanceid;
            if ($courseid <= 0) {
                continue;
            }

            $DB->delete_records(self::RISK_TABLE, ['courseid' => $courseid, 'userid' => $userid]);
            self::remove_user_from_cache_record($courseid, [$userid]);
        }
    }

    /**
     * Delete data for a list of users in a single context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!($context instanceof context_course)) {
            return;
        }

        $courseid = (int)$context->instanceid;
        $userids = array_values(array_filter(array_map('intval', $userlist->get_userids())));
        if ($courseid <= 0 || empty($userids)) {
            return;
        }

        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;
        $DB->delete_records_select(self::RISK_TABLE, "courseid = :courseid AND userid {$insql}", $params);
        self::remove_user_from_cache_record($courseid, $userids);
    }

    /**
     * Remove user references from the course cache record.
     *
     * @param int $courseid
     * @param array<int> $userids
     * @return void
     */
    private static function remove_user_from_cache_record(int $courseid, array $userids): void {
        global $DB;

        if ($courseid <= 0 || empty($userids)) {
            return;
        }

        $cache = $DB->get_record(self::CACHE_TABLE, ['courseid' => $courseid], '*', IGNORE_MISSING);
        if (!$cache) {
            return;
        }

        $userids = array_flip(array_values(array_unique(array_map('intval', $userids))));
        $changed = false;

        if (!empty($cache->most_active_userid) && isset($userids[(int)$cache->most_active_userid])) {
            $cache->most_active_userid = 0;
            $changed = true;
        }

        $inactiveids = self::decode_inactive_userids((string)$cache->inactive_userids);
        $filteredinactive = [];
        foreach ($inactiveids as $userid) {
            if (!isset($userids[$userid])) {
                $filteredinactive[] = $userid;
            }
        }
        if ($filteredinactive !== $inactiveids) {
            $cache->inactive_userids = empty($filteredinactive) ? null : json_encode(array_values($filteredinactive));
            $changed = true;
        }

        if ($changed) {
            $DB->update_record(self::CACHE_TABLE, $cache);
        }
    }

    /**
     * Decode inactive user IDs from storage.
     *
     * @param string $raw
     * @return array<int>
     */
    private static function decode_inactive_userids(string $raw): array {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            // Backward-compatibility for legacy non-JSON payloads.
            if (!preg_match_all('/\d+/', $raw, $matches) || empty($matches[0])) {
                return [];
            }
            $decoded = $matches[0];
        }

        $userids = [];
        foreach ($decoded as $value) {
            $userid = (int)$value;
            if ($userid > 0) {
                $userids[$userid] = $userid;
            }
        }

        return array_values($userids);
    }

    /**
     * Decode risk flags from JSON storage.
     *
     * @param string $raw
     * @return array<int,string>
     */
    private static function decode_flags(string $raw): array {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_map('strval', $decoded));
    }
}
