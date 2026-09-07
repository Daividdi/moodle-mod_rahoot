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
 * Backup structure step for mod_rahoot.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines what goes into rahoot.xml.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_rahoot_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $rahoot = new backup_nested_element('rahoot', ['id'], [
            'name', 'intro', 'introformat', 'quizid', 'quizsubject',
            'mode', 'height', 'grade', 'grademethod', 'timecreated', 'timemodified',
        ]);

        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'userid', 'account', 'attempts',
            'bestpercent', 'bestcorrect', 'besttotal', 'bestpoints', 'bestattempt', 'besttime',
            'lastpercent', 'lastcorrect', 'lasttotal', 'lastpoints', 'lastattempt', 'lasttime',
            'timemodified',
        ]);

        $rahoot->add_child($attempts);
        $attempts->add_child($attempt);

        $rahoot->set_source_table('rahoot', ['id' => backup::VAR_ACTIVITYID]);

        // Results are user data, so they travel only when the backup was asked
        // to include it. A course copied as a template must not carry the last
        // cohort's scores into the next one.
        if ($userinfo) {
            $attempt->set_source_table('rahoot_attempts', ['rahootid' => backup::VAR_PARENTID]);
        }

        $attempt->annotate_ids('user', 'userid');

        $rahoot->annotate_files('mod_rahoot', 'intro', null);

        return $this->prepare_activity_structure($rahoot);
    }
}
