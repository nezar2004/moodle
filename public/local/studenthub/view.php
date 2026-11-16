<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/gradelib.php');

$id = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'materials', PARAM_ALPHA);
$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
if ($course->id == SITEID || (!is_enrolled($context, null, '', true) && !has_capability('moodle/course:update', $context))) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('pluginname', 'local_studenthub'));
}

$canviewgrades = $course->showgrades && has_capability('moodle/grade:view', $context)
    && has_capability('gradereport/user:view', $context);
$tabs = ['materials', 'assessments', 'grades', 'announcements', 'changes'];
if (!$canviewgrades) {
    $tabs = array_values(array_diff($tabs, ['grades']));
}
if (!in_array($tab, $tabs, true)) {
    $tab = 'materials';
}

$url = new moodle_url('/local/studenthub/view.php', ['id' => $id, 'tab' => $tab]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_studenthub'));
$PAGE->set_heading(format_string($course->fullname));

$data = [
    'tabs' => array_map(static fn(string $name): array => [
        'label' => get_string($name, 'local_studenthub'),
        'url' => (new moodle_url('/local/studenthub/view.php', ['id' => $id, 'tab' => $name]))->out(false),
        'active' => $name === $tab,
    ], $tabs),
    'title' => get_string($tab, 'local_studenthub'),
];
$items = \local_studenthub\course_items::get($course, $tab === 'announcements');
$changes = \local_studenthub\change_log::update($id, $items);
$dateformat = get_string('strftimedatetimeshort');
$formatdate = static fn(int $time): string => $time
    ? userdate($time, $dateformat)
    : get_string('nodate', 'local_studenthub');

if ($tab === 'changes') {
    $data['intro'] = get_string('changeintro', 'local_studenthub');
    foreach ($changes as $change) {
        $item = $items[$change['id']];
        $data['entries'][] = [
            'name' => $item['name'],
            'url' => $item['url'],
            'detail' => get_string($change['type'], 'local_studenthub', [
                'old' => $formatdate($change['old'] ?? 0),
                'new' => $formatdate($change['new'] ?? 0),
            ]),
            'date' => $formatdate($change['time']),
        ];
    }
} else if ($tab === 'grades') {
    require_once($CFG->dirroot . '/grade/report/user/lib.php');
    $indicator = new \core\output\task_indicator(
        \core_course\task\regrade_final_grades::create($id),
        get_string('recalculatinggrades', 'grades'),
        get_string('recalculatinggradesadhoc', 'grades'),
        $url,
    );
    if (!$indicator->has_task_record()) {
        grade_regrade_final_grades_if_required($course);
    }
    $data['intro'] = get_string('gradeintro', 'local_studenthub');
    if ($indicator->has_task_record()) {
        $data['gradeshtml'] = $OUTPUT->render($indicator);
    } else {
        $report = new \gradereport_user\report\user($id, null, $context, $USER->id, true);
        $report->showhiddenitems = GRADE_REPORT_USER_HIDE_HIDDEN;
        $report->canviewhidden = false;
        $report->showgrade = true;
        $report->showrange = true;
        $report->showweight = true;
        $report->showcontributiontocoursetotal = true;
        $report->showaverage = false;
        $report->setup_table();
        if ($report->fill_table()) {
            $statuses = [];
            foreach ($report->gradeitemsdata as $grade) {
                if (!in_array($grade['itemtype'], ['mod', 'manual'], true)
                        || $grade['graderaw'] !== null || $grade['gradeneedsupdate']) {
                    continue;
                }
                $submitted = !empty($grade['gradedatesubmitted']) || ($grade['itemmodule'] === 'assign'
                    && \local_studenthub\course_items::is_assignment_submitted($course, (int) $grade['iteminstance']));
                $status = $submitted && empty($grade['gradedategraded']) && !$grade['gradeishidden']
                    ? 'pendinggrading' : 'nogradereleased';
                $statuses["row_{$grade['id']}_{$USER->id}"] = get_string($status, 'local_studenthub');
            }
            foreach ($report->tabledata as $index => $row) {
                $rowid = $row['itemname']['id'] ?? null;
                if (isset($statuses[$rowid])) {
                    $report->tabledata[$index]['grade']['content'] = s($statuses[$rowid]);
                }
            }
            $data['gradeshtml'] = $report->print_table(true);
        }
    }
} else {
    $listed = array_values(array_filter($items, static fn(array $item): bool => $item['type'] === $tab));
    if ($tab === 'assessments') {
        usort($listed, static fn(array $a, array $b): int => ($a['due'] ?: PHP_INT_MAX) <=> ($b['due'] ?: PHP_INT_MAX));
    }
    foreach ($listed as $item) {
        $item['detail'] = $item['section'];
        $item['date'] = $tab === 'assessments' ? $formatdate($item['due']) : null;
        $data['entries'][] = $item;
    }
}

$data['empty'] = empty($data['entries']) && empty($data['gradeshtml']);
$data['emptytext'] = get_string('empty' . $tab, 'local_studenthub');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_studenthub/view', $data);
echo $OUTPUT->footer();
