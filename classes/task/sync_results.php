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
 * Scheduled task that brings Rahoot results into the gradebook.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_rahoot\task;

/**
 * Copies every activity's solo results from Rahoot and grades them.
 *
 * This task, not the gradebook, is the only thing that talks to Rahoot. Grades
 * are recalculated far more often than anyone plays, and a recalculation must
 * never depend on another service being up.
 */
class sync_results extends \core\task\scheduled_task {

    /**
     * Name shown in the scheduled tasks report.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksyncresults', 'mod_rahoot');
    }

    /**
     * Runs the sync.
     *
     * @return void
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/rahoot/locallib.php');

        if (rahoot_base_url() === '') {
            mtrace('mod_rahoot: no base URL configured, nothing to sync.');
            return;
        }

        $instancias = $DB->get_records('rahoot');
        if (!$instancias) {
            return;
        }

        $total = ['synced' => 0, 'unknown' => 0, 'notenrolled' => 0];
        $falhas = 0;

        foreach ($instancias as $rahoot) {
            if (trim((string)$rahoot->quizid) === '' || (int)$rahoot->grade === 0) {
                // An ungraded activity has nothing to carry into the gradebook,
                // so there is no reason to ask Rahoot about it.
                continue;
            }

            $r = rahoot_sync_results($rahoot);
            if ($r === null) {
                $falhas++;
                mtrace("mod_rahoot: could not read results for '{$rahoot->name}' ({$rahoot->quizid}).");
                continue;
            }

            foreach ($total as $chave => $valor) {
                $total[$chave] = $valor + $r[$chave];
            }
        }

        mtrace(sprintf(
            'mod_rahoot: %d grade(s) updated, %d player(s) with no account here, %d not enrolled, %d activity(ies) unreadable.',
            $total['synced'],
            $total['unknown'],
            $total['notenrolled'],
            $falhas
        ));
    }
}
