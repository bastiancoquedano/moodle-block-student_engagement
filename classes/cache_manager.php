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
 * Cache manager for Student Engagement metrics.
 *
 * This class encapsulates all persistence concerns for cached engagement
 * metrics by course, so block rendering can stay fast and predictable.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_student_engagement;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper for reading/writing the engagement cache table.
 */
class cache_manager {

    /** @var string The cache table name (without prefix). */
    private const TABLE = 'block_student_engagement_cache';

    /**
     * Insert or update cached engagement metrics for a course.
     *
     * Expected keys/props (minimum): courseid.
     * Optional: active_students, inactive_students, most_active_userid,
     * most_active_interactions, inactive_userids (array|string), last_calculated,
     * at_risk_count, critical_risk_count, average_completion_percent, risk_last_calculated.
     *
     * @param array|\stdClass $data
     * @return int The record id (inserted or existing).
     */
    public static function save_course_engagement($data): int {
        global $DB;

        $record = self::normalise_record($data);

        $existing = $DB->get_record(self::TABLE, ['courseid' => $record->courseid], 'id', IGNORE_MISSING);
        if ($existing) {
            $record->id = (int)$existing->id;
            $DB->update_record(self::TABLE, $record);
            return $record->id;
        }

        return (int)$DB->insert_record(self::TABLE, $record);
    }

    /**
     * Get the cached engagement record for a course.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function get_course_cache(int $courseid): ?\stdClass {
        global $DB;

        $courseid = (int)$courseid;
        if ($courseid <= 0) {
            return null;
        }

        return $DB->get_record(self::TABLE, ['courseid' => $courseid], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * Get all cached engagement records.
     *
     * @return array<int,\stdClass> Records keyed by id.
     */
    public static function get_all_cached_courses(): array {
        global $DB;

        return $DB->get_records(self::TABLE, null, 'courseid ASC');
    }

    /**
     * Clear cache records.
     *
     * @param int|null $courseid If null, clears the whole cache table.
     * @return void
     */
    public static function clear_cache(?int $courseid = null): void {
        global $DB;

        if ($courseid === null) {
            $DB->delete_records(self::TABLE);
            return;
        }

        $courseid = (int)$courseid;
        if ($courseid <= 0) {
            return;
        }

        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }

    /**
     * Convert input to a normalised record ready to persist.
     *
     * @param array|\stdClass $data
     * @return \stdClass
     */
    private static function normalise_record($data): \stdClass {
        if (is_array($data)) {
            $data = (object)$data;
        }

        if (!($data instanceof \stdClass) || empty($data->courseid)) {
            throw new \coding_exception('Missing required field: courseid');
        }

        $record = new \stdClass();
        $record->courseid = (int)$data->courseid;
        $record->active_students = isset($data->active_students) ? (int)$data->active_students : 0;
        $record->inactive_students = isset($data->inactive_students) ? (int)$data->inactive_students : 0;
        $record->most_active_userid = isset($data->most_active_userid) ? (int)$data->most_active_userid : 0;
        $record->most_active_interactions = isset($data->most_active_interactions) ? (int)$data->most_active_interactions : 0;
        $record->at_risk_count = isset($data->at_risk_count) ? (int)$data->at_risk_count : 0;
        $record->critical_risk_count = isset($data->critical_risk_count) ? (int)$data->critical_risk_count : 0;
        $record->average_completion_percent = isset($data->average_completion_percent) ? (int)$data->average_completion_percent : 0;
        $record->risk_last_calculated = isset($data->risk_last_calculated) ? (int)$data->risk_last_calculated : 0;

        if (property_exists($data, 'inactive_userids')) {
            if (is_array($data->inactive_userids)) {
                $userids = array_values(array_map('intval', $data->inactive_userids));
                $record->inactive_userids = json_encode($userids);
            } else if ($data->inactive_userids === null || $data->inactive_userids === '') {
                $record->inactive_userids = null;
            } else {
                $record->inactive_userids = (string)$data->inactive_userids;
            }
        } else {
            $record->inactive_userids = null;
        }

        $record->last_calculated = isset($data->last_calculated) ? (int)$data->last_calculated : time();

        return $record;
    }
}
