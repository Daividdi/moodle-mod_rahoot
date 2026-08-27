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
 * Discards the cached quiz catalogue and returns to where the teacher was.
 *
 * The catalogue is cached so a Rahoot outage does not slow every form load.
 * That is right for the machine and wrong for the person who just created a
 * quiz next door and wants it in the list now. This gives them the "now".
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('course', PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$course = get_course($courseid);
require_login($course);
require_sesskey();
// Only someone who could add the activity may spend a request on Rahoot.
require_capability('moodle/course:manageactivities', context_course::instance($course->id));

cache::make('mod_rahoot', 'quizzes')->delete('catalogue');

redirect(
    $returnurl !== '' ? new moodle_url($returnurl) : new moodle_url('/course/view.php', ['id' => $course->id]),
    get_string('cataloguerefreshed', 'mod_rahoot'),
    0,
    \core\output\notification::NOTIFY_SUCCESS
);
