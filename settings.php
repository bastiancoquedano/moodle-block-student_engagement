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
 * Global settings for block_student_engagement.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'block_student_engagement/active_days_threshold',
        get_string('active_days_threshold', 'block_student_engagement'),
        get_string('active_days_threshold_desc', 'block_student_engagement'),
        7,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/inactive_days_threshold',
        get_string('inactive_days_threshold', 'block_student_engagement'),
        get_string('inactive_days_threshold_desc', 'block_student_engagement'),
        14,
        PARAM_INT
    ));
}
