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
 * Restore task for mod_rahoot.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/rahoot/backup/moodle2/restore_rahoot_stepslib.php');

/**
 * Assembles the restore of one Rahoot activity.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_rahoot_activity_task extends restore_activity_task {

    /**
     * No particular settings for this activity.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Defines the steps.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_rahoot_activity_structure_step('rahoot_structure', 'rahoot.xml'));
    }

    /**
     * Fields that may contain encoded links.
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('rahoot', ['intro'], 'rahoot');

        return $contents;
    }

    /**
     * Rules that turn the encoded links back into real ones.
     *
     * @return array
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('RAHOOTVIEWBYID', '/mod/rahoot/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('RAHOOTINDEX', '/mod/rahoot/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [];
    }
}
