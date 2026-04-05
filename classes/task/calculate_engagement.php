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
            try {
                $payload = \block_student_engagement\engagement_analyser::analyse_course((int)$course->id);

                if ($this->is_risk_enabled()) {
                    $riskresult = \block_student_engagement\local\risk_analyser::analyse_course((int)$course->id);
                    \block_student_engagement\local\risk_analyser::upsert_course_risk((int)$course->id, $riskresult['rows']);
                    $payload->at_risk_count = (int)$riskresult['aggregates']['at_risk_count'];
                    $payload->critical_risk_count = (int)$riskresult['aggregates']['critical_risk_count'];
                    $payload->average_completion_percent = (int)$riskresult['aggregates']['average_completion_percent'];
                    $payload->risk_last_calculated = (int)$riskresult['aggregates']['risk_last_calculated'];
                }

                \block_student_engagement\cache_manager::save_course_engagement($payload);
                mtrace('Updated engagement cache for course ' . (int)$course->id . ': ' . $course->fullname);
            } catch (\Throwable $exception) {
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
