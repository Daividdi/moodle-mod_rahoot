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
 * Restore structure step for mod_rahoot.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Reads rahoot.xml back into the database.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_rahoot_activity_structure_step extends restore_structure_step {

    /**
     * Defines the paths.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('rahoot', '/activity/rahoot');
        if ($userinfo) {
            $paths[] = new restore_path_element('rahoot_attempt', '/activity/rahoot/attempts/attempt');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores one instance.
     *
     * @param array $data
     * @return void
     */
    protected function process_rahoot($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('rahoot', $data);

        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restores one person's result.
     *
     * @param array $data
     * @return void
     */
    protected function process_rahoot_attempt($data) {
        global $DB;

        $data = (object)$data;
        $data->rahootid = $this->get_new_parentid('rahoot');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // A backup can name a user this site does not have. Dropping the row is
        // right: the alternative is a result attached to whoever happens to
        // hold that id here.
        if (empty($data->userid)) {
            return;
        }

        $data->besttime = $this->apply_date_offset($data->besttime);
        $data->lasttime = $this->apply_date_offset($data->lasttime);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        unset($data->id);
        $DB->insert_record('rahoot_attempts', $data);
    }

    /**
     * Reattaches the description files.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_rahoot', 'intro', null);
    }
}
