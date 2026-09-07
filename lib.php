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
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_BACKUP_MOODLE2:
            return true;
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

    rahoot_grade_item_update($data);

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

    $result = $DB->update_record('rahoot', $data);

    // Order matters: the item has to carry the new maximum before the grades
    // are recomputed against it, or every grade is rescaled to the old one.
    rahoot_grade_item_update($data);
    rahoot_update_grades($data);

    return $result;
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

    $DB->delete_records('rahoot_attempts', ['rahootid' => $rahoot->id]);
    rahoot_grade_item_delete($rahoot);
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

/**
 * Turns a stored percentage into the value the gradebook expects.
 *
 * A percentage is the only comparable thing Rahoot produces: its own points are
 * time weighted, so two people who answered exactly the same questions right
 * score differently for having been quicker. That is fair in a game and unfair
 * in a training record, which is why the points are kept for display but never
 * become the grade.
 *
 * @param stdClass $rahoot The activity instance.
 * @param float $percent 0-100.
 * @return float|null Null when the activity is not graded.
 */
function rahoot_percent_to_grade($rahoot, $percent) {
    $max = (int)$rahoot->grade;

    if ($max == 0) {
        return null;
    }

    if ($max > 0) {
        return round(($percent / 100) * $max, 5);
    }

    // Negative maximum means a scale. Its items are equal steps, so the
    // percentage maps onto them by position; anything above zero has to reach
    // at least the first item, never index 0, which is "no grade".
    $scale = grade_scale::fetch(['id' => -$max]);
    if (!$scale) {
        return null;
    }
    $count = count($scale->load_items());
    if ($count < 1) {
        return null;
    }

    return (float)max(1, min($count, (int)ceil(($percent / 100) * $count)));
}

/**
 * Builds the grades for one activity, from the results already synced locally.
 *
 * @param stdClass $rahoot The activity instance.
 * @param int $userid Zero for everyone.
 * @return array Grade objects keyed by user id.
 */
function rahoot_get_user_grades($rahoot, $userid = 0) {
    global $DB;

    $params = ['rahootid' => $rahoot->id];
    if ($userid) {
        $params['userid'] = $userid;
    }

    $campo = ($rahoot->grademethod === 'last') ? 'lastpercent' : 'bestpercent';
    $tempo = ($rahoot->grademethod === 'last') ? 'lasttime' : 'besttime';

    $grades = [];
    foreach ($DB->get_records('rahoot_attempts', $params) as $row) {
        if ((int)$row->attempts < 1) {
            continue;
        }
        $raw = rahoot_percent_to_grade($rahoot, (float)$row->{$campo});
        if ($raw === null) {
            continue;
        }
        $grades[$row->userid] = (object)[
            'userid'       => $row->userid,
            'rawgrade'     => $raw,
            'dategraded'   => (int)$row->{$tempo},
            'datesubmitted' => (int)$row->{$tempo},
        ];
    }

    return $grades;
}

/**
 * Pushes grades into the gradebook.
 *
 * @param stdClass $rahoot The activity instance.
 * @param int $userid Zero for everyone.
 * @param bool $nullifnone Write an empty grade for a user who has no result.
 * @return void
 */
function rahoot_update_grades($rahoot, $userid = 0, $nullifnone = true) {
    $grades = rahoot_get_user_grades($rahoot, $userid);

    if ($grades) {
        rahoot_grade_item_update($rahoot, $grades);
        return;
    }

    if ($userid && $nullifnone) {
        rahoot_grade_item_update($rahoot, (object)['userid' => $userid, 'rawgrade' => null]);
        return;
    }

    rahoot_grade_item_update($rahoot);
}

/**
 * Creates or updates the grade item.
 *
 * @param stdClass $rahoot The activity instance.
 * @param mixed $grades Grade objects, 'reset', or null.
 * @return int GRADE_UPDATE_* status.
 */
function rahoot_grade_item_update($rahoot, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = ['itemname' => clean_param($rahoot->name, PARAM_NOTAGS)];

    $max = isset($rahoot->grade) ? (int)$rahoot->grade : 0;
    if ($max > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $max;
        $item['grademin']  = 0;
    } else if ($max < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$max;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/rahoot', $rahoot->course, 'mod', 'rahoot', $rahoot->id, 0, $grades, $item);
}

/**
 * Removes the grade item.
 *
 * @param stdClass $rahoot The activity instance.
 * @return int GRADE_UPDATE_* status.
 */
function rahoot_grade_item_delete($rahoot) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/rahoot', $rahoot->course, 'mod', 'rahoot', $rahoot->id, 0, null, ['deleted' => 1]);
}

/**
 * Says whether a scale is in use, so the site will not let it be deleted.
 *
 * @param int $scaleid
 * @return bool
 */
function rahoot_scale_used_anywhere($scaleid) {
    global $DB;

    return $scaleid && $DB->record_exists('rahoot', ['grade' => -$scaleid]);
}

/**
 * Wipes the results and grades when a course is reset.
 *
 * @param stdClass $data Reset form data.
 * @return array Status lines for the reset report.
 */
function rahoot_reset_userdata($data) {
    global $DB;

    $status = [];
    if (empty($data->reset_rahoot_attempts)) {
        return $status;
    }

    $rahoots = $DB->get_records('rahoot', ['course' => $data->courseid]);
    foreach ($rahoots as $rahoot) {
        $DB->delete_records('rahoot_attempts', ['rahootid' => $rahoot->id]);
        // The local copy is gone, so the gradebook has to be told; without this
        // the old grade survives a reset and the next cohort inherits it.
        rahoot_grade_item_update($rahoot, 'reset');
    }

    $status[] = [
        'component' => get_string('modulenameplural', 'mod_rahoot'),
        'item'      => get_string('resultsdeleted', 'mod_rahoot'),
        'error'     => false,
    ];

    return $status;
}

/**
 * Adds the reset option to the course reset form.
 *
 * @param MoodleQuickForm $mform
 * @return void
 */
function rahoot_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'rahootheader', get_string('modulenameplural', 'mod_rahoot'));
    $mform->addElement('advcheckbox', 'reset_rahoot_attempts', get_string('resetresults', 'mod_rahoot'));
}
