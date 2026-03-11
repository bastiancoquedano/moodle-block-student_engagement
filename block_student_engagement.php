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
 * Main class for Student Engagement block.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_student_engagement extends block_base {

    /**
     * Initialize the block title.
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_student_engagement');
    }

    /**
     * Indicates whether this block has global configuration.
     *
     * @return bool
     */
    public function has_config(): bool {
        return true;
    }

    /**
     * Limit where this block can be added.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return [
            'course-view' => true,
            'my' => true,
        ];
    }

    /**
     * Build block content.
     *
     * @return stdClass
     */
    public function get_content(): stdClass {
        global $COURSE, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($this->instance)) {
            return $this->content;
        }

        if (!has_capability('block/student_engagement:view', $this->context)) {
            $this->content->text = get_string('nopermissions', 'block_student_engagement');
            return $this->content;
        }

        // Cache-first read: this issue introduces the persistence model and fast runtime reads.
        // Calculation (reading logstore_standard_log) will be implemented separately.
        $courseid = isset($COURSE->id) ? (int)$COURSE->id : 0;
        $cache = null;
        if ($courseid > 0) {
            $cache = \block_student_engagement\cache_manager::get_course_cache($courseid);
        }

        if (!$cache) {
            $this->content->text = get_string('cachenotavailable', 'block_student_engagement');
            return $this->content;
        }

        $mostactiveuser = $this->resolve_most_active_user($cache);
        $inactiveusers = $this->resolve_inactive_users($cache);
        $lastcalculated = !empty($cache->last_calculated) ? userdate((int)$cache->last_calculated) : '-';

        $rows = [];
        $rows[] = html_writer::tag('dt', get_string('active_students_7_days', 'block_student_engagement'));
        $rows[] = html_writer::tag('dd', (string)((int)$cache->active_students));
        $rows[] = html_writer::tag('dt', get_string('inactive_students', 'block_student_engagement'));
        $rows[] = html_writer::tag('dd', (string)((int)$cache->inactive_students));
        $rows[] = html_writer::tag('dt', get_string('most_active_user', 'block_student_engagement'));
        $rows[] = html_writer::tag(
            'dd',
            s($mostactiveuser) . html_writer::empty_tag('br') .
            get_string('most_active_interactions', 'block_student_engagement', (int)$cache->most_active_interactions)
        );
        $rows[] = html_writer::tag('dt', get_string('inactive_students_over_threshold', 'block_student_engagement'));
        $rows[] = html_writer::tag('dd', $inactiveusers);
        $rows[] = html_writer::tag('dt', get_string('last_calculated', 'block_student_engagement'));
        $rows[] = html_writer::tag('dd', s($lastcalculated));

        $this->content->text = html_writer::tag(
            'dl',
            implode('', $rows),
            ['class' => 'block_student_engagement_metrics']
        );
        return $this->content;
    }

    /**
     * Resolve the most active user name from cached data.
     *
     * @param stdClass $cache
     * @return string
     */
    private function resolve_most_active_user(stdClass $cache): string {
        global $DB;

        if (empty($cache->most_active_userid)) {
            return get_string('no_most_active_user', 'block_student_engagement');
        }

        $user = $DB->get_record(
            'user',
            ['id' => (int)$cache->most_active_userid],
            'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename',
            IGNORE_MISSING
        );

        if (!$user) {
            return get_string('no_most_active_user', 'block_student_engagement');
        }

        return fullname($user);
    }

    /**
     * Resolve the list of inactive user names from cached JSON data.
     *
     * @param stdClass $cache
     * @return string
     */
    private function resolve_inactive_users(stdClass $cache): string {
        global $DB;

        if (empty($cache->inactive_userids)) {
            return s(get_string('no_inactive_students', 'block_student_engagement'));
        }

        $userids = json_decode($cache->inactive_userids, true);
        if (!is_array($userids)) {
            return s(get_string('no_inactive_students', 'block_student_engagement'));
        }

        $userids = array_values(array_filter(array_map('intval', $userids)));
        if (empty($userids)) {
            return s(get_string('no_inactive_students', 'block_student_engagement'));
        }

        $users = $DB->get_records_list(
            'user',
            'id',
            $userids,
            'lastname ASC, firstname ASC',
            'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename'
        );

        if (empty($users)) {
            return s(get_string('no_inactive_students', 'block_student_engagement'));
        }

        $items = [];
        foreach ($users as $user) {
            $items[] = html_writer::tag('li', s(fullname($user)));
        }

        return html_writer::tag('ul', implode('', $items));
    }
}
