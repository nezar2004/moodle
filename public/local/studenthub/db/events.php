<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => \core\event\course_module_deleted::class,
        'callback' => [\local_studenthub\notices::class, 'module_deleted'],
    ],
    [
        'eventname' => \core\event\course_deleted::class,
        'callback' => [\local_studenthub\notices::class, 'course_deleted'],
    ],
];
