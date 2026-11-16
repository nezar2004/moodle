<?php
namespace local_studenthub;

use context_course;
use core\hook\output\before_standard_top_of_body_html_generation;
use moodle_url;

/** Attach notices to course pages and to the current user's course cards. */
final class notice_output {
    public static function add_to_page(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;

        if (!isloggedin() || isguestuser()) {
            return;
        }
        $cards = $PAGE->pagelayout === 'mycourses';
        $incourse = str_starts_with($PAGE->pagetype, 'course-view-');
        if (!$cards && !$incourse) {
            return;
        }

        $data = [];
        if ($cards) {
            $courses = array_filter(enrol_get_my_courses(), static fn($course): bool => (bool) $course->visible);
            foreach (notices::for_courses($courses) as $id => $list) {
                $data[] = [
                    'courseid' => $id,
                    'notices' => [reset($list)],
                    'more' => (new moodle_url('/course/view.php', ['id' => $id], 'local-studenthub-course-notices'))->out(false),
                ];
            }
        } else {
            $course = $PAGE->course;
            $context = context_course::instance($course->id);
            $canpublish = has_capability('moodle/course:manageactivities', $context);
            if (!$canpublish && !is_enrolled($context, null, '', true)) {
                return;
            }
            $list = notices::for_courses([$course])[$course->id] ?? [];
            if (!$list && !$canpublish) {
                return;
            }
            $data[] = [
                'courseid' => $course->id,
                'incourse' => true,
                'notices' => $list,
                'manage' => $canpublish ? (new moodle_url('/local/studenthub/notice.php', ['id' => $course->id]))->out(false) : null,
            ];
        }

        if ($data) {
            $PAGE->requires->js(new moodle_url('/local/studenthub/notice.js'));
            $hook->add_html($hook->renderer->render_from_template('local_studenthub/notices', ['courses' => $data]));
        }
    }
}
