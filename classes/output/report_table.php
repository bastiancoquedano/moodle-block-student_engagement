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
 * Participation report table.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_student_engagement\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * Table renderer for the course participation report.
 */
class report_table extends \table_sql {

    /** @var int */
    private $courseid;
    /** @var string */
    private $viewmode;

    /**
     * Constructor.
     *
     * @param int $courseid
     * @param \moodle_url $baseurl
     * @param string $viewmode
     */
    public function __construct(int $courseid, \moodle_url $baseurl, string $viewmode = 'all') {
        parent::__construct('block-student-engagement-report-' . $courseid);

        $this->courseid = $courseid;
        $this->viewmode = ($viewmode === 'inactive') ? 'inactive' : 'all';
        $isinactiveview = ($this->viewmode === 'inactive');
        $thresholdvalue = get_config('block_student_engagement', 'inactive_days_threshold');
        $inactivedays = ($thresholdvalue === false || $thresholdvalue === null || $thresholdvalue === '')
            ? 14
            : (int)$thresholdvalue;
        $inactivedays = max(0, $inactivedays);

        $columns = ['student', 'eventcount', 'completedcount', 'engagementscore'];
        $headers = [
            get_string('report_student', 'block_student_engagement'),
            get_string('report_events', 'block_student_engagement'),
            get_string('report_completed', 'block_student_engagement'),
            get_string('report_score', 'block_student_engagement'),
        ];
        if ($isinactiveview) {
            $columns = ['student', 'daysinactive', 'lastaccess'];
            $headers = [
                get_string('report_student', 'block_student_engagement'),
                get_string('report_days_inactive', 'block_student_engagement'),
                get_string('report_last_course_access', 'block_student_engagement'),
            ];
        }
        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_baseurl($baseurl);

        $this->column_class('student', 'col-student');
        if ($isinactiveview) {
            $this->column_class('daysinactive', 'col-days-inactive');
            $this->column_class('lastaccess', 'col-last-access');
        }
        if (!$isinactiveview) {
            $this->column_class('eventcount', 'col-events');
            $this->column_class('completedcount', 'col-completed');
            $this->column_class('engagementscore', 'col-score');
        }

        $defaultsort = $isinactiveview ? 'daysinactive' : 'engagementscore';
        $defaultsortdir = SORT_DESC;
        $this->sortable(true, $defaultsort, $defaultsortdir);
        $this->set_attribute('class', 'generaltable');
        $this->collapsible(false);
        $this->pageable(true);
        $this->pagesize(25, 0);
        if ($isinactiveview) {
            $this->set_count_sql(
                "SELECT COUNT(DISTINCT ra.userid)
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
                    AND (activity.lastactivity IS NULL OR activity.lastactivity < :inactivesince)",
                [
                    'activitycourseid' => $courseid,
                    'courseviewed' => '\\core\\event\\course_viewed',
                    'contextcourse' => CONTEXT_COURSE,
                    'courseid' => $courseid,
                    'studentshortname' => 'student',
                    'inactivesince' => time() - ($inactivedays * DAYSECS),
                ]
            );
        } else {
            $this->set_count_sql(
                "SELECT COUNT(DISTINCT ra.userid)
                   FROM {role_assignments} ra
                   JOIN {context} ctx ON ctx.id = ra.contextid
                   JOIN {role} r ON r.id = ra.roleid
                   JOIN {user} u ON u.id = ra.userid
                  WHERE ctx.contextlevel = :contextcourse
                    AND ctx.instanceid = :courseid
                    AND r.shortname = :studentshortname
                    AND u.deleted = 0",
                [
                    'contextcourse' => CONTEXT_COURSE,
                    'courseid' => $courseid,
                    'studentshortname' => 'student',
                ]
            );
        }
        $this->set_sql('', '', '', []);
    }

    /**
     * Query table data.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        $total = \block_student_engagement\engagement_report::count_rows($this->courseid, $this->viewmode);
        if (!$this->is_downloading()) {
            $this->pagesize($pagesize, $total);
        }

        $sort = $this->get_sql_sort();
        $direction = 'ASC';
        $sortcolumn = 'student';
        if (!empty($sort)) {
            $parts = preg_split('/\s+/', trim($sort));
            $sortcolumn = $parts[0] ?? 'student';
            $direction = strtoupper($parts[1] ?? 'ASC');
        }

        $ordersql = \block_student_engagement\engagement_report::get_sort_sql($sortcolumn, $direction, $this->viewmode);
        $this->rawdata = \block_student_engagement\engagement_report::get_rows(
            $this->courseid,
            $ordersql,
            $this->get_page_start(),
            $this->get_page_size(),
            $this->viewmode
        );
    }

    /**
     * Row CSS class based on score level.
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class($row) {
        return 'engagement-level-' . $row->engagementlevel;
    }

    /**
     * Render student column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_student($row): string {
        return \html_writer::link(
            $row->profileurl,
            s($row->studentname),
            ['class' => 'student-link']
        );
    }

    /**
     * Render days inactive column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_daysinactive($row): string {
        if ($row->daysinactive === null) {
            return '-';
        }

        return (string)(int)$row->daysinactive;
    }

    /**
     * Render last access column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_lastaccess($row): string {
        if (empty($row->lastaccesstimestamp)) {
            return get_string('report_never', 'block_student_engagement');
        }

        return userdate((int)$row->lastaccesstimestamp);
    }

    /**
     * Render events column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_eventcount($row): string {
        return (int)$row->eventcount . ' (' . (int)$row->eventprogress . '%)';
    }

    /**
     * Render completion column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_completedcount($row): string {
        return (int)$row->completedcount . ' / ' . (int)$row->totalactivities .
            ' (' . (int)$row->completedprogress . '%)';
    }

    /**
     * Render score column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_engagementscore($row): string {
        $classes = 'engagement-pill engagement-pill--' . $row->engagementlevel;
        return \html_writer::span((int)$row->engagementscore . ' / 100', $classes);
    }
}
