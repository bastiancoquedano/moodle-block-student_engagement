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
 * @Author Bastian Coquedano
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

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/report_event_goal',
        get_string('report_event_goal', 'block_student_engagement'),
        get_string('report_event_goal_desc', 'block_student_engagement'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/export_max_rows',
        get_string('export_max_rows', 'block_student_engagement'),
        get_string('export_max_rows_desc', 'block_student_engagement'),
        5000,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_student_engagement/risk_enabled',
        get_string('risk_enabled', 'block_student_engagement'),
        get_string('risk_enabled_desc', 'block_student_engagement'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/risk_grade_weight',
        get_string('risk_grade_weight', 'block_student_engagement'),
        get_string('risk_grade_weight_desc', 'block_student_engagement'),
        40,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/risk_completion_weight',
        get_string('risk_completion_weight', 'block_student_engagement'),
        get_string('risk_completion_weight_desc', 'block_student_engagement'),
        25,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/risk_inactivity_weight',
        get_string('risk_inactivity_weight', 'block_student_engagement'),
        get_string('risk_inactivity_weight_desc', 'block_student_engagement'),
        20,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/risk_participation_weight',
        get_string('risk_participation_weight', 'block_student_engagement'),
        get_string('risk_participation_weight_desc', 'block_student_engagement'),
        15,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/risk_inactivity_days_threshold',
        get_string('risk_inactivity_days_threshold', 'block_student_engagement'),
        get_string('risk_inactivity_days_threshold_desc', 'block_student_engagement'),
        14,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/risk_event_goal',
        get_string('risk_event_goal', 'block_student_engagement'),
        get_string('risk_event_goal_desc', 'block_student_engagement'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/risk_level_observation_min',
        get_string('risk_level_observation_min', 'block_student_engagement'),
        get_string('risk_level_observation_min_desc', 'block_student_engagement'),
        25,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/risk_level_high_min',
        get_string('risk_level_high_min', 'block_student_engagement'),
        get_string('risk_level_high_min_desc', 'block_student_engagement'),
        50,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/risk_level_critical_min',
        get_string('risk_level_critical_min', 'block_student_engagement'),
        get_string('risk_level_critical_min_desc', 'block_student_engagement'),
        75,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/risk_start_percentage',
        get_string('risk_start_percentage', 'block_student_engagement'),
        get_string('risk_start_percentage_desc', 'block_student_engagement'),
        50,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_student_engagement/risk_critical_percentage',
        get_string('risk_critical_percentage', 'block_student_engagement'),
        get_string('risk_critical_percentage_desc', 'block_student_engagement'),
        75,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configselect(
        'block_student_engagement/risk_course_progress_mode',
        get_string('risk_course_progress_mode', 'block_student_engagement'),
        get_string('risk_course_progress_mode_desc', 'block_student_engagement'),
        'course_dates',
        [
            'course_dates' => get_string('risk_course_progress_mode_course_dates', 'block_student_engagement'),
            'graded_completion' => get_string('risk_course_progress_mode_graded_completion', 'block_student_engagement'),
        ]
    ));
}
