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
 * Renderer for block_student_engagement.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer for the Student Engagement block.
 */
class block_student_engagement_renderer extends plugin_renderer_base {

    /**
     * Render the student engagement dashboard.
     *
     * @param stdClass $data
     * @return string
     */
    public function dashboard(stdClass $data): string {
        $header = html_writer::start_div('block_student_engagement-header');
        $header .= html_writer::div(
            $this->pix_icon('i/report', '') .
            html_writer::span(s($data->title), 'block_student_engagement-header__title'),
            'block_student_engagement-header__heading'
        );
        $header .= html_writer::div(s($data->subtitle), 'block_student_engagement-header__subtitle');
        $header .= html_writer::end_div();

        $cards = [];
        $cards[] = $this->metric_card(
            'active',
            'i/group',
            get_string('active_students_7_days', 'block_student_engagement'),
            (string)$data->active_students,
            get_string('dashboard_active_caption', 'block_student_engagement')
        );
        $cards[] = $this->metric_card(
            'inactive',
            'i/warning',
            get_string('inactive_students', 'block_student_engagement'),
            (string)$data->inactive_students,
            get_string('dashboard_inactive_caption', 'block_student_engagement')
        );
        $cards[] = $this->metric_card(
            'highlight',
            't/award',
            get_string('most_active_user', 'block_student_engagement'),
            s($data->most_active_user),
            get_string('most_active_interactions', 'block_student_engagement', $data->most_active_interactions),
            !$data->has_most_active_user,
            'block_student_engagement-card__value--person'
        );
        $cards[] = $this->inactive_users_card($data);

        $footer = html_writer::start_div('block_student_engagement-footer');
        $footer .= html_writer::div(
            $this->pix_icon('i/calendar', '') .
            html_writer::span(get_string('last_calculated', 'block_student_engagement'), 'block_student_engagement-footer__label'),
            'block_student_engagement-footer__heading'
        );
        $footer .= html_writer::div(s($data->last_calculated), 'block_student_engagement-footer__value');
        $footer .= html_writer::end_div();

        $content = html_writer::start_div('block_student_engagement-dashboard');
        $content .= $header;
        if (!empty($data->has_report_link) && !empty($data->report_url)) {
            $content .= html_writer::div(
                html_writer::link(
                    $data->report_url,
                    get_string('view_engagement_report', 'block_student_engagement'),
                    ['class' => 'block_student_engagement-report-link']
                ),
                'block_student_engagement-actions'
            );
        }
        $content .= html_writer::div(implode('', $cards), 'block_student_engagement-grid');
        $content .= $footer;
        $content .= html_writer::end_div();

        return $content;
    }

    /**
     * Render a metric card.
     *
     * @param string $modifier
     * @param string $icon
     * @param string $label
     * @param string $value
     * @param string $meta
     * @param bool $isempty
     * @param string $valueclass
     * @return string
     */
    private function metric_card(
        string $modifier,
        string $icon,
        string $label,
        string $value,
        string $meta,
        bool $isempty = false,
        string $valueclass = ''
    ): string {
        $classes = 'block_student_engagement-card block_student_engagement-card--' . $modifier;
        if ($isempty) {
            $classes .= ' block_student_engagement-empty';
        }

        $valueclasses = 'block_student_engagement-card__value';
        if ($valueclass !== '') {
            $valueclasses .= ' ' . $valueclass;
        }

        $content = html_writer::div($this->pix_icon($icon, ''), 'block_student_engagement-card__icon');
        $content .= html_writer::div(s($label), 'block_student_engagement-card__label');
        $content .= html_writer::div($value, $valueclasses);
        $content .= html_writer::div(s($meta), 'block_student_engagement-card__meta');

        return html_writer::div($content, $classes);
    }

    /**
     * Render the inactive users card.
     *
     * @param stdClass $data
     * @return string
     */
    private function inactive_users_card(stdClass $data): string {
        $classes = 'block_student_engagement-card block_student_engagement-card--inactive-list';
        $content = html_writer::div($this->pix_icon('i/calendar', ''), 'block_student_engagement-card__icon');
        $content .= html_writer::div(
            get_string('inactive_students_over_threshold', 'block_student_engagement'),
            'block_student_engagement-card__label'
        );

        if (!$data->has_inactive_users) {
            $content .= html_writer::div(
                s(get_string('no_inactive_students', 'block_student_engagement')),
                'block_student_engagement-card__meta block_student_engagement-empty'
            );
            return html_writer::div($content, $classes);
        }

        $items = [];
        foreach ($data->inactive_users as $name) {
            $items[] = html_writer::tag('li', s($name), ['class' => 'block_student_engagement-list__item']);
        }

        $content .= html_writer::tag(
            'ul',
            implode('', $items),
            ['class' => 'block_student_engagement-list']
        );

        return html_writer::div($content, $classes);
    }
}
