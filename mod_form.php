<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * Settings form for a Rahoot activity.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/rahoot/locallib.php');

/**
 * Activity settings form.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_rahoot_mod_form extends moodleform_mod {

    /** @var array|null Catalogue read once and reused by definition and validation. */
    protected $catalogue = null;

    /**
     * Form definition.
     *
     * @return void
     */
    public function definition() {
        global $COURSE, $PAGE;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'rahootsettings', get_string('rahootsettings', 'mod_rahoot'));
        $mform->setExpanded('rahootsettings');

        if (rahoot_base_url() === '') {
            $mform->addElement('static', 'nobaseurl', '',
                $this->warning(get_string('nobaseurl', 'mod_rahoot')));
        }

        $this->catalogue = rahoot_fetch_catalogue();

        if ($this->catalogue !== null) {
            $options = ['' => get_string('choosequiz', 'mod_rahoot')]
                + rahoot_catalogue_options($this->catalogue);
            $mform->addElement('select', 'quizid', get_string('quiz', 'mod_rahoot'), $options);
            $mform->setType('quizid', PARAM_RAW);
            $mform->addHelpButton('quizid', 'quiz', 'mod_rahoot');
            // Um quiz criado agora aparece aqui em ate 30 s. Quem nao quer
            // esperar nem isso tem o link — e ele diz quantos quizzes a lista
            // tem, para a pessoa saber se recarregou de fato.
            $mform->addElement('static', 'cataloguerefresh', '',
                html_writer::link(
                    new moodle_url('/mod/rahoot/refresh.php', [
                        'course'    => $COURSE->id,
                        'sesskey'   => sesskey(),
                        'returnurl' => $PAGE->url->out_as_local_url(false),
                    ]),
                    get_string('cataloguerefresh', 'mod_rahoot', count($this->catalogue))
                ));
        } else {
            $mform->addElement('static', 'catalogueunavailable', '',
                $this->warning(get_string('catalogueunavailable', 'mod_rahoot')));
            $mform->addElement('hidden', 'quizid', '');
            $mform->setType('quizid', PARAM_RAW);
        }

        // Always present, so a teacher is never stuck when the catalogue is
        // unreachable or the quiz is newer than the cached list.
        $mform->addElement('text', 'quizidmanual', get_string('quizidmanual', 'mod_rahoot'), [
            'size' => 64,
            // The placeholder carries the message the label cannot: the whole
            // address pasted from Rahoot is fine, nothing has to be trimmed off.
            'placeholder' => get_string('quizidmanualplaceholder', 'mod_rahoot'),
        ]);
        $mform->setType('quizidmanual', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('quizidmanual', 'quizidmanual', 'mod_rahoot');

        $mform->addElement('text', 'height', get_string('height', 'mod_rahoot'), ['size' => 6]);
        $mform->setType('height', PARAM_INT);
        $mform->setDefault('height', 0);
        $mform->addHelpButton('height', 'height', 'mod_rahoot');

        // Adds the Grade header, the maximum and the pass grade. Everything
        // below it belongs to that section, which is why the rule that picks
        // the attempt is added after and not before.
        $this->standard_grading_coursemodule_elements();

        $mform->addElement('select', 'grademethod', get_string('grademethod', 'mod_rahoot'), [
            'highest' => get_string('grademethodhighest', 'mod_rahoot'),
            'last'    => get_string('grademethodlast', 'mod_rahoot'),
        ]);
        $mform->setType('grademethod', PARAM_ALPHA);
        $mform->setDefault('grademethod', 'highest');
        $mform->addHelpButton('grademethod', 'grademethod', 'mod_rahoot');
        $mform->hideIf('grademethod', 'grade[modgrade_type]', 'eq', 'none');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Wraps a message so it reads as a warning rather than as body text.
     *
     * @param string $text
     * @return string
     */
    protected function warning($text) {
        global $OUTPUT;

        return $OUTPUT->notification($text, \core\output\notification::NOTIFY_WARNING, false);
    }

    /**
     * Puts the stored identifier back into whichever field can hold it.
     *
     * @param array $defaultvalues
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        if (empty($defaultvalues['quizid'])) {
            return;
        }

        $stored = $defaultvalues['quizid'];
        $known = false;

        if ($this->catalogue !== null) {
            foreach ($this->catalogue as $entry) {
                if ($entry->id === $stored) {
                    $known = true;
                    break;
                }
            }
        }

        // A quiz that the catalogue does not know about would silently reset the
        // select to "choose", quietly losing the teacher's choice on every save.
        if (!$known) {
            $defaultvalues['quizidmanual'] = $stored;
            $defaultvalues['quizid'] = '';
        }
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $raw = !empty($data['quizidmanual']) ? $data['quizidmanual'] : ($data['quizid'] ?? '');

        if (trim((string)$raw) === '') {
            $errors['quizidmanual'] = get_string('quizrequired', 'mod_rahoot');
        } else if (rahoot_normalise_quizid($raw) === '') {
            $errors['quizidmanual'] = get_string('quizinvalid', 'mod_rahoot');
        }

        if (!empty($data['height']) && $data['height'] > 0 && $data['height'] < 200) {
            $errors['height'] = get_string('heighttoosmall', 'mod_rahoot');
        }

        return $errors;
    }
}
