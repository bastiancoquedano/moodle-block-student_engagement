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
require_once($CFG->dirroot . '/group/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$view = optional_param('view', 'all', PARAM_ALPHA);
$view = in_array($view, ['all', 'inactive', 'atrisk'], true) ? $view : 'all';
$export = optional_param('export', '', PARAM_ALPHA);

$risklevelraw = trim((string)optional_param('risklevel', 'all', PARAM_RAW_TRIMMED));
$groupidraw = max(0, optional_param('groupid', 0, PARAM_INT));
$statusraw = optional_param('status', 'all', PARAM_ALPHA);
$datefrominput = optional_param('datefrom', '', PARAM_RAW_TRIMMED);
$datetoinput = optional_param('dateto', '', PARAM_RAW_TRIMMED);

$normalise_date_input = static function(string $rawvalue, bool $endofday): array {
    $value = trim($rawvalue);
    if ($value === '') {
        return [0, ''];
    }

    if (preg_match('/^\d{10}$/', $value)) {
        $timestamp = (int)$value;
        if ($timestamp > 0) {
            return [$timestamp, userdate($timestamp, '%Y-%m-%d')];
        }
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $time = $endofday ? '23:59:59' : '00:00:00';
        $timestamp = strtotime($value . ' ' . $time);
        if ($timestamp) {
            return [(int)$timestamp, $value];
        }
    }

    return [0, ''];
};

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('block/student_engagement:viewreport', $context);

$groups = groups_get_all_groups($courseid) ?: [];

$filterformurl = new moodle_url('/blocks/student_engagement/report.php', [
    'courseid' => $courseid,
    'view' => $view,
]);
$filterformdata = [
    'courseid' => $courseid,
    'view' => $view,
    'risklevel' => $risklevelraw,
    'groupid' => $groupidraw,
    'status' => $statusraw,
    'datefrom' => $datefrominput,
    'dateto' => $datetoinput,
    'groups' => $groups,
];
$filterform = new \block_student_engagement\form\report_filters_form(
    $filterformurl,
    $filterformdata,
    'get',
    '',
    ['class' => 'block_student_engagement-filter-form']
);
if ($submittedfilterdata = $filterform->get_data()) {
    $risklevelraw = trim((string)($submittedfilterdata->risklevel ?? 'all'));
    $groupidraw = max(0, (int)($submittedfilterdata->groupid ?? 0));
    $statusraw = (string)($submittedfilterdata->status ?? 'all');
    $datefrominput = trim((string)($submittedfilterdata->datefrom ?? ''));
    $datetoinput = trim((string)($submittedfilterdata->dateto ?? ''));
}

if ($risklevelraw === 'all') {
    $risklevel = 'all';
} else if ($risklevelraw === 'high_critical') {
    $risklevel = 'high_critical';
} else if (ctype_digit($risklevelraw) && (int)$risklevelraw >= 0 && (int)$risklevelraw <= 3) {
    $risklevel = (string)(int)$risklevelraw;
} else {
    $risklevel = 'all';
}

$groupid = $groupidraw;
if ($groupid > 0 && !isset($groups[$groupid])) {
    $groupid = 0;
}

$status = in_array($statusraw, ['all', 'active', 'inactive'], true) ? $statusraw : 'all';

[$datefrom, $datefrominput] = $normalise_date_input($datefrominput, false);
[$dateto, $datetoinput] = $normalise_date_input($datetoinput, true);
if ($datefrom > 0 && $dateto > 0 && $datefrom > $dateto) {
    [$datefrominput, $datetoinput] = [$datetoinput, $datefrominput];
    $datefrom = strtotime($datefrominput . ' 00:00:00') ?: 0;
    $dateto = strtotime($datetoinput . ' 23:59:59') ?: 0;
}

$filterformdata['risklevel'] = $risklevel;
$filterformdata['groupid'] = $groupid;
$filterformdata['status'] = $status;
$filterformdata['datefrom'] = $datefrominput;
$filterformdata['dateto'] = $datetoinput;
$filterform->set_data($filterformdata);

$filters = [
    'risklevel' => $risklevel,
    'atrisk' => ($view === 'atrisk' && $risklevel === 'all') || $risklevel === 'high_critical',
    'groupid' => $groupid,
    'status' => $status,
    'datefrom' => $datefrom,
    'dateto' => $dateto,
    'datefrominput' => $datefrominput,
    'datetoinput' => $datetoinput,
];

$hascustomfilters = ($risklevel !== 'all' || $view === 'atrisk' || $groupid > 0 || $status !== 'all' || $datefrom > 0 || $dateto > 0);
$legacyinactive = ($view === 'inactive' && !$hascustomfilters);
$effectiveview = $legacyinactive ? 'inactive' : 'all';

$urlparams = ['courseid' => $courseid];
if ($view === 'inactive') {
    $urlparams['view'] = 'inactive';
} else if ($view === 'atrisk') {
    $urlparams['view'] = 'atrisk';
}
if ($risklevel !== 'all') {
    $urlparams['risklevel'] = $risklevel;
}
if ($groupid > 0) {
    $urlparams['groupid'] = $groupid;
}
if ($status !== 'all') {
    $urlparams['status'] = $status;
}
if ($datefrominput !== '') {
    $urlparams['datefrom'] = $datefrominput;
}
if ($datetoinput !== '') {
    $urlparams['dateto'] = $datetoinput;
}

$url = new moodle_url('/blocks/student_engagement/report.php', $urlparams);
$exportparams = $urlparams;
$exportparams['export'] = 'excel';
$exportparams['sesskey'] = sesskey();
$exporturl = new moodle_url('/blocks/student_engagement/report.php', $exportparams);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_context($context);

$titlestring = ($legacyinactive) ? 'report_inactive_title' : 'report_title';
$subtitlestring = ($legacyinactive) ? 'report_inactive_subtitle' : 'report_subtitle';
$formulastring = ($legacyinactive) ? 'report_inactive_formula' : 'report_formula';

$PAGE->set_title(get_string($titlestring, 'block_student_engagement'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/blocks/student_engagement/styles.css');

$studentcount = \block_student_engagement\engagement_report::count_rows($courseid, $effectiveview, $filters);

if ($export === 'excel') {
    require_sesskey();

    $defaultsort = ($legacyinactive) ? 'daysinactive' : 'risklevel';
    $defaultdir = 'DESC';
    $ordersql = \block_student_engagement\engagement_report::get_sort_sql($defaultsort, $defaultdir, $effectiveview);
    $rows = \block_student_engagement\engagement_report::get_rows(
        $courseid,
        $ordersql,
        0,
        0,
        $effectiveview,
        $filters
    );

    $legacyexport = \block_student_engagement\output\report_table::is_legacy_inactive_view($view, $filters);
    $headers = \block_student_engagement\output\report_table::get_export_headers($legacyexport);
    $colcount = max(2, count($headers));
    $padrow = static function(array $row) use ($colcount): array {
        return array_pad($row, $colcount, '');
    };

    \core_php_time_limit::raise();
    \core\session\manager::write_close();
    \core_form\util::form_download_complete();

    $filenamebase = 'student_engagement_report_' . $courseid . '_' . userdate(time(), '%Y%m%d_%H%M%S');
    $writer = \OpenSpout\Writer\Common\Creator\WriterFactory::createFromFile($filenamebase . '.xlsx');
    if (method_exists($writer->getOptions(), 'setTempFolder')) {
        $writer->getOptions()->setTempFolder(make_request_directory());
    }
    $writer->openToBrowser($filenamebase . '.xlsx');

    if ($writer instanceof \OpenSpout\Writer\AbstractWriterMultiSheets) {
        $sheettitle = core_text::substr(format_string($course->shortname), 0, 31);
        $writer->getCurrentSheet()->setName($sheettitle);
    }

    $boldcenterstyle = (new \OpenSpout\Common\Entity\Style\Style())
        ->setFontBold()
        ->setCellAlignment(\OpenSpout\Common\Entity\Style\CellAlignment::CENTER);

    $writer->addRow(
        \OpenSpout\Common\Entity\Row::fromValues(
            $padrow([get_string('export_metadata_generated_at', 'block_student_engagement'), userdate(time(), '%Y-%m-%d %H:%M:%S')]),
            $boldcenterstyle
        )
    );
    $writer->addRow(
        \OpenSpout\Common\Entity\Row::fromValues(
            $padrow([get_string('export_metadata_course', 'block_student_engagement'), format_string($course->fullname) . ' (#' . $courseid . ')']),
            $boldcenterstyle
        )
    );
    $writer->addRow(
        \OpenSpout\Common\Entity\Row::fromValues(
            $padrow([get_string('export_metadata_exported_by', 'block_student_engagement'), fullname($USER) . ' (' . $USER->username . ' #' . $USER->id . ')']),
            $boldcenterstyle
        )
    );
    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(array_fill(0, $colcount, '')));
    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($padrow($headers), $boldcenterstyle));

    foreach ($rows as $row) {
        $writer->addRow(
            \OpenSpout\Common\Entity\Row::fromValues(
                $padrow(\block_student_engagement\output\report_table::format_export_row($row, $legacyexport))
            )
        );
    }

    $writer->close();
    exit;
}

echo $OUTPUT->header();

echo html_writer::start_div('block_student_engagement-report');
echo \block_student_engagement\output\report_page::render_header($OUTPUT, $titlestring, $subtitlestring, $formulastring, format_string($course->fullname));

$reseturl = new moodle_url('/blocks/student_engagement/report.php', ['courseid' => $courseid, 'view' => $view]);
echo \block_student_engagement\output\report_page::render_filters($filterform, $reseturl);

$activesummary = [];
if ($risklevel !== 'all') {
    if ($risklevel === 'high_critical') {
        $risklabel = get_string('filter_risk_level_high_critical', 'block_student_engagement');
    } else {
        $risklabel = get_string('risk_level_label_' . (int)$risklevel, 'block_student_engagement');
    }
    $activesummary[] = get_string('filter_risk_level', 'block_student_engagement') . ': ' . $risklabel;
} else if ($view === 'atrisk') {
    $activesummary[] = get_string('filter_risk_level', 'block_student_engagement') . ': ' .
        get_string('risk_level_label_2', 'block_student_engagement') . ' + ' .
        get_string('risk_level_label_3', 'block_student_engagement');
}
if ($groupid > 0 && isset($groups[$groupid])) {
    $activesummary[] = get_string('filter_group', 'block_student_engagement') . ': ' . format_string($groups[$groupid]->name);
}
if ($status !== 'all') {
    $activesummary[] = get_string('filter_status', 'block_student_engagement') . ': ' .
        get_string('filter_status_' . $status, 'block_student_engagement');
}
if ($datefrominput !== '') {
    $activesummary[] = get_string('filter_date_from', 'block_student_engagement') . ': ' . s($datefrominput);
}
if ($datetoinput !== '') {
    $activesummary[] = get_string('filter_date_to', 'block_student_engagement') . ': ' . s($datetoinput);
}

echo \block_student_engagement\output\report_page::render_active_filters($activesummary);
echo \block_student_engagement\output\report_page::render_results($OUTPUT, $studentcount, $activesummary, $legacyinactive, $courseid, $url, $effectiveview, $filters);
echo \block_student_engagement\output\report_page::render_export_action($exporturl);

echo html_writer::end_div();

echo $OUTPUT->footer();
