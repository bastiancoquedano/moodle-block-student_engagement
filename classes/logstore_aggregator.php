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
 * Incremental logstore aggregation service.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_student_engagement;

defined('MOODLE_INTERNAL') || die();

/**
 * Aggregates standard log events so report caches can read compact local data.
 */
class logstore_aggregator {

    /** @var string Aggregation table name. */
    public const TABLE = 'block_student_engagement_log_agg';

    /** @var string Event name to exclude from interaction counts. */
    private const EVENT_COURSE_VIEWED = '\\core\\event\\course_viewed';

    /**
     * Synchronise new standard log rows into the aggregate table.
     *
     * @param int $courseid
     * @param int $lastlogid Confirmed cursor from the course cache.
     * @return \stdClass Cursor payload with last_log_id, last_log_timecreated and processed_events.
     */
    public static function sync_course(int $courseid, int $lastlogid = 0): \stdClass {
        global $DB;

        $result = (object)[
            'last_log_id' => max(0, $lastlogid),
            'last_log_timecreated' => 0,
            'processed_events' => 0,
        ];

        if ($courseid <= 0) {
            return $result;
        }

        $sql = "SELECT l.id, l.userid, l.timecreated
                  FROM {logstore_standard_log} l
                 WHERE l.courseid = :courseid
                   AND l.id > :lastlogid
                   AND l.userid > 0
                   AND l.eventname <> :courseviewed
              ORDER BY l.id ASC";
        $params = [
            'courseid' => $courseid,
            'lastlogid' => max(0, $lastlogid),
            'courseviewed' => self::EVENT_COURSE_VIEWED,
        ];

        $recordset = $DB->get_recordset_sql($sql, $params);
        $aggregates = [];
        foreach ($recordset as $log) {
            $logid = (int)$log->id;
            $userid = (int)$log->userid;
            $timecreated = (int)$log->timecreated;
            $key = $userid . ':' . $timecreated;

            if (!isset($aggregates[$key])) {
                $existing = $DB->get_record(self::TABLE, [
                    'courseid' => $courseid,
                    'userid' => $userid,
                    'timecreated' => $timecreated,
                ], 'id,event_count,last_log_id', IGNORE_MISSING);

                $aggregates[$key] = [
                    'id' => $existing ? (int)$existing->id : 0,
                    'courseid' => $courseid,
                    'userid' => $userid,
                    'timecreated' => $timecreated,
                    'event_count' => $existing ? (int)$existing->event_count : 0,
                    'last_log_id' => $existing ? (int)$existing->last_log_id : 0,
                    'new_events' => 0,
                ];
            }

            if ($logid <= $aggregates[$key]['last_log_id']) {
                $result->last_log_id = max((int)$result->last_log_id, $logid);
                $result->last_log_timecreated = max((int)$result->last_log_timecreated, $timecreated);
                continue;
            }

            $aggregates[$key]['event_count']++;
            $aggregates[$key]['new_events']++;
            $aggregates[$key]['last_log_id'] = $logid;
            $result->processed_events++;
            $result->last_log_id = max((int)$result->last_log_id, $logid);
            $result->last_log_timecreated = max((int)$result->last_log_timecreated, $timecreated);
        }
        $recordset->close();

        foreach ($aggregates as $aggregate) {
            if ($aggregate['new_events'] <= 0) {
                continue;
            }

            $record = (object)[
                'courseid' => $aggregate['courseid'],
                'userid' => $aggregate['userid'],
                'timecreated' => $aggregate['timecreated'],
                'event_count' => $aggregate['event_count'],
                'last_log_id' => $aggregate['last_log_id'],
            ];

            if ($aggregate['id'] > 0) {
                $record->id = $aggregate['id'];
                $DB->update_record(self::TABLE, $record);
            } else {
                $DB->insert_record(self::TABLE, $record);
            }
        }

        return $result;
    }

    /**
     * Return the highest aggregated log timestamp for a course.
     *
     * @param int $courseid
     * @return int
     */
    public static function get_last_aggregated_timecreated(int $courseid): int {
        global $DB;

        if ($courseid <= 0) {
            return 0;
        }

        $sql = "SELECT MAX(timecreated)
                  FROM {" . self::TABLE . "}
                 WHERE courseid = :courseid";
        return (int)$DB->get_field_sql($sql, ['courseid' => $courseid]);
    }
}
