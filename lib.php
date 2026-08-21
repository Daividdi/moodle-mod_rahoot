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
 * Library of interface functions for mod_rahoot.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Declares which Moodle features this module supports.
 *
 * @param string $feature One of the FEATURE_* constants.
 * @return mixed
 */
function rahoot_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Adds a new instance.
 *
 * @param stdClass $data Form data.
 * @param mod_rahoot_mod_form|null $mform The form, when submitted from the web.
 * @return int New instance id.
 */
function rahoot_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    rahoot_prepare_record($data);

    $data->id = $DB->insert_record('rahoot', $data);

    return $data->id;
}

/**
 * Updates an existing instance.
 *
 * @param stdClass $data Form data.
 * @param mod_rahoot_mod_form|null $mform The form, when submitted from the web.
 * @return bool
 */
function rahoot_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;
    rahoot_prepare_record($data);

    return $DB->update_record('rahoot', $data);
}

/**
 * Normalises the fields that are shared by add and update.
 *
 * Kept out of the two entry points on purpose: the quiz identifier may arrive
 * from the select, from the free text fallback, or from a restore, and all
 * three have to end up stored in the same canonical shape.
 *
 * @param stdClass $data Form data, modified in place.
 * @return void
 */
function rahoot_prepare_record($data) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/rahoot/locallib.php');

    $raw = '';
    if (!empty($data->quizidmanual)) {
        $raw = $data->quizidmanual;
    } else if (!empty($data->quizid)) {
        $raw = $data->quizid;
    }

    $data->quizid = rahoot_normalise_quizid($raw);
    $data->mode = 'solo';

    if (empty($data->quizsubject)) {
        $data->quizsubject = rahoot_lookup_subject($data->quizid);
    }

    if (!isset($data->height) || $data->height < 0) {
        $data->height = 0;
    }

    unset($data->quizidmanual);
}

/**
 * Deletes an instance.
 *
 * @param int $id Instance id.
 * @return bool
 */
function rahoot_delete_instance($id) {
    global $DB;

    if (!$rahoot = $DB->get_record('rahoot', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('rahoot', ['id' => $rahoot->id]);

    return true;
}

/**
 * Adds the description to the course page when it is set to be shown there.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|bool
 */
function rahoot_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat';
    if (!$rahoot = $DB->get_record('rahoot', ['id' => $coursemodule->instance], $fields)) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $rahoot->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('rahoot', $rahoot, $coursemodule->id, false);
    }

    return $info;
}
