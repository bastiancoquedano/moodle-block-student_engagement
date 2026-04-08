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
 * Incremental logstore aggregation tests.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_student_engagement;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression tests for incremental logstore cache aggregation.
 */
final class logstore_aggregator_test extends \advanced_testcase {

    /**
     * Initial sync should aggregate valid logs and skip course_viewed noise.
     *
     * @return void
     */
    public function test_sync_course_aggregates_initial_logs_and_skips_course_viewed(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user] = $this->create_course_student();
        $time = time() - HOURSECS;

        $firstid = $this->insert_standard_log((int)$course->id, (int)$user->id, $time);
        $secondid = $this->insert_standard_log((int)$course->id, (int)$user->id, $time);
        $this->insert_standard_log((int)$course->id, (int)$user->id, $time, '\\core\\event\\course_viewed');

        $cursor = logstore_aggregator::sync_course((int)$course->id, 0);
        $aggregates = $DB->get_records(logstore_aggregator::TABLE, ['courseid' => (int)$course->id]);

        $this->assertSame(2, (int)$cursor->processed_events);
        $this->assertSame($secondid, (int)$cursor->last_log_id);
        $this->assertSame($time, (int)$cursor->last_log_timecreated);
        $this->assertCount(1, $aggregates);

        $aggregate = reset($aggregates);
        $this->assertSame((int)$user->id, (int)$aggregate->userid);
        $this->assertSame($time, (int)$aggregate->timecreated);
        $this->assertSame(2, (int)$aggregate->event_count);
        $this->assertSame($secondid, (int)$aggregate->last_log_id);

        $this->assertGreaterThanOrEqual($firstid, (int)$aggregate->last_log_id);
    }

    /**
     * Reprocessing with a stale cursor should not duplicate aggregate counts.
     *
     * @return void
     */
    public function test_sync_course_is_idempotent_when_cursor_is_stale(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user] = $this->create_course_student();
        $time = time() - HOURSECS;

        $this->insert_standard_log((int)$course->id, (int)$user->id, $time);
        $lastid = $this->insert_standard_log((int)$course->id, (int)$user->id, $time);

        logstore_aggregator::sync_course((int)$course->id, 0);
        $cursor = logstore_aggregator::sync_course((int)$course->id, 0);

        $aggregate = $DB->get_record(logstore_aggregator::TABLE, ['courseid' => (int)$course->id], '*', MUST_EXIST);
        $this->assertSame(0, (int)$cursor->processed_events);
        $this->assertSame($lastid, (int)$cursor->last_log_id);
        $this->assertSame($time, (int)$cursor->last_log_timecreated);
        $this->assertSame(2, (int)$aggregate->event_count);
        $this->assertSame($lastid, (int)$aggregate->last_log_id);
    }

    /**
     * Engagement cache should read activity windows from aggregate rows.
     *
     * @return void
     */
    public function test_engagement_analyser_reads_activity_windows_from_aggregates(): void {
        $this->resetAfterTest(true);
        [$course, $activeuser] = $this->create_course_student();
        $inactiveuser = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int)$inactiveuser->id, (int)$course->id, 'student');

        set_config('active_days_threshold', 7, 'block_student_engagement');
        set_config('inactive_days_threshold', 14, 'block_student_engagement');

        $recenttime = time() - DAYSECS;
        $oldtime = time() - (30 * DAYSECS);
        $this->insert_standard_log((int)$course->id, (int)$activeuser->id, $recenttime);
        $this->insert_standard_log((int)$course->id, (int)$activeuser->id, $recenttime);
        $this->insert_standard_log((int)$course->id, (int)$inactiveuser->id, $oldtime);
        logstore_aggregator::sync_course((int)$course->id, 0);

        $payload = engagement_analyser::analyse_course((int)$course->id);

        $this->assertSame(1, (int)$payload->active_students);
        $this->assertSame(1, (int)$payload->inactive_students);
        $this->assertSame((int)$activeuser->id, (int)$payload->most_active_userid);
        $this->assertSame(2, (int)$payload->most_active_interactions);
    }

    /**
     * Risk analyser should keep last activity derived from aggregate rows.
     *
     * @return void
     */
    public function test_risk_analyser_uses_aggregated_last_activity(): void {
        $this->resetAfterTest(true);
        [$course, $user] = $this->create_course_student();
        $time = time() - DAYSECS;

        $this->insert_standard_log((int)$course->id, (int)$user->id, $time);
        logstore_aggregator::sync_course((int)$course->id, 0);

        $result = \block_student_engagement\local\risk_analyser::analyse_course((int)$course->id);

        $this->assertNotEmpty($result['rows']);
        $row = reset($result['rows']);
        $this->assertSame((int)$user->id, (int)$row['userid']);
        $this->assertSame($time, (int)$row['last_activity_timecreated']);
        $this->assertGreaterThanOrEqual(0, (int)$row['days_inactive']);
    }

    /**
     * Cache persistence should keep the confirmed log cursor.
     *
     * @return void
     */
    public function test_cache_manager_persists_log_cursor(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();

        cache_manager::save_course_engagement((object)[
            'courseid' => (int)$course->id,
            'last_log_id' => 123,
            'last_log_timecreated' => 456,
        ]);

        $cache = cache_manager::get_course_cache((int)$course->id);
        $this->assertNotNull($cache);
        $this->assertSame(123, (int)$cache->last_log_id);
        $this->assertSame(456, (int)$cache->last_log_timecreated);
    }

    /**
     * Rollback should keep aggregates and confirmed cursor unchanged.
     *
     * @return void
     */
    public function test_transaction_rollback_keeps_cursor_unconfirmed(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user] = $this->create_course_student();
        $this->insert_standard_log((int)$course->id, (int)$user->id, time() - HOURSECS);

        $transaction = $DB->start_delegated_transaction();
        try {
            $cursor = logstore_aggregator::sync_course((int)$course->id, 0);
            cache_manager::save_course_engagement((object)[
                'courseid' => (int)$course->id,
                'last_log_id' => (int)$cursor->last_log_id,
                'last_log_timecreated' => (int)$cursor->last_log_timecreated,
            ]);
            throw new \coding_exception('Simulated failure after aggregation');
        } catch (\Throwable $exception) {
            try {
                $transaction->rollback($exception);
            } catch (\Throwable $rollbackexception) {
                // Moodle rethrows the original exception after rolling back.
            }
        }

        $this->assertFalse($DB->record_exists(logstore_aggregator::TABLE, ['courseid' => (int)$course->id]));
        $this->assertNull(cache_manager::get_course_cache((int)$course->id));
    }

    /**
     * Create a course and one enrolled student.
     *
     * @return array{\stdClass,\stdClass}
     */
    private function create_course_student(): array {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int)$user->id, (int)$course->id, 'student');

        return [$course, $user];
    }

    /**
     * Insert a minimal standard log row.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $timecreated
     * @param string $eventname
     * @return int
     */
    private function insert_standard_log(
        int $courseid,
        int $userid,
        int $timecreated,
        string $eventname = '\\mod_forum\\event\\course_module_viewed'
    ): int {
        global $DB;

        $context = \context_course::instance($courseid);
        $record = (object)[
            'eventname' => $eventname,
            'component' => 'mod_forum',
            'action' => 'viewed',
            'target' => 'course_module',
            'objecttable' => null,
            'objectid' => null,
            'crud' => 'r',
            'edulevel' => 2,
            'contextid' => $context->id,
            'contextlevel' => CONTEXT_COURSE,
            'contextinstanceid' => $courseid,
            'userid' => $userid,
            'courseid' => $courseid,
            'relateduserid' => null,
            'anonymous' => 0,
            'other' => null,
            'timecreated' => $timecreated,
            'origin' => 'cli',
            'ip' => null,
            'realuserid' => null,
        ];

        return (int)$DB->insert_record('logstore_standard_log', $record);
    }
}
