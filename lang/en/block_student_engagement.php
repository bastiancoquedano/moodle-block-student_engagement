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
 * Strings for component 'block_student_engagement', language 'en'.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Student Engagement';
$string['student_engagement:addinstance'] = 'Add a new Student Engagement block';
$string['student_engagement:myaddinstance'] = 'Add a new Student Engagement block to Dashboard';
$string['student_engagement:view'] = 'View Student Engagement block';
$string['student_engagement:viewreport'] = 'View Student Engagement report';
$string['cachenotavailable'] = 'Engagement metrics have not been calculated for this course yet.';
$string['dashboard_subtitle'] = 'Cached engagement overview for quick teacher review.';
$string['dashboard_active_caption'] = 'Students with recent activity.';
$string['dashboard_inactive_caption'] = 'Students without recent activity.';
$string['dashboard_at_risk_caption'] = 'Students requiring follow-up.';
$string['dashboard_completion_caption'] = 'Average progress in completable activities.';
$string['nopermissions'] = 'You do not have permission to view this block.';

$string['active_days_threshold'] = 'Active days threshold';
$string['active_days_threshold_desc'] = 'Number of days with recent activity to consider a student active.';
$string['inactive_days_threshold'] = 'Inactive days threshold';
$string['inactive_days_threshold_desc'] = 'Number of days without activity to consider a student inactive.';
$string['report_event_goal'] = 'Report event goal';
$string['report_event_goal_desc'] = 'Number of course events required to award the full 30 event points in the engagement report.';
$string['risk_enabled'] = 'Enable academic risk calculation';
$string['risk_enabled_desc'] = 'When enabled, cron calculates per-student academic risk and stores results in cache tables.';
$string['risk_grade_weight'] = 'Risk weight: grade';
$string['risk_grade_weight_desc'] = 'Weight percentage for the grade risk component.';
$string['risk_completion_weight'] = 'Risk weight: completion';
$string['risk_completion_weight_desc'] = 'Weight percentage for the completion risk component.';
$string['risk_inactivity_weight'] = 'Risk weight: inactivity';
$string['risk_inactivity_weight_desc'] = 'Weight percentage for the inactivity risk component.';
$string['risk_participation_weight'] = 'Risk weight: participation';
$string['risk_participation_weight_desc'] = 'Weight percentage for the participation risk component.';
$string['risk_inactivity_days_threshold'] = 'Risk inactivity threshold (days)';
$string['risk_inactivity_days_threshold_desc'] = 'Number of days without activity required to reach maximum inactivity risk.';
$string['risk_event_goal'] = 'Risk event goal';
$string['risk_event_goal_desc'] = 'Number of recent events required to reach zero participation risk.';
$string['risk_level_observation_min'] = 'Risk threshold: observation';
$string['risk_level_observation_min_desc'] = 'Minimum score required to classify a student as observation level.';
$string['risk_level_high_min'] = 'Risk threshold: high';
$string['risk_level_high_min_desc'] = 'Minimum score required to classify a student as high risk.';
$string['risk_level_critical_min'] = 'Risk threshold: critical';
$string['risk_level_critical_min_desc'] = 'Minimum score required to classify a student as critical risk.';
$string['risk_start_percentage'] = 'Course progress start percentage';
$string['risk_start_percentage_desc'] = 'From this course progress percentage, risk is evaluated with normal severity.';
$string['risk_critical_percentage'] = 'Course progress critical percentage';
$string['risk_critical_percentage_desc'] = 'From this course progress percentage, risk is evaluated with higher severity.';
$string['risk_course_progress_mode'] = 'Course progress mode for risk';
$string['risk_course_progress_mode_desc'] = 'Defines how course progress percentage is calculated for stage adjustments.';
$string['risk_course_progress_mode_course_dates'] = 'Course dates';
$string['risk_course_progress_mode_graded_completion'] = 'Graded completion';

$string['active_students'] = 'Active students';
$string['inactive_students'] = 'Inactive students';
$string['at_risk_students'] = 'At risk students';
$string['average_completion'] = 'Average completion';
$string['most_active_user'] = 'Most active user';
$string['most_active_interactions'] = 'Interactions: {$a}';
$string['no_inactive_students'] = 'No inactive students found.';
$string['last_calculated'] = 'Last calculated';
$string['task_calculate_engagement'] = 'Calculate and refresh engagement cache';
$string['view_engagement_report'] = 'View participation report';
$string['view_at_risk_users_report'] = 'View at risk students';
$string['view_recommendations'] = 'View recommendations';
$string['coming_soon'] = 'Soon';
$string['report_title'] = 'Participation report';
$string['report_subtitle'] = 'Detailed student engagement metrics for {$a}.';
$string['report_formula'] = 'Score formula: completed activities up to 70 points, course events up to 30 points.';
$string['report_student'] = 'Student';
$string['report_completed'] = 'Completed activities';
$string['report_score'] = 'Engagement score';
$string['report_no_students'] = 'No students were found in this course.';
$string['view_inactive_users_report'] = 'View inactive';
$string['report_inactive_title'] = 'Inactive students report';
$string['report_inactive_subtitle'] = 'Complete list of inactive students for {$a}.';
$string['report_inactive_formula'] = 'Includes inactivity days and last course access.';
$string['report_days_inactive'] = 'Inactive days';
$string['report_last_course_access'] = 'Last course access';
$string['report_never'] = 'Never';
$string['report_no_inactive_students'] = 'No inactive students were found in this course.';
$string['report_recent_events'] = 'Recent events';
$string['report_current_grade'] = 'Current grade';
$string['report_pass_grade'] = 'Minimum passing grade';
$string['report_grade_gap'] = 'Grade gap';
$string['report_risk_score'] = 'Risk score';
$string['report_risk_level'] = 'Risk level';
$string['report_risk_flags'] = 'Risk flags';
$string['report_no_students_with_filters'] = 'No students matched the selected filters.';

$string['filter_all'] = 'All';
$string['filter_risk_level'] = 'Risk level';
$string['filter_risk_level_high_critical'] = 'High + Critical';
$string['filter_group'] = 'Group';
$string['filter_status'] = 'Status';
$string['filter_status_active'] = 'Active';
$string['filter_status_inactive'] = 'Inactive';
$string['filter_date_from'] = 'Date from';
$string['filter_date_to'] = 'Date to';
$string['filter_apply'] = 'Apply filters';
$string['filter_clear'] = 'Clear';
$string['filters_active_summary'] = 'Active filters:';
$string['export_excel'] = 'Export Excel';
$string['export_metadata_generated_at'] = 'Generated at';
$string['export_metadata_course'] = 'Course';
$string['export_metadata_exported_by'] = 'Exported by';

$string['risk_level_label_0'] = 'Normal';
$string['risk_level_label_1'] = 'Observation';
$string['risk_level_label_2'] = 'High';
$string['risk_level_label_3'] = 'Critical';
$string['risk_flag_low_grade'] = 'Low grade';
$string['risk_flag_low_completion'] = 'Low completion';
$string['risk_flag_inactivity'] = 'Prolonged inactivity';
$string['risk_flag_low_participation'] = 'Low participation';
$string['risk_flag_below_pass_grade'] = 'Below passing grade';
$string['risk_flag_inactive'] = 'Inactive';
$string['risk_flag_low_recent_activity'] = 'Low recent activity';
$string['risk_flag_behind_expected_progress'] = 'Behind expected progress';
