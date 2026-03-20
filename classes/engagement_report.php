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
    /** @var string Event name excluded from inactivity activity checks. */
    private const EVENT_COURSE_VIEWED = '\\core\\event\\course_viewed';

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
     * Count rows for a report mode.
     *
     * @param int $courseid
     * @param string $viewmode
     * @return int
     */
    public static function count_rows(int $courseid, string $viewmode = 'all'): int {
        if ($viewmode !== 'inactive') {
            return self::count_students($courseid);
        }

        global $DB;

        $inactivesince = time() - (self::get_inactive_days_threshold() * DAYSECS);
        $sql = "SELECT COUNT(DISTINCT ra.userid)
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {user} u ON u.id = ra.userid
             LEFT JOIN (
                        SELECT l.userid, MAX(l.timecreated) AS lastactivity
                          FROM {logstore_standard_log} l
                         WHERE l.courseid = :activitycourseid
                           AND l.userid > 0
                           AND l.eventname <> :courseviewed
                      GROUP BY l.userid
                       ) activity ON activity.userid = ra.userid
                 WHERE ctx.contextlevel = :contextcourse
                   AND ctx.instanceid = :courseid
                   AND r.shortname = :studentshortname
                   AND u.deleted = 0
                   AND (activity.lastactivity IS NULL OR activity.lastactivity < :inactivesince)";
        $params = [
            'activitycourseid' => $courseid,
            'courseviewed' => self::EVENT_COURSE_VIEWED,
            'contextcourse' => CONTEXT_COURSE,
            'courseid' => $courseid,
            'studentshortname' => self::STUDENT_ROLE_SHORTNAME,
            'inactivesince' => $inactivesince,
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
    public static function get_rows(
        int $courseid,
        string $sort,
        int $limitfrom = 0,
        int $limitnum = 0,
        string $viewmode = 'all'
    ): array {
        global $DB;

        $isinactiveview = ($viewmode === 'inactive');
        $totalactivities = self::get_total_completable_activities($courseid);
        $eventgoal = self::get_event_goal();
        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'maincourseid' => $courseid,
            'eventcourseid' => $courseid,
            'completioncourseid' => $courseid,
            'lastaccesscourseid' => $courseid,
            'activitycourseid' => $courseid,
            'studentshortname' => self::STUDENT_ROLE_SHORTNAME,
            'courseviewed' => self::EVENT_COURSE_VIEWED,
            'eventgoalcompare' => max(1, $eventgoal),
            'eventgoalscale' => max(1, $eventgoal),
            'totalactivitiescheck' => $totalactivities,
            'totalactivitiesscale' => $totalactivities,
        ];
        $inactivewhere = '';
        if ($isinactiveview) {
            $params['inactivesince'] = time() - (self::get_inactive_days_threshold() * DAYSECS);
            $inactivewhere = ' AND (activity.lastactivity IS NULL OR activity.lastactivity < :inactivesince)';
        }

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
                       COALESCE(lastaccess.lastcourseaccess, 0) AS lastcourseaccess,
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
             LEFT JOIN (
                        SELECT l.userid, MAX(l.timecreated) AS lastcourseaccess
                          FROM {logstore_standard_log} l
                         WHERE l.courseid = :lastaccesscourseid
                           AND l.userid > 0
                      GROUP BY l.userid
                       ) lastaccess ON lastaccess.userid = students.userid
             LEFT JOIN (
                        SELECT l.userid, MAX(l.timecreated) AS lastactivity
                          FROM {logstore_standard_log} l
                         WHERE l.courseid = :activitycourseid
                           AND l.userid > 0
                           AND l.eventname <> :courseviewed
                      GROUP BY l.userid
                       ) activity ON activity.userid = students.userid
                 WHERE u.deleted = 0
                   {$inactivewhere}
              ORDER BY {$sort}";

        $records = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum ?: 0);
        foreach ($records as $record) {
            $record->totalactivities = $totalactivities;
            $record->eventgoal = $eventgoal;
            $record->profileurl = new \moodle_url('/user/profile.php', [
                'id' => (int)$record->userid,
                'course' => $courseid,
            ]);
            $record->studentname = fullname($record);
            $record->lastaccesstimestamp = !empty($record->lastcourseaccess) ? (int)$record->lastcourseaccess : null;
            $record->daysinactive = ($record->lastaccesstimestamp === null)
                ? null
                : max(0, (int)floor((time() - (int)$record->lastaccesstimestamp) / DAYSECS));
            $record->eventprogress = self::calculate_progress((int)$record->eventcount, (int)$eventgoal);
            $record->completedprogress = self::calculate_progress((int)$record->completedcount, (int)$totalactivities);
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
    public static function get_sort_sql(string $sort, string $dir, string $viewmode = 'all'): string {
        $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        if ($viewmode === 'inactive') {
            $inversedirection = ($direction === 'DESC') ? 'ASC' : 'DESC';
            $map = [
                'student' => 'student ' . $direction . ', studentfirstname ' . $direction,
                'daysinactive' => 'lastcourseaccess ' . $inversedirection . ', student ASC, studentfirstname ASC',
                'lastaccess' => 'lastcourseaccess ' . $direction . ', student ASC, studentfirstname ASC',
            ];

            return $map[$sort] ?? $map['daysinactive'];
        }

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

    /**
     * Calculate integer progress percent with a 100% cap.
     *
     * @param int $value
     * @param int $goal
     * @return int
     */
    private static function calculate_progress(int $value, int $goal): int {
        if ($goal <= 0) {
            return 0;
        }

        $progress = (int)round(($value * 100) / $goal);
        return max(0, min(100, $progress));
    }

    /**
     * Get configured inactive days threshold (default 14).
     *
     * @return int
     */
    private static function get_inactive_days_threshold(): int {
        $value = get_config('block_student_engagement', 'inactive_days_threshold');
        $days = ($value === false || $value === null || $value === '') ? 14 : (int)$value;
        return max(0, $days);
    }
}
