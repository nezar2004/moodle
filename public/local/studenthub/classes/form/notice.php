<?php
namespace local_studenthub\form;

defined('MOODLE_INTERNAL') || die();

/** Editing teacher form for a course-wide notice. */
final class notice extends \moodleform {
    public function definition(): void {
        $form = $this->_form;
        $course = $this->_customdata['course'];
        $activities = $this->_customdata['activities'];
        $options = [];
        foreach ($activities as $cm) {
            $options[$cm->id] = $cm->get_formatted_name();
        }
        $form->addElement('hidden', 'id', $course->id);
        $form->setType('id', PARAM_INT);
        $form->addElement('select', 'cmid', get_string('assessment', 'local_studenthub'), $options);
        $form->addElement('select', 'kind', get_string('noticetype', 'local_studenthub'), [
            'cancel' => get_string('cancelassessment', 'local_studenthub'),
            'reschedule' => get_string('rescheduleassessment', 'local_studenthub'),
        ]);
        $form->addElement('static', 'cancelhelp', '', get_string('cancelhelp', 'local_studenthub'));
        $form->hideIf('cancelhelp', 'kind', 'neq', 'cancel');
        $form->addElement('date_time_selector', 'newtime', get_string('newassessmenttime', 'local_studenthub'), ['optional' => true]);
        $form->hideIf('newtime', 'kind', 'neq', 'reschedule');
        $form->addElement('textarea', 'note', get_string('notemessage', 'local_studenthub'), ['rows' => 3, 'maxlength' => 250]);
        $form->setType('note', PARAM_TEXT);
        $this->add_action_buttons(true, get_string('publishnotice', 'local_studenthub'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ($data['kind'] === 'reschedule' && empty($data['newtime'])) {
            $errors['newtime'] = get_string('newtimerequired', 'local_studenthub');
        }
        return $errors;
    }
}
