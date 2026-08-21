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
 * English strings for mod_rahoot.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Rahoot';
$string['modulename'] = 'Rahoot';
$string['modulenameplural'] = 'Rahoot quizzes';
$string['modulename_help'] = 'The Rahoot activity embeds a self paced quiz from your Rahoot server directly in the course, sized to the content area.

Pick a quiz from the list, or paste the address of one, and students take it without leaving Moodle.';
$string['pluginadministration'] = 'Rahoot administration';

$string['rahoot:addinstance'] = 'Add a new Rahoot activity';
$string['rahoot:view'] = 'View a Rahoot activity';

$string['rahootsettings'] = 'Quiz';
$string['quiz'] = 'Quiz';
$string['quiz_help'] = 'The list is read from your Rahoot server. If a quiz was created in the last few minutes and is missing, use the address field below.';
$string['choosequiz'] = 'Choose a quiz...';
$string['quizidmanual'] = 'Quiz address';
$string['quizidmanualplaceholder'] = 'https://your-rahoot-server/solo/quiz-example-1770000000000.json';
$string['quizidmanual_help'] = 'Paste the whole address exactly as Rahoot gives it to you. Nothing has to be trimmed off, and a query string or a missing .json is fine too.

Just the identifier on its own works as well. Whichever you use, the activity is always built from the Rahoot server set for this site, so the host in what you paste is ignored.

Filling this in overrides the selection above.';
$string['quizrequired'] = 'Choose a quiz from the list, or paste its address.';
$string['quizinvalid'] = 'That does not look like a Rahoot quiz address.';
$string['catalogueunavailable'] = 'The quiz list could not be read from the Rahoot server, so it is not shown. Paste the address of the quiz instead.';
$string['nobaseurl'] = 'No Rahoot server has been set for this site yet. An administrator needs to fill it in under Site administration, Plugins, Activity modules, Rahoot.';
$string['nquestions'] = '{$a} questions';
$string['nattempts'] = '{$a} tries each';

$string['height'] = 'Fixed height';
$string['height_help'] = 'Height of the embedded quiz in pixels. Leave at 0 to let it size itself to the browser window, which is what suits most screens.';
$string['heighttoosmall'] = 'Use 0 for the automatic height, or at least 200 pixels.';

$string['fullscreen'] = 'Full screen';
$string['openinnewtab'] = 'Open in a new tab';
$string['noinstances'] = 'There are no Rahoot activities in this course.';

$string['baseurl'] = 'Rahoot server address';
$string['baseurl_desc'] = 'Base address of your Rahoot installation, with no trailing slash, for example https://rahoot.example.org. The quiz list is read from this address and every activity is built from it.';
$string['defaultheight'] = 'Default fixed height';
$string['defaultheight_desc'] = 'Height in pixels used by activities that do not set their own. Leave at 0 to size the quiz to the browser window instead, which is usually the better choice.';

$string['privacy:metadata'] = 'The Rahoot activity does not store any personal data. The quiz runs on the Rahoot server, which keeps its own records.';
