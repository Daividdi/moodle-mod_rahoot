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
 * Renders one Rahoot activity.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/rahoot/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('id', 0, PARAM_INT);
$r  = optional_param('r', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('rahoot', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $rahoot = $DB->get_record('rahoot', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $rahoot = $DB->get_record('rahoot', ['id' => $r], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $rahoot->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('rahoot', $rahoot->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/rahoot:view', $context);

$event = \mod_rahoot\event\course_module_viewed::create([
    'objectid' => $rahoot->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('rahoot', $rahoot);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Bring this viewer's own result up to date before drawing the page, so a quiz
// just finished shows a grade now instead of at the next cron run. Throttled by
// a short lived cache and given a short timeout: the page must still render if
// Rahoot is slow, and one visit must mean at most one request.
if ((int)$rahoot->grade !== 0 && isloggedin() && !isguestuser()) {
    $throttle = cache::make('mod_rahoot', 'lastsync');
    $chave = $rahoot->id . '_' . $USER->id;
    if (!$throttle->get($chave)) {
        $throttle->set($chave, time());
        rahoot_sync_results($rahoot, core_text::strtolower($USER->username), 4);
    }
}

$PAGE->set_url('/mod/rahoot/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . format_string($rahoot->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($rahoot);

echo $OUTPUT->header();

$quizurl = rahoot_quiz_url($rahoot->quizid);

if ($quizurl === null) {
    // Either the site was never configured or the stored identifier is not
    // usable. Say which, because the two need different people to fix them.
    $message = rahoot_base_url() === ''
        ? get_string('nobaseurl', 'mod_rahoot')
        : get_string('quizinvalid', 'mod_rahoot');
    echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->footer();
    die;
}

// The height is handed to CSS as a custom property rather than written onto the
// iframe, so the stylesheet keeps the min-height and the fullscreen rules in
// one place instead of fighting an inline style.
$frameattrs = ['class' => 'mod-rahoot-frame'];
$height = rahoot_effective_height($rahoot);
if ($height > 0) {
    $frameattrs['style'] = '--rahoot-height: ' . $height . 'px;';
}

echo html_writer::start_div('mod-rahoot-embed', ['id' => 'rahoot-embed']);

echo html_writer::start_div('mod-rahoot-toolbar');
// Only a real subject is worth showing. The bare identifier is a file name,
// which tells a student nothing and just eats width.
if (trim((string)$rahoot->quizsubject) !== '') {
    echo html_writer::tag('span', s($rahoot->quizsubject), ['class' => 'mod-rahoot-label']);
}
echo html_writer::tag('button', get_string('fullscreen', 'mod_rahoot'), [
    'type'  => 'button',
    'class' => 'btn btn-secondary btn-sm mod-rahoot-fullscreen',
    'id'    => 'rahoot-fullscreen',
]);
echo html_writer::link($quizurl, get_string('openinnewtab', 'mod_rahoot'), [
    'class'  => 'btn btn-link btn-sm',
    'target' => '_blank',
    'rel'    => 'noopener noreferrer',
]);
if (has_capability('mod/rahoot:viewallresults', context_course::instance($course->id))) {
    echo html_writer::link(
        new moodle_url('/mod/rahoot/results.php', ['rahootid' => $rahoot->id]),
        get_string('viewallresults', 'mod_rahoot'),
        ['class' => 'btn btn-secondary btn-sm']
    );
}
echo html_writer::end_div();

// The person's own standing, in the words the quiz used: correct out of asked,
// and which attempt it came from. The gradebook shows the converted number;
// this shows what actually happened.
$meu = $DB->get_record('rahoot_attempts', ['rahootid' => $rahoot->id, 'userid' => $USER->id]);
if ($meu && (int)$meu->attempts > 0) {
    $melhor = ($rahoot->grademethod === 'last') ? 'last' : 'best';
    $a = (object)[
        'correct' => (int)$meu->{$melhor . 'correct'},
        'total'   => (int)$meu->{$melhor . 'total'},
        'percent' => format_float((float)$meu->{$melhor . 'percent'}, 1, true, true),
        'attempt' => (int)$meu->{$melhor . 'attempt'},
        'attempts' => (int)$meu->attempts,
    ];
    $chave = ($rahoot->grademethod === 'last') ? 'yourresultlast' : 'yourresultbest';
    echo html_writer::div(get_string($chave, 'mod_rahoot', $a), 'mod-rahoot-yourresult');
}

echo html_writer::start_tag('div', $frameattrs);
// Must be tag(), never empty_tag(): <iframe> is not a void element, and a
// self-closing one is parsed as an opening tag that swallows the rest of the
// document -- footer, scripts and all.
echo html_writer::tag('iframe', '', [
    'src'             => $quizurl->out(false),
    'title'           => format_string($rahoot->name),
    'class'           => 'mod-rahoot-iframe',
    'allowfullscreen' => 'allowfullscreen',
    'allow'           => 'fullscreen',
]);
echo html_writer::end_tag('div');

echo html_writer::end_div();

$PAGE->requires->js_amd_inline("
require([], function() {
    var button = document.getElementById('rahoot-fullscreen');
    var frame = document.querySelector('#rahoot-embed .mod-rahoot-frame');
    if (!button || !frame || !frame.requestFullscreen) {
        if (button) {
            button.style.display = 'none';
        }
        return;
    }
    button.addEventListener('click', function() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            frame.requestFullscreen();
        }
    });
});
");

echo $OUTPUT->footer();
