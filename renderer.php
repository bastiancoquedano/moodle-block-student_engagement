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
        $content = html_writer::start_div('block_student_engagement-dashboard');

        $content .= html_writer::start_div('block_student_engagement-header');
        $content .= html_writer::div(
            $this->pix_icon('i/report', '') .
            html_writer::span(s($data->title), 'block_student_engagement-header__title'),
            'block_student_engagement-header__heading'
        );
        $content .= html_writer::div(s($data->subtitle), 'block_student_engagement-header__subtitle');
        $content .= html_writer::end_div();

        $cards = [];
        $cards[] = $this->metric_card(
            'status-good',
            'i/group',
            get_string('active_students', 'block_student_engagement'),
            (string)$data->active_students,
            get_string('dashboard_active_caption', 'block_student_engagement')
        );
        $cards[] = $this->metric_card(
            'status-neutral',
            'i/warning',
            get_string('inactive_students', 'block_student_engagement'),
            (string)$data->inactive_students,
            get_string('dashboard_inactive_caption', 'block_student_engagement')
        );
        $cards[] = $this->metric_card(
            'status-danger',
            'i/warning',
            get_string('at_risk_students', 'block_student_engagement'),
            (string)$data->at_risk_students,
            get_string('dashboard_at_risk_caption', 'block_student_engagement')
        );
        $cards[] = $this->metric_card(
            'status-neutral',
            'i/completion',
            get_string('average_completion', 'block_student_engagement'),
            (string)$data->average_completion_percent . '%',
            get_string('dashboard_completion_caption', 'block_student_engagement')
        );

        $content .= html_writer::div(implode('', $cards), 'block_student_engagement-grid');
        if (!empty($data->has_report_link)) {
            $content .= $this->actions_band($data);
        }
        $content .= html_writer::end_div();

        return $content;
    }

    /**
     * Render a metric card.
     *
     * @param string $statusclass
     * @param string $icon
     * @param string $label
     * @param string $value
     * @param string $meta
     * @return string
     */
    private function metric_card(
        string $statusclass,
        string $icon,
        string $label,
        string $value,
        string $meta
    ): string {
        $classes = 'block_student_engagement-card ' . $statusclass;

        $content = html_writer::div($this->pix_icon($icon, ''), 'block_student_engagement-card__icon');
        $content .= html_writer::div(s($label), 'block_student_engagement-card__label');
        $content .= html_writer::div(s($value), 'block_student_engagement-card__value', ['title' => $value]);
        $content .= html_writer::div(s($meta), 'block_student_engagement-card__meta');

        return html_writer::div($content, $classes);
    }

    /**
     * Render compact action band.
     *
     * @param stdClass $data
     * @return string
     */
    private function actions_band(stdClass $data): string {
        $items = [];
        $items[] = $this->action_link(
            $data->full_report_url,
            'i/report',
            get_string('view_engagement_report', 'block_student_engagement')
        );
        $items[] = $this->action_link(
            $data->inactive_report_url,
            'i/calendar',
            get_string('view_inactive_users_report', 'block_student_engagement')
        );
        $items[] = $this->action_link(
            $data->risk_report_url,
            'i/warning',
            get_string('view_at_risk_users_report', 'block_student_engagement')
        );
        $items[] = $this->action_placeholder(
            'i/report',
            get_string('view_recommendations', 'block_student_engagement')
        );

        return html_writer::div(implode('', $items), 'block_student_engagement-actions');
    }

    /**
     * Render an action link.
     *
     * @param moodle_url|null $url
     * @param string $icon
     * @param string $label
     * @return string
     */
    private function action_link(?moodle_url $url, string $icon, string $label): string {
        if (!$url) {
            return '';
        }

        $content = $this->pix_icon($icon, '') . html_writer::span(s($label), 'block_student_engagement-action__label');
        return html_writer::link($url, $content, ['class' => 'block_student_engagement-action']);
    }

    /**
     * Render disabled placeholder action.
     *
     * @param string $icon
     * @param string $label
     * @return string
     */
    private function action_placeholder(string $icon, string $label): string {
        $content = $this->pix_icon($icon, '') . html_writer::span(s($label), 'block_student_engagement-action__label');
        $content .= html_writer::span(
            s(get_string('coming_soon', 'block_student_engagement')),
            'block_student_engagement-action__badge'
        );

        return html_writer::tag('span', $content, [
            'class' => 'block_student_engagement-action block_student_engagement-action--disabled',
            'aria-disabled' => 'true',
            'role' => 'button',
        ]);
    }
}
