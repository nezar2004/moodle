<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_studenthub\form\notice as notice_form;
use local_studenthub\notices;

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
$context = context_course::instance($id);
require_capability('moodle/course:manageactivities', $context);

$url = new moodle_url('/local/studenthub/notice.php', ['id' => $id]);
$courseurl = new moodle_url('/course/view.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('managenotices', 'local_studenthub'));
$PAGE->set_heading(format_string($course->fullname));

$remove = optional_param('remove', 0, PARAM_INT);
if ($remove && data_submitted() && confirm_sesskey()) {
    notices::remove($course, $remove);
    redirect($url, get_string('noticeremoved', 'local_studenthub'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$activities = notices::activities($course);
$form = new notice_form($url, ['course' => $course, 'activities' => $activities]);
if ($form->is_cancelled()) {
    redirect($courseurl);
}
if ($activities && ($data = $form->get_data())) {
    notices::publish($course, (int) $data->cmid, $data->kind, (int) ($data->newtime ?? 0), (string) ($data->note ?? ''));
    redirect($courseurl, get_string('noticepublished', 'local_studenthub'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managenotices', 'local_studenthub'));
echo html_writer::tag('p', get_string('publishdisclosure', 'local_studenthub'));
if ($activities) {
    $form->display();
} else {
    echo $OUTPUT->notification(get_string('noassessmentsfornotice', 'local_studenthub'), \core\output\notification::NOTIFY_INFO, false);
}
$active = notices::for_courses([$course], true)[$course->id] ?? [];
if ($active) {
    echo $OUTPUT->heading(get_string('activenotices', 'local_studenthub'), 3);
    foreach ($active as $notice) {
        $removeurl = new moodle_url($url, ['remove' => $notice['id'], 'sesskey' => sesskey()]);
        echo html_writer::start_div('d-flex flex-wrap align-items-center gap-2 mb-2');
        echo html_writer::span(s($notice['title']));
        echo $OUTPUT->single_button($removeurl, get_string('removenotice', 'local_studenthub'), 'post');
        echo html_writer::end_div();
    }
}
echo $OUTPUT->footer();
