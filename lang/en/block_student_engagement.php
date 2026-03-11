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
$string['contentnotready'] = 'Engagement metrics are not available yet.';
$string['cachenotavailable'] = 'Engagement metrics have not been calculated for this course yet.';
$string['dashboard_subtitle'] = 'Cached engagement overview for quick teacher review.';
$string['dashboard_active_caption'] = 'Students with recent activity.';
$string['dashboard_inactive_caption'] = 'Students without recent activity.';
$string['nopermissions'] = 'You do not have permission to view this block.';

$string['active_days_threshold'] = 'Active days threshold';
$string['active_days_threshold_desc'] = 'Number of days with recent activity to consider a student active.';
$string['inactive_days_threshold'] = 'Inactive days threshold';
$string['inactive_days_threshold_desc'] = 'Number of days without activity to consider a student inactive.';

$string['active_students'] = 'Active students';
$string['active_students_7_days'] = 'Active students (7 days)';
$string['inactive_students'] = 'Inactive students';
$string['inactive_students_over_threshold'] = 'Inactive students > 14 days';
$string['most_active_user'] = 'Most active user';
$string['most_active_interactions'] = 'Interactions: {$a}';
$string['no_inactive_students'] = 'No inactive students found.';
$string['no_most_active_user'] = 'No active student available.';
$string['last_calculated'] = 'Last calculated';
$string['task_calculate_engagement'] = 'Calculate and refresh engagement cache';
