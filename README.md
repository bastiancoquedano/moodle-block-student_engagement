# Student Engagement Block for Moodle

## Description

`block_student_engagement` is a Moodle block plugin designed to help teachers identify participation patterns quickly through cached engagement metrics at course level.

The plugin reads activity signals from Moodle data sources, calculates engagement metrics in the background, stores them in a cache table, and renders a compact summary inside the course page.

## Features

- Cached course-level engagement metrics for fast block rendering
- Active students summary based on recent activity
- Inactive students summary and inactive student name list
- Most active student with interaction count
- Engagement score calculation service for future extensions
- Daily scheduled task for automatic cache refresh
- English and Spanish language support

## Installation

1. Copy the plugin into `blocks/student_engagement`.
2. Visit `Site administration > Notifications`.
3. Complete the Moodle upgrade process.
4. Configure thresholds in `Site administration > Plugins > Blocks > Student Engagement`.
5. Run cron or execute the scheduled task manually to populate the cache.

## Screenshots

### Block overview

Placeholder: add a screenshot showing the final block render inside a course page.

### Scheduled task / admin configuration

Placeholder: add a screenshot showing plugin settings and the scheduled task registered in Moodle.

## Metric calculation

The plugin uses the `engagement_analyser` domain class to calculate metrics from Moodle internal tables:

- Student detection is based on course-context role assignments with role shortname `student`.
- Active students are identified from recent events in `logstore_standard_log`.
- Inactive students are derived from enrolled students with no recent qualifying events.
- The most active student is selected by interaction count.
- Noisy `\core\event\course_viewed` events are excluded from interaction counts.

## Engagement Score

The v1 engagement score uses this formula:

`score = events + (completed * 5)`

Where:

- `events` = qualifying events in `logstore_standard_log`
- `completed` = completed activities from `course_modules_completion`

This score is implemented as a reusable service method for future reporting and UI improvements.

## Detailed participation report

The plugin now includes a course-level participation report accessible from the block via
`View participation report`.

The report displays one row per student with:

- total course events
- completed activities shown as `X / total`
- engagement score shown as `NN / 100`

Report score formula:

- completed activities contribute up to `70` points
- course events contribute up to `30` points
- the event contribution uses the configurable admin setting `report_event_goal`

## Cache strategy and scheduled task

The plugin avoids reading logs directly during block rendering.

Instead:

1. `engagement_analyser` calculates metrics for a course.
2. `cache_manager` persists results in `block_student_engagement_cache`.
3. A daily scheduled task refreshes the cache for active courses.
4. The block reads only cached values at runtime.

This architecture keeps the UI responsive and makes the plugin easier to extend with future reporting features.

## Current status

Implemented in v1:

- cached engagement data model
- threshold configuration
- SQL-based engagement analyser
- daily scheduled task for cache refresh
- final block render using cached metrics

Planned next steps:

- richer teacher-facing UX
- screenshots and documentation polish
- optional reporting views or dashboards

## License

This project is released under `GPL-3.0-or-later`.
