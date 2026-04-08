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
 * Scheduled task to refresh cached engagement metrics.
 * @Author Bastian Coquedano
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_student_engagement\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Calculates and persists engagement metrics for active courses.
 */
class calculate_engagement extends \core\task\scheduled_task {

    /**
     * Return task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_calculate_engagement', 'block_student_engagement');
    }

    /**
     * Execute the scheduled task.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        \core_php_time_limit::raise();

        $now = time();
        $select = 'id <> :siteid AND visible = :visible AND (enddate = 0 OR enddate >= :now)';
        $params = [
            'siteid' => SITEID,
            'visible' => 1,
            'now' => $now,
        ];

        $recordset = $DB->get_recordset_select('course', $select, $params, 'id ASC', 'id, fullname');

        foreach ($recordset as $course) {
            $transaction = null;
            try {
                $courseid = (int)$course->id;
                $cache = \block_student_engagement\cache_manager::get_course_cache($courseid);
                $lastlogid = $cache ? (int)($cache->last_log_id ?? 0) : 0;
                $lastlogtimecreated = $cache ? (int)($cache->last_log_timecreated ?? 0) : 0;

                $transaction = $DB->start_delegated_transaction();

                $logcursor = \block_student_engagement\logstore_aggregator::sync_course($courseid, $lastlogid);
                $payload = \block_student_engagement\engagement_analyser::analyse_course($courseid);
                $payload->last_log_id = (int)$logcursor->last_log_id;
                $payload->last_log_timecreated = ((int)$logcursor->last_log_timecreated > 0)
                    ? (int)$logcursor->last_log_timecreated
                    : $lastlogtimecreated;

                if ($this->is_risk_enabled()) {
                    $riskresult = \block_student_engagement\local\risk_analyser::analyse_course($courseid);
                    \block_student_engagement\local\risk_analyser::upsert_course_risk($courseid, $riskresult['rows']);
                    $payload->at_risk_count = (int)$riskresult['aggregates']['at_risk_count'];
                    $payload->critical_risk_count = (int)$riskresult['aggregates']['critical_risk_count'];
                    $payload->average_completion_percent = (int)$riskresult['aggregates']['average_completion_percent'];
                    $payload->risk_last_calculated = (int)$riskresult['aggregates']['risk_last_calculated'];
                }

                \block_student_engagement\cache_manager::save_course_engagement($payload);
                $transaction->allow_commit();
                $transaction = null;
                mtrace('Updated engagement cache for course ' . (int)$course->id . ': ' . $course->fullname);
            } catch (\Throwable $exception) {
                if ($transaction !== null) {
                    try {
                        $transaction->rollback($exception);
                    } catch (\Throwable $rollbackexception) {
                        $exception = $rollbackexception;
                    }
                }
                // Continue processing the remaining courses even if one course fails.
                mtrace(
                    'Failed to update engagement cache for course ' . (int)$course->id . ': ' .
                    $exception->getMessage()
                );
            }
        }

        $recordset->close();
    }

    /**
     * Return whether risk calculation is enabled.
     *
     * @return bool
     */
    private function is_risk_enabled(): bool {
        $enabled = get_config('block_student_engagement', 'risk_enabled');
        return !empty($enabled);
    }
}
