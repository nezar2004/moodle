<?php
defined('MOODLE_INTERNAL') || die();

/** Add a consistent entry point to the Moodle course navigation. */
function local_studenthub_extend_settings_navigation($settingsnav, $context): void {
    global $PAGE;

    if ($PAGE->course->id == SITEID) {
        return;
    }

    $coursecontext = context_course::instance($PAGE->course->id);
    if (!is_enrolled($coursecontext, null, '', true) && !has_capability('moodle/course:update', $coursecontext)) {
        return;
    }

    $coursenode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE);
    if ($coursenode) {
        $coursenode->add(
            get_string('pluginname', 'local_studenthub'),
            new moodle_url('/local/studenthub/view.php', ['id' => $PAGE->course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_studenthub',
        );
    }
}
