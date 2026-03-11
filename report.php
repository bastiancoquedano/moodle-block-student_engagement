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
 * Detailed participation report page.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('block/student_engagement:viewreport', $context);

$url = new moodle_url('/blocks/student_engagement/report.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_context($context);
$PAGE->set_title(get_string('report_title', 'block_student_engagement'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/blocks/student_engagement/styles.css');

$studentcount = \block_student_engagement\engagement_report::count_students($courseid);

echo $OUTPUT->header();

echo html_writer::start_div('block_student_engagement-report');
echo html_writer::start_div('block_student_engagement-report__header');
echo html_writer::div(
    $OUTPUT->pix_icon('i/report', '') . get_string('report_title', 'block_student_engagement'),
    'block_student_engagement-report__title'
);
echo html_writer::div(
    get_string('report_subtitle', 'block_student_engagement', format_string($course->fullname)),
    'block_student_engagement-report__subtitle'
);
echo html_writer::div(
    get_string('report_formula', 'block_student_engagement'),
    'block_student_engagement-report__formula'
);
echo html_writer::end_div();

if ($studentcount === 0) {
    echo $OUTPUT->notification(get_string('report_no_students', 'block_student_engagement'), 'info');
} else {
    $table = new \block_student_engagement\output\report_table($courseid, $url);
    ob_start();
    $table->out(25, true);
    $tablehtml = ob_get_clean();
    echo html_writer::div($tablehtml, 'block_student_engagement-report__table');
}

echo html_writer::end_div();

echo $OUTPUT->footer();
