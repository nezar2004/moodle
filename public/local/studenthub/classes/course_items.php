<?php
namespace local_studenthub;

use cm_info;
use context_module;
use moodle_url;
use stdClass;

/** Activities available to the current user, independent of course layout. */
final class course_items {
    private const MATERIALS = ['resource', 'folder', 'page', 'book', 'url', 'imscp', 'lesson', 'lti'];

    /** Collect visible links and the values used to detect updates. */
    public static function get(stdClass $course, bool $includeannouncements = false): array {
        global $DB, $USER;

        $modinfo = get_fast_modinfo($course);
        $forums = $DB->get_records_menu('forum', ['course' => $course->id], '', 'id, type');
        $extensions = $DB->get_records_sql_menu(
            'SELECT f.assignment, f.extensionduedate
               FROM {assign_user_flags} f
               JOIN {assign} a ON a.id = f.assignment
              WHERE a.course = :course AND f.userid = :userid AND f.extensionduedate > 0',
            ['course' => $course->id, 'userid' => $USER->id],
        );
        $items = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || !$cm->url || $cm->deletioninprogress) {
                continue;
            }

            $type = match (true) {
                in_array($cm->modname, self::MATERIALS, true) => 'materials',
                $cm->modname === 'forum' && ($forums[$cm->instance] ?? '') === 'news' => 'announcements',
                plugin_supports('mod', $cm->modname, FEATURE_GRADE_HAS_GRADE, false) => 'assessments',
                default => null,
            };
            $capability = $cm->modname === 'forum' ? 'mod/forum:viewdiscussion' : "mod/{$cm->modname}:view";
            if (!$type || !has_capability($capability, context_module::instance($cm->id))) {
                continue;
            }

            $section = $modinfo->get_section_info($cm->sectionnum);
            $due = (int) (($cm->customdata['duedate'] ?? 0) ?: ($cm->customdata['timeclose'] ?? 0));
            if ($cm->modname === 'assign') {
                $due = max($due, (int) ($extensions[$cm->instance] ?? 0));
            }
            $items[$cm->id] = [
                'type' => $type,
                'name' => $cm->get_formatted_name(),
                'module' => $cm->modfullname,
                'url' => $cm->url->out(false),
                'section' => get_section_name($course, $section),
                'due' => $due,
                'files' => self::fingerprint($cm),
                'recent' => $includeannouncements && $type === 'announcements' ? self::announcements($cm) : [],
            ];
        }

        return $items;
    }

    /** Establish a baseline even if the student opens the course's usual page first. */
    public static function record_course_visit(\core_course\hook\before_course_viewed $hook): void {
        $course = $hook->course;
        if (is_enrolled(\context_course::instance($course->id), null, '', true)) {
            change_log::update($course->id, self::get($course));
        }
    }

    /** Check the assignment's own submission state for the current student. */
    public static function is_assignment_submitted(stdClass $course, int $instanceid): bool {
        global $CFG, $USER;

        $cm = get_fast_modinfo($course)->get_instances_of('assign')[$instanceid] ?? null;
        if (!$cm || !$cm->uservisible || !has_capability('mod/assign:view', $cm->context)) {
            return false;
        }
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $assignment = new \assign($cm->context, $cm, $course);
        $submission = $assignment->get_instance()->teamsubmission
            ? $assignment->get_group_submission($USER->id, 0, false)
            : $assignment->get_user_submission($USER->id, false);

        return $submission && $submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED;
    }

    /** Hash attachment content and relative paths, including replacements with the same filename. */
    private static function fingerprint(cm_info $cm): string {
        $area = match ($cm->modname) {
            'resource', 'folder', 'imscp' => 'content',
            'page' => 'content',
            default => null,
        };
        if (!$area) {
            return '';
        }
        $files = get_file_storage()->get_area_files($cm->context->id, 'mod_' . $cm->modname, $area, 0,
            'filepath, filename', false);
        $signatures = [];
        foreach ($files as $file) {
            $signatures[] = [$file->get_filepath(), $file->get_filename(), $file->get_contenthash()];
        }
        return hash('sha256', json_encode($signatures, JSON_THROW_ON_ERROR));
    }

    /** Get recent announcement posts using the forum's group and visibility checks. */
    private static function announcements(cm_info $cm): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $discussions = forum_get_discussions($cm, 'd.timemodified DESC', false, -1, 5);
        $results = [];
        foreach ($discussions ?: [] as $discussion) {
            $results[] = [
                'title' => format_string($discussion->name, true, ['context' => $cm->context]),
                'url' => (new moodle_url('/mod/forum/discuss.php', ['d' => $discussion->discussion]))->out(false),
                'date' => userdate($discussion->timemodified, get_string('strftimedatetimeshort')),
            ];
        }
        return $results;
    }
}
