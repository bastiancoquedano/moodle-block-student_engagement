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
 * Security hardening tests for engagement report SQL handling.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_student_engagement;

defined('MOODLE_INTERNAL') || die();

/**
 * SQL hardening regression tests.
 */
final class engagement_report_security_test extends \advanced_testcase {

    /**
     * Unknown sort tokens must fall back to whitelist defaults.
     *
     * @return void
     */
    public function test_get_sort_sql_rejects_injected_sort_column(): void {
        $sql = engagement_report::get_sort_sql("risklevel DESC, (SELECT 1)", 'DESC', 'all');
        $this->assertSame(
            'risklevel DESC, riskscore DESC, daysinactivevalue DESC, student ASC, studentfirstname ASC',
            $sql
        );
    }

    /**
     * Direction accepts only ASC|DESC and defaults safely for injected payloads.
     *
     * @return void
     */
    public function test_get_sort_sql_rejects_injected_direction(): void {
        $sql = engagement_report::get_sort_sql('student', 'DESC; DROP TABLE user; --', 'all');
        $this->assertSame('student ASC, studentfirstname ASC', $sql);
    }

    /**
     * Row retrieval should remain stable with malicious filter payloads.
     *
     * @return void
     */
    public function test_get_rows_with_malicious_filter_payloads_is_safe(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $rows = engagement_report::get_rows(
            (int)$course->id,
            "risklevel DESC, (SELECT 1)",
            "DESC; DROP TABLE mdl_user; --",
            0,
            10,
            'all',
            [
                'risklevel' => "' OR 1=1 --",
                'groupid' => "1 OR 1=1",
                'status' => "inactive' OR '1'='1",
                'atrisk' => false,
            ]
        );

        $this->assertIsArray($rows);
    }
}

