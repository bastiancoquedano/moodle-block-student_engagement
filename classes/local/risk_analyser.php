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
 * Academic risk analyser service.
 * @Author Bastian Coquedano
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_student_engagement\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Calculates and persists per-student academic risk.
 */
class risk_analyser {
    /** @var string */
    private const RISK_TABLE = 'block_student_engagement_risk';

    /** @var string */
    private const EVENT_COURSE_VIEWED = '\\core\\event\\course_viewed';

    /** @var int */
    private const LEVEL_NORMAL = 0;
    /** @var int */
    private const LEVEL_OBSERVATION = 1;
    /** @var int */
    private const LEVEL_HIGH = 2;
    /** @var int */
    private const LEVEL_CRITICAL = 3;

    /**
     * Analyse risk for all students in a course.
     *
     * @param int $courseid
     * @return array{rows:array<int,array>,aggregates:array<string,int>}
     */
    public static function analyse_course(int $courseid): array {
        if ($courseid <= 0) {
            return ['rows' => [], 'aggregates' => self::empty_aggregates()];
        }

        $config = self::get_valid_config();
        $studentuserids = \block_student_engagement\engagement_analyser::get_student_userids($courseid);
        if (empty($studentuserids)) {
            return ['rows' => [], 'aggregates' => self::empty_aggregates()];
        }

        $gradeinfo = self::get_gradebook_data($courseid, $studentuserids);
        $totalactivities = \block_student_engagement\engagement_report::get_total_completable_activities($courseid);
        $completedcounts = self::get_completed_counts($courseid);

        $recentevents = self::get_recent_event_counts($courseid, $config['risk_inactivity_days_threshold']);
        $lastactivity = self::get_last_activity_timestamps($courseid);
        $courseprogress = self::get_course_progress_percent($courseid, $studentuserids, $totalactivities, $config);

        $now = time();
        $rows = [];
        foreach ($studentuserids as $userid) {
            $userid = (int)$userid;
            $currentgrade = $gradeinfo['grades'][$userid] ?? null;
            $passgrade = $gradeinfo['pass_grade'];
            $completioncount = $completedcounts[$userid] ?? 0;
            $completionpercent = ($totalactivities > 0)
                ? (int)round(($completioncount * 100) / $totalactivities)
                : 50;
            $completionpercent = max(0, min(100, $completionpercent));

            $lastactivityts = $lastactivity[$userid] ?? null;
            if ($lastactivityts === null) {
                $daysinactive = $config['risk_inactivity_days_threshold'] + 1;
            } else {
                $daysinactive = max(0, (int)floor(($now - (int)$lastactivityts) / DAYSECS));
            }

            $recenteventcount = $recentevents[$userid] ?? 0;
            $engagementscore = \block_student_engagement\engagement_analyser::get_engagement_score(
                $userid,
                $courseid,
                $config['risk_inactivity_days_threshold']
            );

            $dataset = [
                'courseid' => $courseid,
                'userid' => $userid,
                'current_grade' => $currentgrade,
                'pass_grade' => $passgrade,
                'completion_percent' => $completionpercent,
                'days_inactive' => $daysinactive,
                'recent_events' => $recenteventcount,
                'engagement_score' => $engagementscore,
                'course_progress_percent' => $courseprogress,
                'attendance_percent' => null,
                'config' => $config,
            ];

            $result = self::analyse_student($dataset);
            $result['last_calculated'] = $now;
            $rows[] = $result;
        }

        return [
            'rows' => $rows,
            'aggregates' => self::build_aggregates($rows, $now),
        ];
    }

    /**
     * Analyse one student dataset.
     *
     * @param array $dataset
     * @return array
     */
    public static function analyse_student(array $dataset): array {
        $config = $dataset['config'] ?? self::get_valid_config();

        $courseid = (int)($dataset['courseid'] ?? 0);
        $userid = (int)($dataset['userid'] ?? 0);

        $currentgrade = self::to_nullable_float($dataset['current_grade'] ?? null);
        $passgrade = self::to_nullable_float($dataset['pass_grade'] ?? null);
        $gradegap = ($currentgrade !== null && $passgrade !== null) ? ($currentgrade - $passgrade) : null;

        $completionpercent = max(0, min(100, (int)($dataset['completion_percent'] ?? 50)));
        $daysinactive = max(0, (int)($dataset['days_inactive'] ?? 0));
        $recentevents = max(0, (int)($dataset['recent_events'] ?? 0));
        $attpercent = self::to_nullable_float($dataset['attendance_percent'] ?? null);
        $engagementscore = max(0, (int)($dataset['engagement_score'] ?? 0));
        $courseprogress = max(0, min(100, (int)($dataset['course_progress_percent'] ?? 50)));

        $graderisk = self::grade_risk($currentgrade, $passgrade);
        $completionrisk = 100 - $completionpercent;
        $inactivityrisk = (int)round(min(100, ($daysinactive * 100) / max(1, $config['risk_inactivity_days_threshold'])));
        $participationrisk = 100 - (int)round(min(100, ($recentevents * 100) / max(1, $config['risk_event_goal'])));

        $basescore =
            ($graderisk * $config['risk_grade_weight'] / 100.0) +
            ($completionrisk * $config['risk_completion_weight'] / 100.0) +
            ($inactivityrisk * $config['risk_inactivity_weight'] / 100.0) +
            ($participationrisk * $config['risk_participation_weight'] / 100.0);

        $factor = 1.0;
        if ($courseprogress < $config['risk_start_percentage']) {
            $factor = 0.7;
        } else if ($courseprogress >= $config['risk_critical_percentage']) {
            $factor = 1.2;
        }

        $riskscore = max(0, min(100, (int)round($basescore * $factor)));
        $risklevel = self::determine_risk_level($riskscore, $config);
        $riskflags = self::build_flags($currentgrade, $passgrade, $completionpercent, $daysinactive, $recentevents, $courseprogress, $config);

        return [
            'courseid' => $courseid,
            'userid' => $userid,
            'current_grade' => $currentgrade,
            'pass_grade' => $passgrade,
            'grade_gap' => $gradegap,
            'completion_percent' => $completionpercent,
            'days_inactive' => $daysinactive,
            'recent_events' => $recentevents,
            'attendance_percent' => $attpercent,
            'engagement_score' => $engagementscore,
            'risk_score' => $riskscore,
            'risk_level' => $risklevel,
            'risk_flags' => $riskflags,
        ];
    }

    /**
     * Persist course risk rows with upsert semantics.
     *
     * @param int $courseid
     * @param array<int,array> $rows
     * @return void
     */
    public static function upsert_course_risk(int $courseid, array $rows): void {
        global $DB;

        if ($courseid <= 0) {
            return;
        }

        $existing = $DB->get_records(self::RISK_TABLE, ['courseid' => $courseid], '', 'id,userid');
        $existingbyuserid = [];
        foreach ($existing as $record) {
            $existingbyuserid[(int)$record->userid] = (int)$record->id;
        }

        $processed = [];
        foreach ($rows as $row) {
            $userid = (int)$row['userid'];
            $processed[$userid] = true;

            $record = (object)[
                'courseid' => (int)$courseid,
                'userid' => $userid,
                'current_grade' => $row['current_grade'],
                'pass_grade' => $row['pass_grade'],
                'grade_gap' => $row['grade_gap'],
                'completion_percent' => (int)$row['completion_percent'],
                'days_inactive' => (int)$row['days_inactive'],
                'recent_events' => (int)$row['recent_events'],
                'attendance_percent' => $row['attendance_percent'],
                'engagement_score' => (int)$row['engagement_score'],
                'risk_score' => (int)$row['risk_score'],
                'risk_level' => (int)$row['risk_level'],
                'risk_flags' => json_encode(array_values($row['risk_flags'] ?? [])),
                'last_calculated' => (int)($row['last_calculated'] ?? time()),
            ];

            if (isset($existingbyuserid[$userid])) {
                $record->id = $existingbyuserid[$userid];
                $DB->update_record(self::RISK_TABLE, $record);
            } else {
                $DB->insert_record(self::RISK_TABLE, $record);
            }
        }

        foreach ($existingbyuserid as $userid => $id) {
            if (!isset($processed[$userid])) {
                $DB->delete_records(self::RISK_TABLE, ['id' => $id]);
            }
        }
    }

    /**
     * Return safe, validated risk config.
     *
     * @return array<string,int|string>
     */
    private static function get_valid_config(): array {
        $defaults = [
            'risk_grade_weight' => 40,
            'risk_completion_weight' => 25,
            'risk_inactivity_weight' => 20,
            'risk_participation_weight' => 15,
            'risk_inactivity_days_threshold' => 14,
            'risk_event_goal' => 30,
            'risk_level_observation_min' => 25,
            'risk_level_high_min' => 50,
            'risk_level_critical_min' => 75,
            'risk_start_percentage' => 50,
            'risk_critical_percentage' => 75,
            'risk_course_progress_mode' => 'course_dates',
        ];

        $config = $defaults;
        foreach ($defaults as $key => $value) {
            $stored = get_config('block_student_engagement', $key);
            if (is_string($value)) {
                $config[$key] = ($stored === false || $stored === null || $stored === '') ? $value : (string)$stored;
            } else {
                $config[$key] = ($stored === false || $stored === null || $stored === '') ? $value : (int)$stored;
            }
        }

        $weights = [
            'risk_grade_weight',
            'risk_completion_weight',
            'risk_inactivity_weight',
            'risk_participation_weight',
        ];
        $sum = 0;
        foreach ($weights as $weightkey) {
            if (!is_int($config[$weightkey]) || $config[$weightkey] < 0) {
                $config[$weightkey] = $defaults[$weightkey];
            }
            $sum += (int)$config[$weightkey];
        }
        if ($sum !== 100) {
            foreach ($weights as $weightkey) {
                $config[$weightkey] = $defaults[$weightkey];
            }
        }

        $config['risk_inactivity_days_threshold'] = max(1, (int)$config['risk_inactivity_days_threshold']);
        $config['risk_event_goal'] = max(1, (int)$config['risk_event_goal']);

        $obs = (int)$config['risk_level_observation_min'];
        $high = (int)$config['risk_level_high_min'];
        $critical = (int)$config['risk_level_critical_min'];
        if ($obs < 0 || $high < $obs || $critical < $high || $critical > 100) {
            $config['risk_level_observation_min'] = $defaults['risk_level_observation_min'];
            $config['risk_level_high_min'] = $defaults['risk_level_high_min'];
            $config['risk_level_critical_min'] = $defaults['risk_level_critical_min'];
        }

        $start = (int)$config['risk_start_percentage'];
        $criticalstage = (int)$config['risk_critical_percentage'];
        if ($start < 0 || $criticalstage < $start || $criticalstage > 100) {
            $config['risk_start_percentage'] = $defaults['risk_start_percentage'];
            $config['risk_critical_percentage'] = $defaults['risk_critical_percentage'];
        }

        $allowedmodes = ['course_dates', 'graded_completion'];
        if (!in_array((string)$config['risk_course_progress_mode'], $allowedmodes, true)) {
            $config['risk_course_progress_mode'] = $defaults['risk_course_progress_mode'];
        }

        return $config;
    }

    /**
     * @param int $riskscore
     * @param array<string,int|string> $config
     * @return int
     */
    private static function determine_risk_level(int $riskscore, array $config): int {
        if ($riskscore >= (int)$config['risk_level_critical_min']) {
            return self::LEVEL_CRITICAL;
        }

        if ($riskscore >= (int)$config['risk_level_high_min']) {
            return self::LEVEL_HIGH;
        }

        if ($riskscore >= (int)$config['risk_level_observation_min']) {
            return self::LEVEL_OBSERVATION;
        }

        return self::LEVEL_NORMAL;
    }

    /**
     * @param float|null $currentgrade
     * @param float|null $passgrade
     * @return int
     */
    private static function grade_risk(?float $currentgrade, ?float $passgrade): int {
        if ($currentgrade === null || $passgrade === null || $passgrade <= 0) {
            return 50;
        }

        if ($currentgrade >= $passgrade) {
            return 0;
        }

        $gap = max(0.0, $passgrade - $currentgrade);
        return (int)round(min(100, ($gap * 100) / $passgrade));
    }

    /**
     * @param float|null $currentgrade
     * @param float|null $passgrade
     * @param int $completionpercent
     * @param int $daysinactive
     * @param int $recentevents
     * @param int $courseprogress
     * @param array<string,int|string> $config
     * @return string[]
     */
    private static function build_flags(
        ?float $currentgrade,
        ?float $passgrade,
        int $completionpercent,
        int $daysinactive,
        int $recentevents,
        int $courseprogress,
        array $config
    ): array {
        $flags = [];

        if ($currentgrade !== null && $passgrade !== null && $passgrade > 0 && $currentgrade < $passgrade) {
            $flags[] = 'below_pass_grade';
        }

        if ($completionpercent < 50) {
            $flags[] = 'low_completion';
        }

        if ($daysinactive >= (int)$config['risk_inactivity_days_threshold']) {
            $flags[] = 'inactive';
        }

        $lowactivitylimit = max(1, (int)ceil(((int)$config['risk_event_goal']) * 0.3));
        if ($recentevents < $lowactivitylimit) {
            $flags[] = 'low_recent_activity';
        }

        if ($completionpercent + 10 < $courseprogress) {
            $flags[] = 'behind_expected_progress';
        }

        return $flags;
    }

    /**
     * @param int $courseid
     * @param int[] $userids
     * @return array{pass_grade:?float,grades:array<int,?float>}
     */
    private static function get_gradebook_data(int $courseid, array $userids): array {
        global $DB;

        $passgrade = null;
        $grades = [];
        foreach ($userids as $userid) {
            $grades[(int)$userid] = null;
        }

        $courseitem = $DB->get_record('grade_items', [
            'courseid' => $courseid,
            'itemtype' => 'course',
        ], 'id,gradepass', IGNORE_MISSING);

        if (!$courseitem) {
            return ['pass_grade' => null, 'grades' => $grades];
        }

        if ($courseitem->gradepass !== null) {
            $passgrade = (float)$courseitem->gradepass;
        }

        list($insql, $inparams) = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED);
        $sql = "SELECT userid, finalgrade
                  FROM {grade_grades}
                 WHERE itemid = :itemid
                   AND userid {$insql}";
        $params = ['itemid' => (int)$courseitem->id] + $inparams;
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            $grades[(int)$record->userid] = ($record->finalgrade === null) ? null : (float)$record->finalgrade;
        }

        return ['pass_grade' => $passgrade, 'grades' => $grades];
    }

    /**
     * @param int $courseid
     * @return array<int,int>
     */
    private static function get_completed_counts(int $courseid): array {
        global $DB;

        $sql = "SELECT c.userid, COUNT(DISTINCT c.coursemoduleid) AS completedcount
                  FROM {course_modules_completion} c
                  JOIN {course_modules} cm ON cm.id = c.coursemoduleid
                 WHERE cm.course = :courseid
                   AND cm.completion > 0
                   AND c.completionstate <> 0
              GROUP BY c.userid";

        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row->userid] = (int)$row->completedcount;
        }

        return $map;
    }

    /**
     * @param int $courseid
     * @param int $days
     * @return array<int,int>
     */
    private static function get_recent_event_counts(int $courseid, int $days): array {
        global $DB;

        $since = max(0, time() - (max(0, $days) * DAYSECS));
        $sql = "SELECT l.userid, COUNT(1) AS eventcount
                  FROM {logstore_standard_log} l
                 WHERE l.courseid = :courseid
                   AND l.userid > 0
                   AND l.timecreated >= :since
                   AND l.eventname <> :courseviewed
              GROUP BY l.userid";
        $rows = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'since' => $since,
            'courseviewed' => self::EVENT_COURSE_VIEWED,
        ]);

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row->userid] = (int)$row->eventcount;
        }

        return $map;
    }

    /**
     * @param int $courseid
     * @return array<int,int>
     */
    private static function get_last_activity_timestamps(int $courseid): array {
        global $DB;

        $sql = "SELECT l.userid, MAX(l.timecreated) AS lastactivity
                  FROM {logstore_standard_log} l
                 WHERE l.courseid = :courseid
                   AND l.userid > 0
                   AND l.eventname <> :courseviewed
              GROUP BY l.userid";

        $rows = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'courseviewed' => self::EVENT_COURSE_VIEWED,
        ]);

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row->userid] = (int)$row->lastactivity;
        }

        return $map;
    }

    /**
     * @param int $courseid
     * @param int[] $studentuserids
     * @param int $totalactivities
     * @param array<string,int|string> $config
     * @return int
     */
    private static function get_course_progress_percent(
        int $courseid,
        array $studentuserids,
        int $totalactivities,
        array $config
    ): int {
        if ($config['risk_course_progress_mode'] === 'graded_completion') {
            return self::get_course_progress_by_completion($courseid, $studentuserids, $totalactivities);
        }

        return self::get_course_progress_by_dates($courseid);
    }

    /**
     * @param int $courseid
     * @return int
     */
    private static function get_course_progress_by_dates(int $courseid): int {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id,startdate,enddate', IGNORE_MISSING);
        if (!$course || empty($course->startdate) || empty($course->enddate) || (int)$course->enddate <= (int)$course->startdate) {
            return 50;
        }

        $now = time();
        $start = (int)$course->startdate;
        $end = (int)$course->enddate;

        if ($now <= $start) {
            return 0;
        }
        if ($now >= $end) {
            return 100;
        }

        $percent = (($now - $start) * 100) / max(1, ($end - $start));
        return max(0, min(100, (int)round($percent)));
    }

    /**
     * @param int $courseid
     * @param int[] $studentuserids
     * @param int $totalactivities
     * @return int
     */
    private static function get_course_progress_by_completion(int $courseid, array $studentuserids, int $totalactivities): int {
        global $DB;

        if ($totalactivities <= 0 || empty($studentuserids)) {
            return 50;
        }

        list($insql, $inparams) = $DB->get_in_or_equal(array_values($studentuserids), SQL_PARAMS_NAMED);
        $sql = "SELECT COUNT(1)
                  FROM {course_modules_completion} c
                  JOIN {course_modules} cm ON cm.id = c.coursemoduleid
                 WHERE cm.course = :courseid
                   AND cm.completion > 0
                   AND c.completionstate <> 0
                   AND c.userid {$insql}";
        $params = ['courseid' => $courseid] + $inparams;

        $completed = (int)$DB->count_records_sql($sql, $params);
        $maxpossible = count($studentuserids) * $totalactivities;
        if ($maxpossible <= 0) {
            return 50;
        }

        $percent = ($completed * 100) / $maxpossible;
        return max(0, min(100, (int)round($percent)));
    }

    /**
     * @param array<int,array> $rows
     * @param int $calculatedat
     * @return array<string,int>
     */
    private static function build_aggregates(array $rows, int $calculatedat): array {
        if (empty($rows)) {
            return self::empty_aggregates();
        }

        $atrisk = 0;
        $critical = 0;
        $completiontotal = 0;

        foreach ($rows as $row) {
            $level = (int)$row['risk_level'];
            if ($level >= self::LEVEL_HIGH) {
                $atrisk++;
            }
            if ($level >= self::LEVEL_CRITICAL) {
                $critical++;
            }
            $completiontotal += (int)$row['completion_percent'];
        }

        return [
            'at_risk_count' => $atrisk,
            'critical_risk_count' => $critical,
            'average_completion_percent' => (int)round($completiontotal / count($rows)),
            'risk_last_calculated' => $calculatedat,
        ];
    }

    /**
     * @return array<string,int>
     */
    private static function empty_aggregates(): array {
        return [
            'at_risk_count' => 0,
            'critical_risk_count' => 0,
            'average_completion_percent' => 0,
            'risk_last_calculated' => time(),
        ];
    }

    /**
     * @param mixed $value
     * @return float|null
     */
    private static function to_nullable_float($value): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        return (float)$value;
    }
}
