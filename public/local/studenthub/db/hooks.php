<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core_course\hook\before_course_viewed::class,
        'callback' => [\local_studenthub\course_items::class, 'record_course_visit'],
    ],
];
