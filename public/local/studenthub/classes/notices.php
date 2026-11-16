<?php
namespace local_studenthub;

use cm_info;
use context_course;
use core\event\course_deleted;
use core\event\course_module_deleted;
use core\event\course_module_updated;
use core_courseformat\formatactions;
use stdClass;

/** Course-wide assessment notices explicitly published by editing teachers. */
final class notices {
    public const TABLE = 'local_studenthub_notice';
    public const KINDS = ['cancel', 'reschedule'];

    /** Editing teachers can select an assessment in the current course. */
    public static function activities(stdClass $course): array {
        return array_filter(get_fast_modinfo($course)->get_cms(), static fn(cm_info $cm): bool =>
            in_array($cm->modname, ['assign', 'quiz'], true) && !$cm->deletioninprogress);
    }

    /** Determine the previous time from the active notice or original Moodle activity. */
    public static function previous_time(cm_info $cm, ?stdClass $existing = null): int {
        global $DB;
        if ($existing && $existing->kind === 'reschedule') {
            return (int) $existing->newtime;
        }

        if ($cm->modname === 'assign') {
            return (int) $DB->get_field('assign', 'duedate', ['id' => $cm->instance], MUST_EXIST);
        }
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id, timeopen, timeclose', MUST_EXIST);
        return (int) ($quiz->timeopen ?: $quiz->timeclose);
    }

    /** Hide a cancelled assessment and publish its notice as one operation. */
    public static function publish(stdClass $course, int $cmid, string $kind, int $newtime, string $note): void {
        global $DB;
        require_capability('moodle/course:manageactivities', context_course::instance($course->id));
        $cm = self::activities($course)[$cmid] ?? null;
        if (!$cm || !in_array($kind, self::KINDS, true) || ($kind === 'reschedule' && $newtime <= 0)) {
            throw new \moodle_exception('invalidnotice', 'local_studenthub');
        }
        if ($kind === 'cancel') {
            require_capability('moodle/course:activityvisibility', $cm->context);
        }

        $factory = \core\lock\lock_config::get_lock_factory('local_studenthub_notice');
        $lock = $factory->get_lock((string) $cmid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'error');
        }

        try {
            $existing = $DB->get_record(self::TABLE, ['cmid' => $cmid]);
            $oldtime = $kind === 'reschedule' ? self::previous_time($cm, $existing ?: null) : 0;
            if ($kind === 'reschedule' && $newtime === $oldtime) {
                if (!$existing || $existing->kind !== 'reschedule') {
                    throw new \moodle_exception('sameassessmenttime', 'local_studenthub');
                }
                // Editing the note for an already published time preserves the original comparison.
                $oldtime = (int) $existing->oldtime;
            }
            $record = (object) [
                'courseid' => $course->id,
                'cmid' => $cmid,
                'kind' => $kind,
                'oldtime' => $oldtime,
                'newtime' => $kind === 'reschedule' ? $newtime : 0,
                'note' => \core_text::substr(trim($note), 0, 250),
                'timemodified' => time(),
            ];
            $transaction = $DB->start_delegated_transaction();
            try {
                if ($kind === 'cancel') {
                    $activity = $DB->get_record('course_modules', ['id' => $cmid, 'course' => $course->id], '*', MUST_EXIST);
                    if ($activity->deletioninprogress
                            || !formatactions::cm($course->id)->set_visibility($cmid, 0, (int) $activity->visibleoncoursepage, false)) {
                        throw new \moodle_exception('invalidnotice', 'local_studenthub');
                    }
                    if ($activity->visible) {
                        course_module_updated::create_from_cm($cm, $cm->context)->trigger();
                    }
                }
                if ($existing) {
                    $record->id = $existing->id;
                    $DB->update_record(self::TABLE, $record);
                } else {
                    $DB->insert_record(self::TABLE, $record);
                }
                $transaction->allow_commit();
            } catch (\Throwable $error) {
                $transaction->rollback($error);
            }
            if ($kind === 'cancel') {
                \course_modinfo::purge_course_module_cache($course->id, $cmid);
                rebuild_course_cache($course->id, false, true);
            }
        } finally {
            $lock->release();
        }
    }

    /** Current notices, newest first, for courses already authorized by the caller. */
    public static function for_courses(array $courses, bool $includeinactive = false): array {
        global $DB;
        if (!$courses) {
            return [];
        }
        $courseids = array_column($courses, 'id');
        $records = $DB->get_records_list(self::TABLE, 'courseid', $courseids, 'timemodified DESC, id DESC');
        $bycourse = [];
        foreach ($records as $record) {
            $modinfo = get_fast_modinfo($record->courseid);
            $cm = $modinfo->get_cms()[$record->cmid] ?? null;
            if (!$cm || $cm->deletioninprogress || (!$includeinactive && $record->kind === 'cancel' && $cm->visible)) {
                continue;
            }
            $name = \core_text::entities_to_utf8(format_string($cm->name, true, ['context' => $cm->context]));
            $date = get_string('strftimedatetimeshort');
            $bycourse[$record->courseid][] = [
                'id' => $record->id,
                'cmid' => $record->cmid,
                'title' => get_string('notice' . $record->kind, 'local_studenthub', $name),
                'oldtime' => $record->oldtime ? userdate($record->oldtime, $date) : get_string('nodate', 'local_studenthub'),
                'newtime' => $record->newtime ? userdate($record->newtime, $date) : null,
                'note' => $record->note,
            ];
        }
        return $bycourse;
    }

    /** Remove an active notice only from the current course. */
    public static function remove(stdClass $course, int $noticeid): void {
        global $DB;
        require_capability('moodle/course:manageactivities', context_course::instance($course->id));
        $record = $DB->get_record(self::TABLE, ['id' => $noticeid, 'courseid' => $course->id]);
        if (!$record) {
            return;
        }
        $lock = \core\lock\lock_config::get_lock_factory('local_studenthub_notice')->get_lock((string) $record->cmid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'error');
        }
        try {
            $DB->delete_records(self::TABLE, ['id' => $noticeid, 'courseid' => $course->id]);
        } finally {
            $lock->release();
        }
    }

    public static function module_deleted(course_module_deleted $event): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cmid' => $event->objectid]);
    }

    public static function course_deleted(course_deleted $event): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $event->objectid]);
    }
}
