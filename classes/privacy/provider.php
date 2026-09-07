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
 * Privacy provider for mod_rahoot.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_rahoot\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Describes and exports the results this activity keeps.
 *
 * The quiz itself runs in Rahoot, which keeps its own records. What is stored
 * here is the copy of a person's result that the gradebook is built from.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describes what is stored, and what leaves the site.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('rahoot_attempts', [
            'userid'      => 'privacy:metadata:rahoot_attempts:userid',
            'account'     => 'privacy:metadata:rahoot_attempts:account',
            'attempts'    => 'privacy:metadata:rahoot_attempts:attempts',
            'bestpercent' => 'privacy:metadata:rahoot_attempts:bestpercent',
            'besttime'    => 'privacy:metadata:rahoot_attempts:besttime',
            'lastpercent' => 'privacy:metadata:rahoot_attempts:lastpercent',
            'lasttime'    => 'privacy:metadata:rahoot_attempts:lasttime',
        ], 'privacy:metadata:rahoot_attempts');

        // The identity is not sent anywhere: this site reads results that
        // Rahoot already holds, matching on the directory account both systems
        // authenticate against.
        $collection->add_external_location_link('rahoot', [
            'account' => 'privacy:metadata:rahoot:account',
        ], 'privacy:metadata:rahoot');

        return $collection;
    }

    /**
     * Contexts where this user has a result.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {rahoot_attempts} ra
                  JOIN {rahoot} r ON r.id = ra.rahootid
                  JOIN {course_modules} cm ON cm.instance = r.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE ra.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'modname'      => 'rahoot',
            'contextlevel' => CONTEXT_MODULE,
            'userid'       => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Users who have a result in this context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT ra.userid
                  FROM {rahoot_attempts} ra
                  JOIN {rahoot} r ON r.id = ra.rahootid
                  JOIN {course_modules} cm ON cm.instance = r.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, ['modname' => 'rahoot', 'cmid' => $context->instanceid]);
    }

    /**
     * Exports the results.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('rahoot', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $registro = $DB->get_record('rahoot_attempts', [
                'rahootid' => $cm->instance,
                'userid'   => $user->id,
            ]);
            if (!$registro) {
                continue;
            }

            $dados = helper::get_context_data($context, $user);
            $dados->account = $registro->account;
            $dados->attempts = $registro->attempts;
            $dados->bestresult = $registro->bestcorrect . '/' . $registro->besttotal
                . ' (' . format_float($registro->bestpercent, 2) . '%)';
            $dados->besttime = transform::datetime($registro->besttime);
            $dados->lastresult = $registro->lastcorrect . '/' . $registro->lasttotal
                . ' (' . format_float($registro->lastpercent, 2) . '%)';
            $dados->lasttime = transform::datetime($registro->lasttime);

            writer::with_context($context)->export_data([], $dados);
            helper::export_context_files($context, $user);
        }
    }

    /**
     * Deletes every result in a context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('rahoot', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $DB->delete_records('rahoot_attempts', ['rahootid' => $cm->instance]);
    }

    /**
     * Deletes one user's results.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('rahoot', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $DB->delete_records('rahoot_attempts', ['rahootid' => $cm->instance, 'userid' => $userid]);
        }
    }

    /**
     * Deletes the results of the given users.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('rahoot', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $inparams['rahootid'] = $cm->instance;
        $DB->delete_records_select('rahoot_attempts', "rahootid = :rahootid AND userid $insql", $inparams);
    }
}
