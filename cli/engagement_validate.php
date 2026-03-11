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
 * CLI validation helper for engagement_analyser.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/blocks/student_engagement/classes/engagement_analyser.php');

[$options, $unrecognized] = cli_get_params(
    [
        'courseid' => null,
        'help' => false,
    ],
    [
        'h' => 'help',
    ]
);

if (!empty($unrecognized)) {
    $options['help'] = true;
}

if (!empty($options['help']) || empty($options['courseid'])) {
    $help = "Validate block_student_engagement engagement analyser.\n\n" .
        "Options:\n" .
        "  --courseid=ID   Course ID to analyse (required)\n" .
        "  -h, --help      Print out this help\n";
    cli_error($help, 0);
}

$courseid = (int)$options['courseid'];
if ($courseid <= 0) {
    cli_error("Invalid --courseid value\n", 1);
}

$payload = \block_student_engagement\engagement_analyser::analyse_course($courseid);

echo "courseid={$payload->courseid}\n";
echo "active_students={$payload->active_students}\n";
echo "inactive_students={$payload->inactive_students}\n";
echo "most_active_userid={$payload->most_active_userid}\n";
echo "most_active_interactions={$payload->most_active_interactions}\n";
echo "inactive_userids_count=" . (is_array($payload->inactive_userids) ? count($payload->inactive_userids) : 0) . "\n";
echo "last_calculated={$payload->last_calculated}\n";
