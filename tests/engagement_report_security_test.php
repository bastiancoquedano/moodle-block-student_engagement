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
     * Invoke private static method on engagement_report via reflection.
     *
     * @param string $method
     * @param mixed ...$args
     * @return mixed
     */
    private function invoke_private_static(string $method, ...$args) {
        $refclass = new \ReflectionClass(engagement_report::class);
        $refmethod = $refclass->getMethod($method);
        $refmethod->setAccessible(true);
        return $refmethod->invoke(null, ...$args);
    }

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

    /**
     * Risk-level predicates should avoid COALESCE over indexed columns.
     *
     * @return void
     */
    public function test_build_shared_sql_parts_uses_sargable_risk_level_predicates(): void {
        $filters = $this->invoke_private_static('normalise_filters', 'all', ['atrisk' => true]);
        $parts = $this->invoke_private_static('build_shared_sql_parts', 123, $filters);

        $this->assertStringNotContainsString('COALESCE(risk.risk_level, 0)', $parts['where']);
        $this->assertStringContainsString('risk.risk_level >= :risklevelmin', $parts['where']);
    }

    /**
     * Risk level zero filter must include rows without risk cache.
     *
     * @return void
     */
    public function test_build_shared_sql_parts_risklevel_zero_keeps_null_semantics(): void {
        $filters = $this->invoke_private_static('normalise_filters', 'all', ['risklevel' => '0']);
        $parts = $this->invoke_private_static('build_shared_sql_parts', 123, $filters);

        $this->assertStringContainsString('(risk.risk_level = :risklevel OR risk.risk_level IS NULL)', $parts['where']);
        $this->assertSame(0, $parts['params']['risklevel']);
    }

    /**
     * Non-zero risk-level filters should use direct predicates.
     *
     * @return void
     */
    public function test_build_shared_sql_parts_nonzero_risklevels_use_direct_predicates(): void {
        foreach ([1, 2, 3] as $risklevel) {
            $filters = $this->invoke_private_static('normalise_filters', 'all', ['risklevel' => (string)$risklevel]);
            $parts = $this->invoke_private_static('build_shared_sql_parts', 123, $filters);

            $this->assertStringContainsString('risk.risk_level = :risklevel', $parts['where']);
            $this->assertStringNotContainsString('risk.risk_level IS NULL', $parts['where']);
            $this->assertStringNotContainsString('COALESCE(risk.risk_level, 0)', $parts['where']);
            $this->assertSame($risklevel, $parts['params']['risklevel']);
        }
    }

    /**
     * Active status should use cached inactivity before calculated fallback.
     *
     * @return void
     */
    public function test_build_shared_sql_parts_active_status_limits_floor_to_uncached_fallback(): void {
        $filters = $this->invoke_private_static('normalise_filters', 'all', ['status' => 'active']);
        $parts = $this->invoke_private_static('build_shared_sql_parts', 123, $filters);

        $this->assertStringContainsString('logsummary.lastcourseaccess > 0', $parts['where']);
        $this->assertStringContainsString('risk.days_inactive < :inactivedaysthreshold', $parts['where']);
        $this->assertStringContainsString('risk.days_inactive IS NULL', $parts['where']);
        $this->assertStringContainsString('FLOOR(', $parts['where']);
        $this->assertStringContainsString(':currenttimeactive - logsummary.lastcourseaccess', $parts['where']);
        $this->assertStringContainsString('/ :daysecsactive', $parts['where']);
        $this->assertStringNotContainsString('COALESCE(', $parts['where']);
    }

    /**
     * Inactive status should use cached inactivity before calculated fallback.
     *
     * @return void
     */
    public function test_build_shared_sql_parts_inactive_status_limits_floor_to_uncached_fallback(): void {
        $filters = $this->invoke_private_static('normalise_filters', 'all', ['status' => 'inactive']);
        $parts = $this->invoke_private_static('build_shared_sql_parts', 123, $filters);

        $this->assertStringContainsString('logsummary.lastcourseaccess IS NULL', $parts['where']);
        $this->assertStringContainsString('logsummary.lastcourseaccess = 0', $parts['where']);
        $this->assertStringContainsString('risk.days_inactive >= :inactivedaysthreshold', $parts['where']);
        $this->assertStringContainsString('risk.days_inactive IS NULL', $parts['where']);
        $this->assertStringContainsString(
            'FLOOR((:currenttimeinactive - logsummary.lastcourseaccess) / :daysecsinactive)',
            $parts['where']
        );
        $this->assertStringNotContainsString('COALESCE(', $parts['where']);
    }

    /**
     * Group filters should compose with risk and status predicates.
     *
     * @return void
     */
    public function test_build_shared_sql_parts_group_filter_composes_with_risk_and_status(): void {
        $filters = $this->invoke_private_static('normalise_filters', 'all', [
            'groupid' => '42',
            'risklevel' => '2',
            'status' => 'inactive',
        ]);
        $parts = $this->invoke_private_static('build_shared_sql_parts', 123, $filters);

        $this->assertStringContainsString('FROM {groups_members} gm', $parts['where']);
        $this->assertStringContainsString('gm.userid = students.userid', $parts['where']);
        $this->assertStringContainsString('gm.groupid = :groupid', $parts['where']);
        $this->assertStringContainsString('risk.risk_level = :risklevel', $parts['where']);
        $this->assertStringContainsString('risk.days_inactive >= :inactivedaysthreshold', $parts['where']);
        $this->assertSame(42, $parts['params']['groupid']);
        $this->assertSame(2, $parts['params']['risklevel']);
    }

    /**
     * Log table should be scanned only once in shared SQL parts.
     *
     * @return void
     */
    public function test_build_shared_sql_parts_queries_logstore_only_once(): void {
        $filters = $this->invoke_private_static('normalise_filters', 'all', []);
        $parts = $this->invoke_private_static('build_shared_sql_parts', 123, $filters);

        $occurrences = substr_count($parts['from'], '{logstore_standard_log}');
        $this->assertSame(1, $occurrences);
        $this->assertStringContainsString('COUNT(1) AS eventcount', $parts['from']);
        $this->assertStringContainsString('MAX(l.timecreated) AS lastcourseaccess', $parts['from']);
    }
}
