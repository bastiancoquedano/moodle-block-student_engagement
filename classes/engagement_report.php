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
 * Engagement report data service.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_student_engagement;

defined('MOODLE_INTERNAL') || die();

/**
 * Domain service to aggregate per-student report data.
 */
class engagement_report {

    /** @var string Student role shortname. */
    private const STUDENT_ROLE_SHORTNAME = 'student';

    /**
     * Count students in a course.
     *
     * @param int $courseid
     * @return int
     */
    public static function count_students(int $courseid): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT ra.userid)
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {user} u ON u.id = ra.userid
                 WHERE ctx.contextlevel = :contextcourse
                   AND ctx.instanceid = :courseid
                   AND r.shortname = :studentshortname
                   AND u.deleted = 0";
        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'courseid' => $courseid,
            'studentshortname' => self::STUDENT_ROLE_SHORTNAME,
        ];

        return (int)$DB->count_records_sql($sql, $params);
    }

    /**
     * Return report rows for a course.
     *
     * @param int $courseid
     * @param string $sort
     * @param int $limitfrom
     * @param int $limitnum
     * @return \stdClass[]
     */
    public static function get_rows(int $courseid, string $sort, int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;

        $totalactivities = self::get_total_completable_activities($courseid);
        $eventgoal = self::get_event_goal();
        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'maincourseid' => $courseid,
            'eventcourseid' => $courseid,
            'completioncourseid' => $courseid,
            'studentshortname' => self::STUDENT_ROLE_SHORTNAME,
            'eventgoalcompare' => max(1, $eventgoal),
            'eventgoalscale' => max(1, $eventgoal),
            'totalactivitiescheck' => $totalactivities,
            'totalactivitiesscale' => $totalactivities,
        ];

        $scoreexpression = self::get_score_sql();
        $sql = "SELECT students.userid,
                       u.firstname,
                       u.lastname,
                       u.firstnamephonetic,
                       u.lastnamephonetic,
                       u.middlename,
                       u.alternatename,
                       u.lastname AS student,
                       u.firstname AS studentfirstname,
                       COALESCE(events.eventcount, 0) AS eventcount,
                       COALESCE(completed.completedcount, 0) AS completedcount,
                       {$scoreexpression} AS engagementscore
                  FROM (
                        SELECT DISTINCT ra.userid
                          FROM {role_assignments} ra
                          JOIN {context} ctx ON ctx.id = ra.contextid
                          JOIN {role} r ON r.id = ra.roleid
                         WHERE ctx.contextlevel = :contextcourse
                           AND ctx.instanceid = :maincourseid
                           AND r.shortname = :studentshortname
                       ) students
                  JOIN {user} u ON u.id = students.userid
             LEFT JOIN (
                        SELECT l.userid, COUNT(1) AS eventcount
                          FROM {logstore_standard_log} l
                         WHERE l.courseid = :eventcourseid
                           AND l.userid > 0
                      GROUP BY l.userid
                       ) events ON events.userid = students.userid
             LEFT JOIN (
                        SELECT c.userid, COUNT(DISTINCT c.coursemoduleid) AS completedcount
                          FROM {course_modules_completion} c
                          JOIN {course_modules} cm ON cm.id = c.coursemoduleid
                         WHERE cm.course = :completioncourseid
                           AND cm.completion > 0
                           AND c.completionstate <> 0
                      GROUP BY c.userid
                       ) completed ON completed.userid = students.userid
                 WHERE u.deleted = 0
              ORDER BY {$sort}";

        $records = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum ?: 0);
        foreach ($records as $record) {
            $record->totalactivities = $totalactivities;
            $record->profileurl = new \moodle_url('/user/profile.php', [
                'id' => (int)$record->userid,
                'course' => $courseid,
            ]);
            $record->studentname = fullname($record);
            $record->engagementscore = max(0, min(100, (int)$record->engagementscore));
            $record->engagementlevel = self::resolve_level((int)$record->engagementscore);
        }

        return array_values($records);
    }

    /**
     * Get total activities with completion enabled in the course.
     *
     * @param int $courseid
     * @return int
     */
    public static function get_total_completable_activities(int $courseid): int {
        global $DB;

        $sql = "SELECT COUNT(1)
                  FROM {course_modules} cm
                 WHERE cm.course = :courseid
                   AND cm.completion > 0";

        return (int)$DB->count_records_sql($sql, ['courseid' => $courseid]);
    }

    /**
     * Get configured event goal for report scoring.
     *
     * @return int
     */
    public static function get_event_goal(): int {
        $value = get_config('block_student_engagement', 'report_event_goal');
        return max(1, (int)($value ?: 30));
    }

    /**
     * Resolve engagement level class based on score.
     *
     * @param int $score
     * @return string
     */
    public static function resolve_level(int $score): string {
        if ($score >= 70) {
            return 'high';
        }

        if ($score >= 40) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Return allowed sort SQL.
     *
     * @param string $sort
     * @param string $dir
     * @return string
     */
    public static function get_sort_sql(string $sort, string $dir): string {
        $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $map = [
            'student' => 'student ' . $direction . ', studentfirstname ' . $direction,
            'eventcount' => 'eventcount ' . $direction . ', student ASC, studentfirstname ASC',
            'completedcount' => 'completedcount ' . $direction . ', student ASC, studentfirstname ASC',
            'engagementscore' => 'engagementscore ' . $direction . ', student ASC, studentfirstname ASC',
        ];

        return $map[$sort] ?? $map['student'];
    }

    /**
     * SQL expression for the 0-100 score.
     *
     * @return string
     */
    private static function get_score_sql(): string {
        return "ROUND(
                    (CASE
                        WHEN :totalactivitiescheck > 0
                        THEN (COALESCE(completed.completedcount, 0) * 70.0 / :totalactivitiesscale)
                        ELSE 0
                    END) +
                    (CASE
                        WHEN COALESCE(events.eventcount, 0) >= :eventgoalcompare
                        THEN 30
                        ELSE (COALESCE(events.eventcount, 0) * 30.0 / :eventgoalscale)
                    END),
                0)";
    }
}
