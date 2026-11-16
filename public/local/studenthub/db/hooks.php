<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => [\local_studenthub\notice_output::class, 'add_to_page'],
    ],
    [
        'hook' => \core_course\hook\before_course_viewed::class,
        'callback' => [\local_studenthub\course_items::class, 'record_course_visit'],
    ],
];
