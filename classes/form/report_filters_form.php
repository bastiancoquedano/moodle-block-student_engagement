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

namespace block_student_engagement\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Report filters form.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_filters_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata ?? [];

        $courseid = (int)($customdata['courseid'] ?? 0);
        $view = (string)($customdata['view'] ?? 'all');
        $risklevel = (string)($customdata['risklevel'] ?? 'all');
        $groupid = (int)($customdata['groupid'] ?? 0);
        $status = (string)($customdata['status'] ?? 'all');
        $datefrom = (string)($customdata['datefrom'] ?? '');
        $dateto = (string)($customdata['dateto'] ?? '');
        $groups = (array)($customdata['groups'] ?? []);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'view', $view);
        $mform->setType('view', PARAM_ALPHA);

        $mform->addElement('html', '<div class="horizontal-filters">');

        $riskoptions = [
            'all' => get_string('filter_all', 'block_student_engagement'),
            'high_critical' => get_string('filter_risk_level_high_critical', 'block_student_engagement'),
            '0' => get_string('risk_level_label_0', 'block_student_engagement'),
            '1' => get_string('risk_level_label_1', 'block_student_engagement'),
            '2' => get_string('risk_level_label_2', 'block_student_engagement'),
            '3' => get_string('risk_level_label_3', 'block_student_engagement'),
        ];
        $mform->addElement('html', '<div class="horizontal-filter-item">');
        $mform->addElement('select', 'risklevel', get_string('filter_risk_level', 'block_student_engagement'), $riskoptions);
        $mform->setType('risklevel', PARAM_RAW_TRIMMED);
        $mform->setDefault('risklevel', $risklevel);
        $mform->addElement('html', '</div>');

        $groupoptions = [0 => get_string('filter_all', 'block_student_engagement')];
        foreach ($groups as $group) {
            $groupoptions[(int)$group->id] = format_string($group->name);
        }
        $mform->addElement('html', '<div class="horizontal-filter-item">');
        $mform->addElement('select', 'groupid', get_string('filter_group', 'block_student_engagement'), $groupoptions);
        $mform->setType('groupid', PARAM_INT);
        $mform->setDefault('groupid', $groupid);
        $mform->addElement('html', '</div>');

        $statusoptions = [
            'all' => get_string('filter_all', 'block_student_engagement'),
            'active' => get_string('filter_status_active', 'block_student_engagement'),
            'inactive' => get_string('filter_status_inactive', 'block_student_engagement'),
        ];
        $mform->addElement('html', '<div class="horizontal-filter-item">');
        $mform->addElement('select', 'status', get_string('filter_status', 'block_student_engagement'), $statusoptions);
        $mform->setType('status', PARAM_ALPHA);
        $mform->setDefault('status', $status);
        $mform->addElement('html', '</div>');

        $mform->addElement('html', '<div class="horizontal-filter-item">');
        $mform->addElement('text', 'datefrom', get_string('filter_date_from', 'block_student_engagement'));
        $mform->setType('datefrom', PARAM_RAW_TRIMMED);
        $mform->setDefault('datefrom', $datefrom);
        $datefromelement = $mform->getElement('datefrom');
        if ($datefromelement) {
            $datefromelement->setType('date');
            $datefromelement->updateAttributes(['type' => 'date']);
        }
        $mform->addElement('html', '</div>');

        $mform->addElement('html', '<div class="horizontal-filter-item">');
        $mform->addElement('text', 'dateto', get_string('filter_date_to', 'block_student_engagement'));
        $mform->setType('dateto', PARAM_RAW_TRIMMED);
        $mform->setDefault('dateto', $dateto);
        $datetoelement = $mform->getElement('dateto');
        if ($datetoelement) {
            $datetoelement->setType('date');
            $datetoelement->updateAttributes(['type' => 'date']);
        }
        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');

        $this->add_action_buttons(false, get_string('filter_apply', 'block_student_engagement'));
    }
}
