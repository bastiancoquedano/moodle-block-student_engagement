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
 * @Author Bastian Coquedano
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
     * @param string $viewmode
     * @param array $filters
     * @return int
     */
    public static function count_rows(int $courseid, string $viewmode = 'all', array $filters = []): int {
        global $DB;

        $filterdata = self::normalise_filters($viewmode, $filters);
        $parts = self::build_shared_sql_parts($courseid, $filterdata);

        $sql = "SELECT COUNT(DISTINCT students.userid)
                  {$parts['from']}
                 {$parts['where']}";

        return (int)$DB->count_records_sql($sql, $parts['params']);
    }

    /**
     * Return report rows for a course.
     *
     * @param int $courseid
     * @param string $sortcolumn
     * @param string $sortdirection
     * @param int $limitfrom
     * @param int $limitnum
     * @param string $viewmode
     * @param array $filters
     * @return \stdClass[]
     */
    public static function get_rows(
        int $courseid,
        string $sortcolumn = 'risklevel',
        string $sortdirection = 'DESC',
        int $limitfrom = 0,
        int $limitnum = 0,
        string $viewmode = 'all',
        array $filters = []
    ): array {
        global $DB;

        $filterdata = self::normalise_filters($viewmode, $filters);
        $parts = self::build_shared_sql_parts($courseid, $filterdata);
        $totalactivities = self::get_total_completable_activities($courseid);
        $eventgoal = self::get_event_goal();
        $ordersql = self::get_sort_sql($sortcolumn, $sortdirection, $viewmode);

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
                       COALESCE(logsummary.eventcount, 0) AS eventcount,
                       COALESCE(risk.recent_events, COALESCE(logsummary.eventcount, 0)) AS recentevents,
                       COALESCE(completed.completedcount, 0) AS completedcount,
                       COALESCE(logsummary.lastcourseaccess, 0) AS lastcourseaccess,
                       risk.current_grade AS currentgrade,
                       risk.pass_grade AS passgrade,
                       risk.grade_gap AS gradegap,
                       COALESCE(risk.risk_score, 0) AS riskscore,
                       COALESCE(risk.risk_level, 0) AS risklevel,
                       risk.risk_flags AS riskflags,
                       risk.days_inactive AS riskdaysinactive,
                       COALESCE(
                           risk.days_inactive,
                           CASE
                               WHEN COALESCE(logsummary.lastcourseaccess, 0) = 0 THEN NULL
                               ELSE FLOOR((:currenttimestamp - logsummary.lastcourseaccess) / :secondsinday)
                           END
                       ) AS daysinactivevalue,
                       {$scoreexpression} AS engagementscore
                  {$parts['from']}
                 {$parts['where']}
              ORDER BY {$ordersql}";

        $params = $parts['params'];
        $params['eventgoalcompare'] = max(1, $eventgoal);
        $params['eventgoalscale'] = max(1, $eventgoal);
        $params['totalactivitiescheck'] = $totalactivities;
        $params['totalactivitiesscale'] = $totalactivities;
        $params['currenttimestamp'] = time();
        $params['secondsinday'] = DAYSECS;

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

            if ($record->daysinactivevalue !== null) {
                $record->daysinactive = max(0, (int)$record->daysinactivevalue);
            } else if ($record->lastaccesstimestamp === null) {
                $record->daysinactive = null;
            } else {
                $record->daysinactive = max(0, (int)floor((time() - (int)$record->lastaccesstimestamp) / DAYSECS));
            }

            $record->eventprogress = self::calculate_progress((int)$record->recentevents, (int)$eventgoal);
            $record->completedprogress = self::calculate_progress((int)$record->completedcount, (int)$totalactivities);
            $record->engagementscore = max(0, min(100, (int)$record->engagementscore));
            $record->engagementlevel = self::resolve_level((int)$record->engagementscore);
            $record->riskscore = max(0, min(100, (int)$record->riskscore));
            $record->risklevel = max(0, min(3, (int)$record->risklevel));
            $record->riskflags = self::decode_flags($record->riskflags ?? null);

            $record->currentgrade = ($record->currentgrade === null || $record->currentgrade === '')
                ? null
                : (float)$record->currentgrade;
            $record->passgrade = ($record->passgrade === null || $record->passgrade === '')
                ? null
                : (float)$record->passgrade;
            $record->gradegap = ($record->gradegap === null || $record->gradegap === '')
                ? null
                : (float)$record->gradegap;
        }

        return array_values($records);
    }

    /**
     * Build reusable SQL fragments for count and rows.
     *
     * @param int $courseid
     * @param array $filters
     * @return array{from:string,where:string,params:array}
     */
    private static function build_shared_sql_parts(int $courseid, array $filters): array {
        $from = "FROM (
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
                    SELECT l.userid,
                           COUNT(1) AS eventcount,
                           MAX(l.timecreated) AS lastcourseaccess
                      FROM {logstore_standard_log} l
                     WHERE l.courseid = :logcourseid
                       AND l.userid > 0
                  GROUP BY l.userid
                   ) logsummary ON logsummary.userid = students.userid
         LEFT JOIN (
                    SELECT c.userid, COUNT(DISTINCT c.coursemoduleid) AS completedcount
                      FROM {course_modules_completion} c
                      JOIN {course_modules} cm ON cm.id = c.coursemoduleid
                     WHERE cm.course = :completioncourseid
                       AND cm.completion > 0
                       AND c.completionstate <> 0
                  GROUP BY c.userid
                   ) completed ON completed.userid = students.userid
         LEFT JOIN {block_student_engagement_risk} risk
                ON risk.courseid = :riskcourseid
               AND risk.userid = students.userid";

        $where = "WHERE u.deleted = 0";
        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'maincourseid' => $courseid,
            'logcourseid' => $courseid,
            'completioncourseid' => $courseid,
            'riskcourseid' => $courseid,
            'studentshortname' => self::STUDENT_ROLE_SHORTNAME,
        ];

        if ($filters['groupid'] > 0) {
            $where .= " AND EXISTS (
                SELECT 1
                  FROM {groups_members} gm
                 WHERE gm.userid = students.userid
                   AND gm.groupid = :groupid
            )";
            $params['groupid'] = (int)$filters['groupid'];
        }

        if (!empty($filters['atrisk'])) {
            $where .= " AND risk.risk_level >= :risklevelmin";
            $params['risklevelmin'] = 2;
        } else if ($filters['risklevel'] !== null) {
            $params['risklevel'] = (int)$filters['risklevel'];
            if ((int)$filters['risklevel'] === 0) {
                $where .= " AND (risk.risk_level = :risklevel OR risk.risk_level IS NULL)";
            } else {
                $where .= " AND risk.risk_level = :risklevel";
            }
        }

        if ($filters['status'] === 'inactive' || $filters['legacyinactive']) {
            $where .= " AND (
                logsummary.lastcourseaccess IS NULL
                OR logsummary.lastcourseaccess = 0
                OR risk.days_inactive >= :inactivedaysthreshold
                OR (
                    risk.days_inactive IS NULL
                    AND logsummary.lastcourseaccess > 0
                    AND FLOOR((:currenttimeinactive - logsummary.lastcourseaccess) / :daysecsinactive) >= :inactivedaysthreshold
                )
            )";
            $params['currenttimeinactive'] = (int)$filters['currenttime'];
            $params['daysecsinactive'] = DAYSECS;
            $params['inactivedaysthreshold'] = (int)$filters['inactivedaysthreshold'];
        } else if ($filters['status'] === 'active') {
            $where .= " AND logsummary.lastcourseaccess > 0
                        AND (
                            risk.days_inactive < :inactivedaysthreshold
                            OR (
                                risk.days_inactive IS NULL
                                AND FLOOR(
                                    (:currenttimeactive - logsummary.lastcourseaccess) / :daysecsactive
                                ) < :inactivedaysthreshold
                            )
                        )";
            $params['currenttimeactive'] = (int)$filters['currenttime'];
            $params['daysecsactive'] = DAYSECS;
            $params['inactivedaysthreshold'] = (int)$filters['inactivedaysthreshold'];
        }

        return [
            'from' => $from,
            'where' => $where,
            'params' => $params,
        ];
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
     * @param string $viewmode
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
            'lastaccess' => 'lastcourseaccess ' . $direction . ', student ASC, studentfirstname ASC',
            'daysinactive' => 'daysinactivevalue ' . $direction . ', student ASC, studentfirstname ASC',
            'recentevents' => 'recentevents ' . $direction . ', student ASC, studentfirstname ASC',
            'completedcount' => 'completedcount ' . $direction . ', student ASC, studentfirstname ASC',
            'currentgrade' => 'currentgrade ' . $direction . ', student ASC, studentfirstname ASC',
            'passgrade' => 'passgrade ' . $direction . ', student ASC, studentfirstname ASC',
            'gradegap' => 'gradegap ' . $direction . ', student ASC, studentfirstname ASC',
            'engagementscore' => 'engagementscore ' . $direction . ', student ASC, studentfirstname ASC',
            'riskscore' => 'riskscore ' . $direction . ', student ASC, studentfirstname ASC',
            'risklevel' => 'risklevel ' . $direction . ', riskscore DESC, daysinactivevalue DESC, student ASC, studentfirstname ASC',
        ];

        return $map[$sort] ?? 'risklevel DESC, riskscore DESC, daysinactivevalue DESC, student ASC, studentfirstname ASC';
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
                        WHEN COALESCE(logsummary.eventcount, 0) >= :eventgoalcompare
                        THEN 30
                        ELSE (COALESCE(logsummary.eventcount, 0) * 30.0 / :eventgoalscale)
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
     * Normalize filter input.
     *
     * @param string $viewmode
     * @param array $filters
     * @return array
     */
    private static function normalise_filters(string $viewmode, array $filters): array {
        $risklevel = null;
        if (array_key_exists('risklevel', $filters) && $filters['risklevel'] !== 'all' && $filters['risklevel'] !== '' && $filters['risklevel'] !== null) {
            $risklevelraw = trim((string)$filters['risklevel']);
            if ($risklevelraw === 'high_critical') {
                $risklevel = null;
            } else if (ctype_digit($risklevelraw)) {
                $risklevelint = (int)$risklevelraw;
                if ($risklevelint >= 0 && $risklevelint <= 3) {
                    $risklevel = $risklevelint;
                }
            }
        }
        $atrisk = (!empty($filters['atrisk']) || (($filters['risklevel'] ?? '') === 'high_critical')) && $risklevel === null;

        $groupid = isset($filters['groupid']) ? max(0, (int)$filters['groupid']) : 0;
        $status = $filters['status'] ?? 'all';
        if (!in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        $customactive = ($risklevel !== null || $groupid > 0 || $status !== 'all');
        $legacyinactive = ($viewmode === 'inactive' && !$customactive);

        return [
            'risklevel' => $risklevel,
            'atrisk' => $atrisk,
            'groupid' => $groupid,
            'status' => $status,
            'legacyinactive' => $legacyinactive,
            'currenttime' => time(),
            'inactivedaysthreshold' => self::get_inactive_days_threshold(),
        ];
    }

    /**
     * Decode risk flags JSON safely.
     *
     * @param string|null $flags
     * @return string[]
     */
    private static function decode_flags(?string $flags): array {
        if ($flags === null || $flags === '') {
            return [];
        }

        $decoded = json_decode($flags, true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $items[] = $value;
            }
        }

        return $items;
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
