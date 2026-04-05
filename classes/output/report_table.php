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
 * @Author Bastian Coquedano
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
    /** @var array */
    private $filters;
    /** @var bool */
    private $legacyinactiveview;

    /**
     * Determine whether legacy inactive view applies.
     *
     * @param string $viewmode
     * @param array $filters
     * @return bool
     */
    public static function is_legacy_inactive_view(string $viewmode, array $filters): bool {
        $normalisedview = ($viewmode === 'inactive') ? 'inactive' : 'all';
        // Treat any explicit risk-level selection as a custom filter, even if it comes via URL aliasing.
        $riskfilteractive = isset($filters['risklevel']) &&
            $filters['risklevel'] !== '' &&
            $filters['risklevel'] !== null &&
            $filters['risklevel'] !== 'all';
        $hascustomfilters = $riskfilteractive || !empty($filters['groupid']) ||
            !empty($filters['datefrom']) || !empty($filters['dateto']) ||
            !empty($filters['atrisk']) ||
            (!empty($filters['status']) && $filters['status'] !== 'all');

        return ($normalisedview === 'inactive' && !$hascustomfilters);
    }

    /**
     * Export headers in display order.
     *
     * @param bool $legacyinactiveview
     * @return array
     */
    public static function get_export_headers(bool $legacyinactiveview): array {
        if ($legacyinactiveview) {
            return [
                get_string('report_student', 'block_student_engagement'),
                get_string('report_days_inactive', 'block_student_engagement'),
                get_string('report_last_course_access', 'block_student_engagement'),
            ];
        }

        return [
            get_string('report_student', 'block_student_engagement'),
            get_string('report_last_course_access', 'block_student_engagement'),
            get_string('report_days_inactive', 'block_student_engagement'),
            get_string('report_recent_events', 'block_student_engagement'),
            get_string('report_completed', 'block_student_engagement'),
            get_string('report_current_grade', 'block_student_engagement'),
            get_string('report_pass_grade', 'block_student_engagement'),
            get_string('report_grade_gap', 'block_student_engagement'),
            get_string('report_score', 'block_student_engagement'),
            get_string('report_risk_score', 'block_student_engagement'),
            get_string('report_risk_level', 'block_student_engagement'),
            get_string('report_risk_flags', 'block_student_engagement'),
        ];
    }

    /**
     * Format a data row for CSV export.
     *
     * @param \stdClass $row
     * @param bool $legacyinactiveview
     * @return array
     */
    public static function format_export_row(\stdClass $row, bool $legacyinactiveview): array {
        if ($legacyinactiveview) {
            return [
                self::sanitize_spreadsheet_text((string)$row->studentname),
                self::format_days_inactive($row),
                self::sanitize_spreadsheet_text(self::format_last_access($row)),
            ];
        }

        return [
            self::sanitize_spreadsheet_text((string)$row->studentname),
            self::sanitize_spreadsheet_text(self::format_last_access($row)),
            self::format_days_inactive($row),
            (string)(int)$row->recentevents,
            (string)((int)$row->completedcount . ' / ' . (int)$row->totalactivities . ' (' . (int)$row->completedprogress . '%)'),
            self::format_nullable_grade($row->currentgrade),
            self::format_nullable_grade($row->passgrade),
            self::format_nullable_grade($row->gradegap),
            (string)((int)$row->engagementscore . ' / 100'),
            (string)((int)$row->riskscore . ' / 100'),
            self::sanitize_spreadsheet_text(get_string('risk_level_label_' . (int)$row->risklevel, 'block_student_engagement')),
            self::sanitize_spreadsheet_text(self::format_risk_flags_text($row)),
        ];
    }

    /**
     * Neutralize spreadsheet formula prefixes in exported text cells.
     *
     * @param string $value
     * @return string
     */
    public static function sanitize_spreadsheet_text(string $value): string {
        $value = (string)$value;
        if ($value === '') {
            return $value;
        }

        // Ignore leading whitespace/control chars when deciding if a cell could be interpreted as a formula.
        $leadingtrimmed = ltrim($value, " \t\r\n");
        if ($leadingtrimmed === '') {
            return $value;
        }

        $firstchar = \core_text::substr($leadingtrimmed, 0, 1);
        if (in_array($firstchar, ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Constructor.
     *
     * @param int $courseid
     * @param \moodle_url $baseurl
     * @param string $viewmode
     * @param array $filters
     */
    public function __construct(int $courseid, \moodle_url $baseurl, string $viewmode = 'all', array $filters = []) {
        parent::__construct('block-student-engagement-report-' . $courseid);

        $this->courseid = $courseid;
        $this->viewmode = ($viewmode === 'inactive') ? 'inactive' : 'all';
        $this->filters = $filters;

        // Preserve old "inactive report" layout only when no extra filters are active.
        $this->legacyinactiveview = self::is_legacy_inactive_view($this->viewmode, $filters);

        if ($this->legacyinactiveview) {
            $this->define_columns(['student', 'daysinactive', 'lastaccess']);
            $this->define_headers([
                get_string('report_student', 'block_student_engagement'),
                get_string('report_days_inactive', 'block_student_engagement'),
                get_string('report_last_course_access', 'block_student_engagement'),
            ]);
        } else {
            $this->define_columns([
                'student',
                'lastaccess',
                'daysinactive',
                'recentevents',
                'completedcount',
                'currentgrade',
                'passgrade',
                'gradegap',
                'engagementscore',
                'riskscore',
                'risklevel',
                'riskflags',
            ]);
            $this->define_headers([
                get_string('report_student', 'block_student_engagement'),
                get_string('report_last_course_access', 'block_student_engagement'),
                get_string('report_days_inactive', 'block_student_engagement'),
                get_string('report_recent_events', 'block_student_engagement'),
                get_string('report_completed', 'block_student_engagement'),
                get_string('report_current_grade', 'block_student_engagement'),
                get_string('report_pass_grade', 'block_student_engagement'),
                get_string('report_grade_gap', 'block_student_engagement'),
                get_string('report_score', 'block_student_engagement'),
                get_string('report_risk_score', 'block_student_engagement'),
                get_string('report_risk_level', 'block_student_engagement'),
                get_string('report_risk_flags', 'block_student_engagement'),
            ]);
        }

        $this->define_baseurl($baseurl);

        $defaultsort = $this->legacyinactiveview ? 'daysinactive' : 'risklevel';
        $defaultsortdir = SORT_DESC;
        $this->sortable(true, $defaultsort, $defaultsortdir);

        $this->set_attribute('class', 'generaltable');
        $this->collapsible(false);
        $this->pageable(true);
        $this->pagesize(25, 0);
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
        $total = \block_student_engagement\engagement_report::count_rows($this->courseid, $this->viewmode, $this->filters);
        if (!$this->is_downloading()) {
            $this->pagesize($pagesize, $total);
        }

        $sort = $this->get_sql_sort();
        $direction = 'ASC';
        $sortcolumn = 'student';
        if (!empty($sort)) {
            // Moodle returns expressions like "risklevel DESC"; split defensively to keep safe defaults.
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
            $this->viewmode,
            $this->filters
        );
    }

    /**
     * Row CSS class based on risk level.
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class($row) {
        if ($this->legacyinactiveview) {
            return '';
        }

        return 'risk-level-' . (int)$row->risklevel;
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
     * Render recent events column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_recentevents($row): string {
        return (int)$row->recentevents;
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
     * Render current grade column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_currentgrade($row): string {
        return ($row->currentgrade === null) ? '-' : format_float((float)$row->currentgrade, 2);
    }

    /**
     * Render pass grade column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_passgrade($row): string {
        return ($row->passgrade === null) ? '-' : format_float((float)$row->passgrade, 2);
    }

    /**
     * Render grade gap column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_gradegap($row): string {
        return ($row->gradegap === null) ? '-' : format_float((float)$row->gradegap, 2);
    }

    /**
     * Render engagement score column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_engagementscore($row): string {
        $classes = 'engagement-pill engagement-pill--' . $row->engagementlevel;
        return \html_writer::span((int)$row->engagementscore . ' / 100', $classes);
    }

    /**
     * Render risk score column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_riskscore($row): string {
        return \html_writer::div((int)$row->riskscore . ' / 100', 'risk-score-cell');
    }

    /**
     * Render risk level column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_risklevel($row): string {
        $level = (int)$row->risklevel;
        $label = get_string('risk_level_label_' . $level, 'block_student_engagement');
        $classes = 'risk-pill risk-pill--' . $level;

        return \html_writer::div(
            \html_writer::span(s($label), $classes),
            'risk-level-cell'
        );
    }

    /**
     * Render risk flags column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_riskflags($row): string {
        if (empty($row->riskflags) || !is_array($row->riskflags)) {
            return '-';
        }

        $items = [];
        foreach ($row->riskflags as $flag) {
            $label = self::resolve_risk_flag_label((string)$flag);
            $tone = self::resolve_risk_flag_tone((string)$flag);
            $items[] = \html_writer::span(s($label), 'risk-flag-pill risk-flag-pill--' . $tone);
        }

        return \html_writer::div(implode('', $items), 'risk-flag-list');
    }

    /**
     * Resolve visual tone for a risk flag badge.
     *
     * @param string $flag
     * @return string
     */
    private static function resolve_risk_flag_tone(string $flag): string {
        $flag = self::normalise_risk_flag_key($flag);
        $highriskflags = ['below_pass_grade', 'inactive', 'behind_expected_progress', 'low_grade', 'inactivity'];
        $mediumriskflags = ['low_completion', 'low_recent_activity', 'low_participation'];

        if (in_array($flag, $highriskflags, true)) {
            return 'low';
        }
        if (in_array($flag, $mediumriskflags, true)) {
            return 'medium';
        }

        return 'high';
    }

    /**
     * Resolve translated label for a risk flag key.
     *
     * @param string $flag
     * @return string
     */
    private static function resolve_risk_flag_label(string $flag): string {
        $flag = self::normalise_risk_flag_key($flag);
        // Backward compatibility for previously used keys and renamed labels.
        $aliases = [
            'low_grade' => 'below_pass_grade',
            'inactivity' => 'inactive',
            'low_participation' => 'low_recent_activity',
        ];
        $canonicalflag = $aliases[$flag] ?? $flag;
        $key = 'risk_flag_' . $canonicalflag;

        if (get_string_manager()->string_exists($key, 'block_student_engagement')) {
            return get_string($key, 'block_student_engagement');
        }

        return ucwords(str_replace('_', ' ', $canonicalflag));
    }

    /**
     * Normalize risk flag key variants.
     *
     * @param string $flag
     * @return string
     */
    private static function normalise_risk_flag_key(string $flag): string {
        $flag = trim(\core_text::strtolower($flag));
        $flag = str_replace('-', '_', $flag);
        if (strpos($flag, 'risk_flag_') === 0) {
            $flag = substr($flag, 10);
        }

        return $flag;
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    private static function format_days_inactive(\stdClass $row): string {
        if ($row->daysinactive === null) {
            return '-';
        }

        return (string)(int)$row->daysinactive;
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    private static function format_last_access(\stdClass $row): string {
        if (empty($row->lastaccesstimestamp)) {
            return get_string('report_never', 'block_student_engagement');
        }

        return userdate((int)$row->lastaccesstimestamp);
    }

    /**
     * @param float|null $value
     * @return string
     */
    private static function format_nullable_grade(?float $value): string {
        return ($value === null) ? '-' : format_float((float)$value, 2);
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    private static function format_risk_flags_text(\stdClass $row): string {
        if (empty($row->riskflags) || !is_array($row->riskflags)) {
            return '-';
        }

        $labels = [];
        foreach ($row->riskflags as $flag) {
            $labels[] = self::resolve_risk_flag_label((string)$flag);
        }

        return implode(', ', $labels);
    }

    /**
     * Disable Moodle "Reset table preferences" action for this plugin table.
     *
     * @return bool
     */
    protected function can_be_reset() {
        return false;
    }
}
