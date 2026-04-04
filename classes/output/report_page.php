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

namespace block_student_engagement\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Report page UI helper.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_page {
    /**
     * Render report header.
     *
     * @param \renderer_base $output
     * @param string $titlestring
     * @param string $subtitlestring
     * @param string $formulastring
     * @param string $coursefullname
     * @return string
     */
    public static function render_header(
        \renderer_base $output,
        string $titlestring,
        string $subtitlestring,
        string $formulastring,
        string $coursefullname
    ): string {
        $content = \html_writer::start_div('block_student_engagement-report__header');
        $content .= \html_writer::div(
            $output->pix_icon('i/report', '') . get_string($titlestring, 'block_student_engagement'),
            'block_student_engagement-report__title'
        );
        $content .= \html_writer::div(
            get_string($subtitlestring, 'block_student_engagement', $coursefullname),
            'block_student_engagement-report__subtitle'
        );
        $content .= \html_writer::div(
            get_string($formulastring, 'block_student_engagement'),
            'block_student_engagement-report__formula'
        );
        $content .= \html_writer::end_div();

        return $content;
    }

    /**
     * Render filters form and clear action.
     *
     * @param \moodleform $filterform
     * @param \moodle_url $reseturl
     * @return string
     */
    public static function render_filters(
        \moodleform $filterform,
        \moodle_url $reseturl
    ): string {
        ob_start();
        $filterform->display();
        $formhtml = ob_get_clean();
        $applybutton = \html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('filter_apply', 'block_student_engagement'),
            'class' => 'btn btn-primary',
        ]);
        $clearlink = \html_writer::link(
            $reseturl,
            get_string('filter_clear', 'block_student_engagement'),
            ['class' => 'btn btn-secondary block_student_engagement-filter-clear']
        );
        $actionshtml = \html_writer::div($applybutton . $clearlink, 'block_student_engagement-filter-actions');
        $formhtml = preg_replace('/<\/form>\s*$/', $actionshtml . '</form>', $formhtml) ?? ($formhtml . $actionshtml);

        $content = \html_writer::start_div('block_student_engagement-report__filters');
        $content .= $formhtml;
        $content .= \html_writer::end_div();

        return $content;
    }

    /**
     * Render active filters summary.
     *
     * @param array $activesummary
     * @return string
     */
    public static function render_active_filters(array $activesummary): string {
        if (empty($activesummary)) {
            return '';
        }

        return \html_writer::div(
            get_string('filters_active_summary', 'block_student_engagement') . ' ' . s(implode(' | ', $activesummary)),
            'block_student_engagement-report__active-filters'
        );
    }

    /**
     * Render table or empty state.
     *
     * @param \renderer_base $output
     * @param int $studentcount
     * @param array $activesummary
     * @param bool $legacyinactive
     * @param int $courseid
     * @param \moodle_url $url
     * @param string $effectiveview
     * @param array $filters
     * @return string
     */
    public static function render_results(
        \renderer_base $output,
        int $studentcount,
        array $activesummary,
        bool $legacyinactive,
        int $courseid,
        \moodle_url $url,
        string $effectiveview,
        array $filters
    ): string {
        if ($studentcount === 0) {
            if (!empty($activesummary)) {
                $emptystring = 'report_no_students_with_filters';
            } else {
                $emptystring = ($legacyinactive) ? 'report_no_inactive_students' : 'report_no_students';
            }
            return $output->notification(get_string($emptystring, 'block_student_engagement'), 'info');
        }

        $table = new report_table($courseid, $url, $effectiveview, $filters);
        ob_start();
        $table->out(25, true);
        $tablehtml = ob_get_clean();

        return \html_writer::div($tablehtml, 'block_student_engagement-report__table');
    }

    /**
     * Render export action.
     *
     * @param \moodle_url $exporturl
     * @return string
     */
    public static function render_export_action(\moodle_url $exporturl): string {
        return \html_writer::div(
            \html_writer::link(
                $exporturl,
                get_string('export_excel', 'block_student_engagement'),
                ['class' => 'btn btn-primary block_student_engagement-report__export']
            ),
            'block_student_engagement-report__export-wrap'
        );
    }
}
