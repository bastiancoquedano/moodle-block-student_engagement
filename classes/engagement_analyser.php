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
 * Engagement analyser for Student Engagement block.
 *
 * This class is responsible for calculating engagement metrics using Moodle
 * internal tables (especially logstore_standard_log). It is intentionally
 * UI-agnostic so it can be reused by scheduled tasks and by the block.
 *
 * Important: keep queries parameterised and apply early filters to make
 * logstore queries feasible on large datasets.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_student_engagement;

defined('MOODLE_INTERNAL') || die();

/**
 * Domain service for engagement calculations.
 */
class engagement_analyser {

    /** @var string Event name to exclude from interaction counts (noise). */
    private const EVENT_COURSE_VIEWED = '\\core\\event\\course_viewed';

    /** @var string The shortname used to identify the student role. */
    private const STUDENT_ROLE_SHORTNAME = 'student';

    /**
     * Get all user IDs with role 'student' assigned in the course context.
     *
     * @param int $courseid
     * @return int[]
     */
    public static function get_student_userids(int $courseid): array {
        global $DB;

        $courseid = (int)$courseid;
        if ($courseid <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT ra.userid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ctx.contextlevel = :contextcourse
                   AND ctx.instanceid = :courseid
                   AND r.shortname = :studentshortname";
        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'courseid' => $courseid,
            'studentshortname' => self::STUDENT_ROLE_SHORTNAME,
        ];

        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    /**
     * Get user IDs of students with activity in the last N days.
     *
     * Activity is based on logstore_standard_log events (excluding course_viewed).
     *
     * @param int $courseid
     * @param int|null $days If null, uses active_days_threshold setting.
     * @return int[]
     */
    public static function get_active_students(int $courseid, ?int $days = null): array {
        global $DB;

        $courseid = (int)$courseid;
        if ($courseid <= 0) {
            return [];
        }

        $days = $days ?? self::active_days_threshold();
        $since = self::since_days($days);

        // Join against role assignments in the course context to avoid large IN() lists
        // and to ensure we only count students.
        $sql = "SELECT DISTINCT l.userid
                  FROM {logstore_standard_log} l
                  JOIN {context} ctx ON ctx.contextlevel = :contextcourse
                                   AND ctx.instanceid = :ctxcourseid
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                                            AND ra.userid = l.userid
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE l.courseid = :courseid
                   AND l.userid > 0
                   AND l.timecreated >= :since
                   AND l.eventname <> :courseviewed
                   AND r.shortname = :studentshortname";
        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'ctxcourseid' => $courseid,
            'courseid' => $courseid,
            'since' => $since,
            'courseviewed' => self::EVENT_COURSE_VIEWED,
            'studentshortname' => self::STUDENT_ROLE_SHORTNAME,
        ];

        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    /**
     * Get user IDs of students with no activity in the last N days.
     *
     * @param int $courseid
     * @param int|null $days If null, uses inactive_days_threshold setting.
     * @return int[]
     */
    public static function get_inactive_students(int $courseid, ?int $days = null): array {
        $courseid = (int)$courseid;
        if ($courseid <= 0) {
            return [];
        }

        $days = $days ?? self::inactive_days_threshold();

        $students = self::get_student_userids($courseid);
        if (empty($students)) {
            return [];
        }

        $active = self::get_active_students($courseid, $days);
        if (empty($active)) {
            return $students;
        }

        $inactive = array_values(array_diff($students, $active));
        return array_map('intval', $inactive);
    }

    /**
     * Get the most active student in the last N days.
     *
     * @param int $courseid
     * @param int|null $days If null, uses active_days_threshold setting.
     * @return \stdClass|null Object with userid and interactions.
     */
    public static function get_most_active_student(int $courseid, ?int $days = null): ?\stdClass {
        global $DB;

        $courseid = (int)$courseid;
        if ($courseid <= 0) {
            return null;
        }

        $days = $days ?? self::active_days_threshold();
        $since = self::since_days($days);

        $sql = "SELECT l.userid, COUNT(1) AS interactions
                  FROM {logstore_standard_log} l
                  JOIN {context} ctx ON ctx.contextlevel = :contextcourse
                                   AND ctx.instanceid = :ctxcourseid
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                                            AND ra.userid = l.userid
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE l.courseid = :courseid
                   AND l.userid > 0
                   AND l.timecreated >= :since
                   AND l.eventname <> :courseviewed
                   AND r.shortname = :studentshortname
              GROUP BY l.userid
              ORDER BY interactions DESC";
        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'ctxcourseid' => $courseid,
            'courseid' => $courseid,
            'since' => $since,
            'courseviewed' => self::EVENT_COURSE_VIEWED,
            'studentshortname' => self::STUDENT_ROLE_SHORTNAME,
        ];

        $records = $DB->get_records_sql($sql, $params, 0, 1);
        if (empty($records)) {
            return null;
        }
        $record = reset($records);

        $result = new \stdClass();
        $result->userid = (int)$record->userid;
        $result->interactions = (int)$record->interactions;
        return $result;
    }

    /**
     * Calculate engagement score for a user in a course.
     *
     * v1 formula: score = events + (completed * 5)
     *
     * @param int $userid
     * @param int $courseid
     * @param int|null $days If null, uses inactive_days_threshold setting.
     * @return int
     */
    public static function get_engagement_score(int $userid, int $courseid, ?int $days = null): int {
        global $DB;

        $userid = (int)$userid;
        $courseid = (int)$courseid;
        if ($userid <= 0 || $courseid <= 0) {
            return 0;
        }

        $days = $days ?? self::inactive_days_threshold();
        $since = self::since_days($days);

        $eventssql = "SELECT COUNT(1)
                        FROM {logstore_standard_log} l
                       WHERE l.courseid = :courseid
                         AND l.userid = :userid
                         AND l.userid > 0
                         AND l.timecreated >= :since
                         AND l.eventname <> :courseviewed";
        $eventsparams = [
            'courseid' => $courseid,
            'userid' => $userid,
            'since' => $since,
            'courseviewed' => self::EVENT_COURSE_VIEWED,
        ];
        $events = (int)$DB->get_field_sql($eventssql, $eventsparams);

        $completedsql = "SELECT COUNT(1)
                           FROM {course_modules_completion} c
                           JOIN {course_modules} cm ON cm.id = c.coursemoduleid
                          WHERE cm.course = :courseid
                            AND c.userid = :userid
                            AND c.completionstate <> 0
                            AND c.timemodified >= :since";
        $completedparams = [
            'courseid' => $courseid,
            'userid' => $userid,
            'since' => $since,
        ];
        $completed = (int)$DB->get_field_sql($completedsql, $completedparams);

        return $events + ($completed * 5);
    }

    /**
     * Analyse a course and return a payload compatible with cache_manager::save_course_engagement().
     *
     * This does not write to the database by itself (callers decide when to persist).
     *
     * @param int $courseid
     * @return \stdClass
     */
    public static function analyse_course(int $courseid): \stdClass {
        $courseid = (int)$courseid;
        if ($courseid <= 0) {
            throw new \coding_exception('Invalid courseid');
        }

        $activeuserids = self::get_active_students($courseid);
        $inactiveuserids = self::get_inactive_students($courseid);
        $mostactive = self::get_most_active_student($courseid);

        $payload = new \stdClass();
        $payload->courseid = $courseid;
        $payload->active_students = count($activeuserids);
        $payload->inactive_students = count($inactiveuserids);
        $payload->most_active_userid = $mostactive ? (int)$mostactive->userid : 0;
        $payload->most_active_interactions = $mostactive ? (int)$mostactive->interactions : 0;
        $payload->inactive_userids = $inactiveuserids;
        $payload->last_calculated = time();

        return $payload;
    }

    /**
     * Compute a "since" timestamp given a day count.
     *
     * @param int $days
     * @return int
     */
    private static function since_days(int $days): int {
        $days = max(0, (int)$days);
        if ($days === 0) {
            return 0;
        }
        return time() - ($days * DAYSECS);
    }

    /**
     * Get configured active days threshold (default 7).
     *
     * @return int
     */
    private static function active_days_threshold(): int {
        $value = get_config('block_student_engagement', 'active_days_threshold');
        $days = ($value === false || $value === null || $value === '') ? 7 : (int)$value;
        return max(0, $days);
    }

    /**
     * Get configured inactive days threshold (default 14).
     *
     * @return int
     */
    private static function inactive_days_threshold(): int {
        $value = get_config('block_student_engagement', 'inactive_days_threshold');
        $days = ($value === false || $value === null || $value === '') ? 14 : (int)$value;
        return max(0, $days);
    }
}
