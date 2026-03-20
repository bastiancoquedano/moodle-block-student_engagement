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
        global $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($this->instance)) {
            return $this->content;
        }

        $this->page->requires->css('/blocks/student_engagement/styles.css');
        
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

        $renderer = $this->page->get_renderer('block_student_engagement');
        $this->content->text = $renderer->dashboard($this->prepare_dashboard_data($cache));
        return $this->content;
    }

    /**
     * Prepare dashboard data for the renderer.
     *
     * @param stdClass $cache
     * @return stdClass
     */
    private function prepare_dashboard_data(stdClass $cache): stdClass {
        global $COURSE;

        $data = new stdClass();
        $data->title = get_string('pluginname', 'block_student_engagement');
        $data->subtitle = get_string('dashboard_subtitle', 'block_student_engagement');
        $data->active_students = (int)$cache->active_students;
        $data->inactive_students = (int)$cache->inactive_students;
        $data->most_active_user = $this->resolve_most_active_user_name($cache);
        $data->has_most_active_user = !empty($cache->most_active_userid) &&
            $data->most_active_user !== get_string('no_most_active_user', 'block_student_engagement');
        $data->most_active_interactions = (int)$cache->most_active_interactions;
        $data->inactive_users = $this->resolve_inactive_user_names($cache);
        $data->has_inactive_users = !empty($data->inactive_users);
        $data->last_calculated = !empty($cache->last_calculated) ? userdate((int)$cache->last_calculated) : '-';
        $data->has_report_link = $this->can_view_report($COURSE ?? null);
        $data->report_url = $data->has_report_link
            ? new moodle_url('/blocks/student_engagement/report.php', ['courseid' => (int)$COURSE->id])
            : null;
        $data->inactive_report_url = $data->has_report_link
            ? new moodle_url('/blocks/student_engagement/report.php', ['courseid' => (int)$COURSE->id, 'view' => 'inactive'])
            : null;

        return $data;
    }

    /**
     * Check whether the current user can open the detailed report.
     *
     * @param stdClass|null $course
     * @return bool
     */
    private function can_view_report(?stdClass $course): bool {
        if (!$course || empty($course->id) || (int)$course->id <= 0 || (int)$course->id === SITEID) {
            return false;
        }

        $coursecontext = context_course::instance((int)$course->id, IGNORE_MISSING);
        if (!$coursecontext) {
            return false;
        }

        return has_capability('block/student_engagement:viewreport', $coursecontext);
    }

    /**
     * Resolve the most active user name from cached data.
     *
     * @param stdClass $cache
     * @return string
     */
    private function resolve_most_active_user_name(stdClass $cache): string {
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
     * @return string[]
     */
    private function resolve_inactive_user_names(stdClass $cache): array {
        global $DB;

        if (empty($cache->inactive_userids)) {
            return [];
        }

        $userids = json_decode($cache->inactive_userids, true);
        if (!is_array($userids)) {
            return [];
        }

        $userids = array_values(array_filter(array_map('intval', $userids)));
        if (empty($userids)) {
            return [];
        }

        $users = $DB->get_records_list(
            'user',
            'id',
            $userids,
            'lastname ASC, firstname ASC',
            'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename'
        );

        if (empty($users)) {
            return [];
        }

        $items = [];
        foreach ($users as $user) {
            $items[] = fullname($user);
        }

        return $items;
    }
}
