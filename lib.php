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
 * Library plugin functions
 *
 * @package mod_ogte
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2021 Tengku Alauddin - din@pukunui.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/


defined('MOODLE_INTERNAL') || die();

use \mod_ogte\constants;
use \mod_ogte\utils;


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 * @param object $ogte Object containing required ogte properties
 * @return int OGTE ID
 */
function ogte_add_instance($ogte) {
    global $DB;

    $ogte->timemodified = time();
    $ogte->id = $DB->insert_record("ogte", $ogte);

    ogte_grade_item_update($ogte);

    return $ogte->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 * @param object $ogte Object containing required ogte properties
 * @return boolean True if successful
 */
function ogte_update_instance($ogte) {
    global $DB;

    $ogte->timemodified = time();
    $ogte->id = $ogte->instance;

    $result = $DB->update_record("ogte", $ogte);

    ogte_grade_item_update($ogte);

    ogte_update_grades($ogte, 0, false);

    return $result;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * nd any data that depends on it.
 * @param int $id OGTE ID
 * @return boolean True if successful
 */
function ogte_delete_instance($id) {
    global $DB;

    $result = true;

    if (! $ogte = $DB->get_record("ogte", array("id" => $id))) {
        return false;
    }

    if (! $DB->delete_records("ogte_entries", array("ogte" => $ogte->id))) {
        $result = false;
    }

    if (! $DB->delete_records("ogte", array("id" => $ogte->id))) {
        $result = false;
    }

    return $result;
}


function ogte_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_RATE:
            return false;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return null;
    }
}


function ogte_get_view_actions() {
    return array('view', 'view all', 'view responses');
}


function ogte_get_post_actions() {
    return array('add entry', 'update entry', 'update feedback');
}


function ogte_user_outline($course, $user, $mod, $ogte) {

    global $DB;

    if ($entry = $DB->get_record("ogte_entries", array("userid" => $user->id, "ogte" => $ogte->id))) {

        $numwords = count(preg_split("/\w\b/", $entry->text)) - 1;

        $result = new stdClass();
        $result->info = get_string("numwords", "", $numwords);
        $result->time = $entry->modified;
        return $result;
    }
    return null;
}


function ogte_user_complete($course, $user, $mod, $ogte) {

    global $DB, $OUTPUT;

    if ($entry = $DB->get_record("ogte_entries", array("userid" => $user->id, "ogte" => $ogte->id))) {

        echo $OUTPUT->box_start();

        if ($entry->modified) {
            echo "<p><font size=\"1\">".get_string("lastedited").": ".userdate($entry->modified)."</font></p>";
        }
        if ($entry->text) {
            echo ogte_format_entry_text($entry, $course, $mod);
        }
        if ($entry->teacher) {
            $grades = make_grades_menu($ogte->grade);
            ogte_print_feedback($course, $entry, $grades);
        }

        echo $OUTPUT->box_end();

    } else {
        print_string("noentry", "ogte");
    }
}


/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in ogte activities and print it out.
 * Return true if there was output, or false if there was none.
 *
 * @global stdClass $DB
 * @global stdClass $OUTPUT
 * @param stdClass $course
 * @param bool $viewfullnames
 * @param int $timestart
 * @return bool
 */
function ogte_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    if (!get_config('ogte', 'showrecentactivity')) {
        return false;
    }

    $dbparams = array($timestart, $course->id, 'ogte');
    $namefields = user_picture::fields('u', null, 'userid');
    $sql = "SELECT je.id, je.modified, cm.id AS cmid, $namefields
         FROM {ogte_entries} je
              JOIN {ogte} j         ON j.id = je.ogte
              JOIN {course_modules} cm ON cm.instance = j.id
              JOIN {modules} md        ON md.id = cm.module
              JOIN {user} u            ON u.id = je.userid
         WHERE je.modified > ? AND
               j.course = ? AND
               md.name = ?
         ORDER BY je.modified ASC
    ";

    $newentries = $DB->get_records_sql($sql, $dbparams);

    $modinfo = get_fast_modinfo($course);
    $show    = array();

    foreach ($newentries as $anentry) {

        if (!array_key_exists($anentry->cmid, $modinfo->get_cms())) {
            continue;
        }
        $cm = $modinfo->get_cm($anentry->cmid);

        if (!$cm->uservisible) {
            continue;
        }
        if ($anentry->userid == $USER->id) {
            $show[] = $anentry;
            continue;
        }
        $context = context_module::instance($anentry->cmid);

        // Only teachers can see other students entries.
        if (!has_capability('mod/ogte:manageentries', $context)) {
            continue;
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode == SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups',  $context)) {
            if (isguestuser()) {
                // Shortcut - guest user does not belong into any group.
                continue;
            }

            // This will be slow - show only users that share group with me in this cm.
            if (!$modinfo->get_groups($cm->groupingid)) {
                continue;
            }
            $usersgroups = groups_get_all_groups($course->id, $anentry->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $anentry;
    }

    if (empty($show)) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newogteentries', 'ogte').':', 3);

    foreach ($show as $submission) {
        $cm = $modinfo->get_cm($submission->cmid);
        $context = context_module::instance($submission->cmid);
        if (has_capability('mod/ogte:manageentries', $context)) {
            $link = $CFG->wwwroot.'/mod/ogte/report.php?id='.$cm->id;
        } else {
            $link = $CFG->wwwroot.'/mod/ogte/view.php?id='.$cm->id;
        }
        print_recent_activity_note($submission->modified,
                                   $submission,
                                   $cm->name,
                                   $link,
                                   false,
                                   $viewfullnames);
    }
    return true;
}

/**
 * Returns the users with data in one ogte
 * (users with records in ogte_entries, students and teachers)
 * @param int $ogteid OGTE ID
 * @return array Array of user ids
 */
function ogte_get_participants($ogteid) {
    global $DB;

    // Get students.
    $students = $DB->get_records_sql("SELECT DISTINCT u.id
                                      FROM {user} u,
                                      {ogte_entries} j
                                      WHERE j.ogte=? and
                                      u.id = j.userid", array($ogteid));
    // Get teachers.
    $teachers = $DB->get_records_sql("SELECT DISTINCT u.id
                                      FROM {user} u,
                                      {ogte_entries} j
                                      WHERE j.ogte=? and
                                      u.id = j.teacher", array($ogteid));

    // Add teachers to students.
    if ($teachers) {
        foreach ($teachers as $teacher) {
            $students[$teacher->id] = $teacher;
        }
    }
    // Return students array (it contains an array of unique users).
    return $students;
}

/**
 * This function returns true if a scale is being used by one ogte
 * @param int $ogteid OGTE ID
 * @param int $scaleid Scale ID
 * @return boolean True if a scale is being used by one ogte
 */
function ogte_scale_used ($ogteid, $scaleid) {

    global $DB;
    $return = false;

    $rec = $DB->get_record("ogte", array("id" => $ogteid, "grade" => -$scaleid));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of ogte
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any ogte
 */
function ogte_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->get_records('ogte', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the ogte.
 *
 * @param object $mform form passed by reference
 */
function ogte_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'ogteheader', get_string('modulenameplural', 'ogte'));
    $mform->addElement('advcheckbox', 'reset_ogte', get_string('removemessages', 'ogte'));
}

/**
 * Course reset form defaults.
 *
 * @param object $course
 * @return array
 */
function ogte_reset_course_form_defaults($course) {
    return array('reset_ogte' => 1);
}

/**
 * Removes all entries
 *
 * @param object $data
 */
function ogte_reset_userdata($data) {

    global $CFG, $DB;

    $status = array();
    if (!empty($data->reset_ogte)) {

        $sql = "SELECT j.id
                FROM {ogte} j
                WHERE j.course = ?";
        $params = array($data->courseid);

        $DB->delete_records_select('ogte_entries', "ogte IN ($sql)", $params);

        $status[] = array('component' => get_string('modulenameplural', 'ogte'),
                          'item' => get_string('removeentries', 'ogte'),
                          'error' => false);
    }

    return $status;
}

function ogte_print_overview($courses, &$htmlarray) {

    global $USER, $CFG, $DB;

    if (!get_config('ogte', 'overview')) {
        return array();
    }

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$ogtes = get_all_instances_in_courses('ogte', $courses)) {
        return array();
    }

    $strogte = get_string('modulename', 'ogte');

    $timenow = time();
    foreach ($ogtes as $ogte) {

        if (empty($courses[$ogte->course]->format)) {
            $courses[$ogte->course]->format = $DB->get_field('course', 'format', array('id' => $ogte->course));
        }

        if ($courses[$ogte->course]->format == 'weeks' AND $ogte->days) {

            $coursestartdate = $courses[$ogte->course]->startdate;

            $ogte->timestart  = $coursestartdate + (($ogte->section - 1) * 608400);
            if (!empty($ogte->days)) {
                $ogte->timefinish = $ogte->timestart + (3600 * 24 * $ogte->days);
            } else {
                $ogte->timefinish = 9999999999;
            }
            $ogteopen = ($ogte->timestart < $timenow && $timenow < $ogte->timefinish);

        } else {
            $ogteopen = true;
        }

        if ($ogteopen) {
            $str = '<div class="ogte overview"><div class="name">'.
                   $strogte.': <a '.($ogte->visible ? '' : ' class="dimmed"').
                   ' href="'.$CFG->wwwroot.'/mod/ogte/view.php?id='.$ogte->coursemodule.'">'.
                   $ogte->name.'</a></div></div>';

            if (empty($htmlarray[$ogte->course]['ogte'])) {
                $htmlarray[$ogte->course]['ogte'] = $str;
            } else {
                $htmlarray[$ogte->course]['ogte'] .= $str;
            }
        }
    }
}

function ogte_get_user_grades($ogte, $userid=0) {
    global $DB;

    $params = array();

    if ($userid) {
        $userstr = 'AND userid = :uid';
        $params['uid'] = $userid;
    } else {
        $userstr = '';
    }

    if (!$ogte) {
        return false;

    } else {

        $sql = "SELECT userid, modified as datesubmitted, format as feedbackformat,
                rating as rawgrade, entrycomment as feedback, teacher as usermodifier, timemarked as dategraded
                FROM {ogte_entries}
                WHERE ogte = :jid ".$userstr;
        $params['jid'] = $ogte->id;

        $grades = $DB->get_records_sql($sql, $params);

        if ($grades) {
            foreach ($grades as $key => $grade) {
                $grades[$key]->id = $grade->userid;
                if ($grade->rawgrade == -1) {
                    $grades[$key]->rawgrade = null;
                }
            }
        } else {
            return false;
        }

        return $grades;
    }

}


/**
 * Update ogte grades in 1.9 gradebook
 *
 * @param object   $ogte      if is null, all ogtes
 * @param int      $userid       if is false al users
 * @param boolean  $nullifnone   return null if grade does not exist
 */
function ogte_update_grades($ogte=null, $userid=0, $nullifnone=true) {

    global $CFG, $DB;

    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if ($ogte != null) {
        if ($grades = ogte_get_user_grades($ogte, $userid)) {
            ogte_grade_item_update($ogte, $grades);
        } else if ($userid && $nullifnone) {
            $grade = new stdClass();
            $grade->userid   = $userid;
            $grade->rawgrade = null;
            ogte_grade_item_update($ogte, $grade);
        } else {
            ogte_grade_item_update($ogte);
        }
    } else {
        $sql = "SELECT j.*, cm.idnumber as cmidnumber
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                JOIN {ogte} j ON cm.instance = j.id
                WHERE m.name = 'ogte'";
        if ($recordset = $DB->get_records_sql($sql)) {
            foreach ($recordset as $ogte) {
                if ($ogte->grade != false) {
                    ogte_update_grades($ogte);
                } else {
                    ogte_grade_item_update($ogte);
                }
            }
        }
    }
}


/**
 * Create grade item for given ogte
 *
 * @param object $ogte object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function ogte_grade_item_update($ogte, $grades=null) {
    global $CFG;
    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (property_exists($ogte, 'cmidnumber')) {
        $params = array('itemname' => $ogte->name, 'idnumber' => $ogte->cmidnumber);
    } else {
        $params = array('itemname' => $ogte->name);
    }

    // if ($ogte->grade > 0) {
        // $params['gradetype']  = GRADE_TYPE_VALUE;
        // $params['grademax']   = $ogte->grade;
        // $params['grademin']   = 0;
        // $params['multfactor'] = 1.0;

    // } else if ($ogte->grade < 0) {
        // $params['gradetype'] = GRADE_TYPE_SCALE;
        // $params['scaleid']   = -$ogte->grade;

    // } else {
        // $params['gradetype']  = GRADE_TYPE_NONE;
        // $params['multfactor'] = 1.0;
    // }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/ogte', $ogte->course, 'mod', 'ogte', $ogte->id, 0, $grades, $params);
}


/**
 * Delete grade item for given ogte
 *
 * @param   object   $ogte
 * @return  object   grade_item
 */
function ogte_grade_item_delete($ogte) {
    global $CFG;

    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/ogte', $ogte->course, 'mod', 'ogte', $ogte->id, 0, null, array('deleted' => 1));
}



function ogte_get_users_done($ogte, $currentgroup) {
    global $DB;

    $params = array();

    $sql = "SELECT u.* FROM {ogte_entries} j
            JOIN {user} u ON j.userid = u.id ";

    // Group users.
    if ($currentgroup != 0) {
        $sql .= "JOIN {groups_members} gm ON gm.userid = u.id AND gm.groupid = ?";
        $params[] = $currentgroup;
    }

    $sql .= " WHERE j.ogte=? ORDER BY j.modified DESC";
    $params[] = $ogte->id;
    $ogtes = $DB->get_records_sql($sql, $params);

    $cm = ogte_get_coursemodule($ogte->id);
    if (!$ogtes || !$cm) {
        return null;
    }

    // Remove unenrolled participants.
    foreach ($ogtes as $key => $user) {

        $context = context_module::instance($cm->id);

        $canadd = has_capability('mod/ogte:addentries', $context, $user);
        $entriesmanager = has_capability('mod/ogte:manageentries', $context, $user);

        if (!$entriesmanager and !$canadd) {
            unset($ogtes[$key]);
        }
    }

    return $ogtes;
}

/**
 * Counts all the ogte entries (optionally in a given group)
 */
function ogte_count_entries($ogte, $groupid = 0) {
    global $DB;

    $cm = ogte_get_coursemodule($ogte->id);
    $context = context_module::instance($cm->id);

    if ($groupid) {     // How many in a particular group?

        $sql = "SELECT DISTINCT u.id FROM {ogte_entries} j
                JOIN {groups_members} g ON g.userid = j.userid
                JOIN {user} u ON u.id = g.userid
                WHERE j.ogte = ? AND g.groupid = ?";
        $ogtes = $DB->get_records_sql($sql, array($ogte->id, $groupid));

    } else { // Count all the entries from the whole course.

        $sql = "SELECT DISTINCT u.id FROM {ogte_entries} j
                JOIN {user} u ON u.id = j.userid
                WHERE j.ogte = ?";
        $ogtes = $DB->get_records_sql($sql, array($ogte->id));
    }

    if (!$ogtes) {
        return 0;
    }

    $canadd = get_users_by_capability($context, 'mod/ogte:addentries', 'u.id');
    $entriesmanager = get_users_by_capability($context, 'mod/ogte:manageentries', 'u.id');

    // Remove unenrolled participants.
    foreach ($ogtes as $userid => $notused) {

        if (!isset($entriesmanager[$userid]) && !isset($canadd[$userid])) {
            unset($ogtes[$userid]);
        }
    }

    return count($ogtes);
}

function ogte_get_unmailed_graded($cutofftime) {
    global $DB;

    $sql = "SELECT je.*, j.course, j.name FROM {ogte_entries} je
            JOIN {ogte} j ON je.ogte = j.id
            WHERE je.mailed = '0' AND je.timemarked < ? AND je.timemarked > 0";
    return $DB->get_records_sql($sql, array($cutofftime));
}

function ogte_log_info($log) {
    global $DB;

    $sql = "SELECT j.*, u.firstname, u.lastname
            FROM {ogte} j
            JOIN {ogte_entries} je ON je.ogte = j.id
            JOIN {user} u ON u.id = je.userid
            WHERE je.id = ?";
    return $DB->get_record_sql($sql, array($log->info));
}

/**
 * Returns the ogte instance course_module id
 *
 * @param integer $ogte
 * @return object
 */
function ogte_get_coursemodule($ogteid) {

    global $DB;

    return $DB->get_record_sql("SELECT cm.id FROM {course_modules} cm
                                JOIN {modules} m ON m.id = cm.module
                                WHERE cm.instance = ? AND m.name = 'ogte'", array($ogteid));
}



function ogte_print_user_entry($course, $user, $entry, $teachers, $grades) {

    global $USER, $OUTPUT, $DB, $CFG;

    require_once($CFG->dirroot.'/lib/gradelib.php');

    echo "\n<table class=\"ogteuserentry m-b-1\" id=\"entry-" . $user->id . "\">";

    echo "\n<tr>";
    echo "\n<td class=\"userpix\" rowspan=\"2\">";
    echo $OUTPUT->user_picture($user, array('courseid' => $course->id, 'alttext' => true));
    echo "</td>";
    echo "<td class=\"userfullname\">".fullname($user);
    if ($entry) {
        echo " <span class=\"lastedit\">".get_string("lastedited").": ".userdate($entry->modified)."</span>";
    }
    echo "</td>";
    echo "</tr>";

    echo "\n<tr><td>";
    if ($entry) {
        echo ogte_format_entry_text($entry, $course);
    } else {
        print_string("noentry", "ogte");
    }
    echo "</td></tr>";

    if ($entry) {
        echo "\n<tr>";
        echo "<td class=\"userpix\">";
        if (!$entry->teacher) {
            $entry->teacher = $USER->id;
        }
        if (empty($teachers[$entry->teacher])) {
            $teachers[$entry->teacher] = $DB->get_record('user', array('id' => $entry->teacher));
        }
        echo $OUTPUT->user_picture($teachers[$entry->teacher], array('courseid' => $course->id, 'alttext' => true));
        echo "</td>";
        echo "<td>".get_string("feedback").":";

        $attrs = array();
        $hiddengradestr = '';
        $gradebookgradestr = '';
        $feedbackdisabledstr = '';
        $feedbacktext = $entry->entrycomment;

        // If the grade was modified from the gradebook disable edition also skip if ogte is not graded.
        $gradinginfo = grade_get_grades($course->id, 'mod', 'ogte', $entry->ogte, array($user->id));
        if (!empty($gradinginfo->items[0]->grades[$entry->userid]->str_long_grade)) {
            if ($gradingdisabled = $gradinginfo->items[0]->grades[$user->id]->locked
                    || $gradinginfo->items[0]->grades[$user->id]->overridden) {
                $attrs['disabled'] = 'disabled';
                $hiddengradestr = '<input type="hidden" name="r'.$entry->id.'" value="'.$entry->rating.'"/>';
                $gradebooklink = '<a href="'.$CFG->wwwroot.'/grade/report/grader/index.php?id='.$course->id.'">';
                $gradebooklink .= $gradinginfo->items[0]->grades[$user->id]->str_long_grade.'</a>';
                $gradebookgradestr = '<br/>'.get_string("gradeingradebook", "ogte").':&nbsp;'.$gradebooklink;

                $feedbackdisabledstr = 'disabled="disabled"';
                $feedbacktext = $gradinginfo->items[0]->grades[$user->id]->str_feedback;
            }
        }

        // Grade selector.
        $attrs['id'] = 'r' . $entry->id;
        echo html_writer::label(fullname($user)." ".get_string('grade'), 'r'.$entry->id, true, array('class' => 'accesshide'));
        echo html_writer::select($grades, 'r'.$entry->id, $entry->rating, get_string("nograde").'...', $attrs);
        echo $hiddengradestr;
        // Rewrote next three lines to show entry needs to be regraded due to resubmission.
        if (!empty($entry->timemarked) && $entry->modified > $entry->timemarked) {
            echo " <span class=\"lastedit\">".get_string("needsregrade", "ogte"). "</span>";
        } else if ($entry->timemarked) {
            echo " <span class=\"lastedit\">".userdate($entry->timemarked)."</span>";
        }
        echo $gradebookgradestr;

        // Feedback text.
        echo html_writer::label(fullname($user)." ".get_string('feedback'), 'c'.$entry->id, true, array('class' => 'accesshide'));
        echo "<p><textarea id=\"c$entry->id\" name=\"c$entry->id\" rows=\"12\" cols=\"60\" $feedbackdisabledstr>";
        p($feedbacktext);
        echo "</textarea></p>";

        if ($feedbackdisabledstr != '') {
            echo '<input type="hidden" name="c'.$entry->id.'" value="'.$feedbacktext.'"/>';
        }
        echo "</td></tr>";
    }
    echo "</table>\n";

}

function ogte_print_feedback($course, $entry, $grades) {

    global $CFG, $DB, $OUTPUT;

    require_once($CFG->dirroot.'/lib/gradelib.php');

    if (! $teacher = $DB->get_record('user', array('id' => $entry->teacher))) {
        print_error('Weird ogte error');
    }

    echo '<table class="feedbackbox">';

    echo '<tr>';
    echo '<td class="left picture">';
    echo $OUTPUT->user_picture($teacher, array('courseid' => $course->id, 'alttext' => true));
    echo '</td>';
    echo '<td class="entryheader">';
    echo '<span class="author">'.fullname($teacher).'</span>';
    echo '&nbsp;&nbsp;<span class="time">'.userdate($entry->timemarked).'</span>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="left side">&nbsp;</td>';
    echo '<td class="entrycontent">';

    echo '<div class="grade">';

    // Gradebook preference.
    $gradinginfo = grade_get_grades($course->id, 'mod', 'ogte', $entry->ogte, array($entry->userid));
    if (!empty($gradinginfo->items[0]->grades[$entry->userid]->str_long_grade)) {
        echo get_string('grade').': ';
        echo $gradinginfo->items[0]->grades[$entry->userid]->str_long_grade;
    } else {
        print_string('nograde');
    }
    echo '</div>';

    // Feedback text.
    echo format_text($entry->entrycomment, FORMAT_PLAIN);
    echo '</td></tr></table>';
}

/**
 * Serves the ogte files.
 *
 * @package  mod_ogte
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function ogte_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    switch($filearea){
        case 'entry':
            require_course_login($course, true, $cm);
            if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
                return false;
            }
            // Args[0] should be the entry id.
            $entryid = intval(array_shift($args));
            $entry = $DB->get_record('ogte_entries', array('id' => $entryid), 'id, userid', MUST_EXIST);

            $canmanage = has_capability('mod/ogte:manageentries', $context);
            if (!$canmanage && !has_capability('mod/ogte:addentries', $context)) {
                // Even if it is your own entry.
                return false;
            }

            // Students can only see their own entry.
            if (!$canmanage && $USER->id !== $entry->userid) {
                return false;
            }

            if ($filearea !== 'entry') {
                return false;
            }

            $fs = get_file_storage();
            $relativepath = implode('/', $args);
            $fullpath = "/$context->id/mod_ogte/$filearea/$entryid/$relativepath";
            $file = $fs->get_file_by_hash(sha1($fullpath));

            // Finally send the file.
            send_stored_file($file, null, 0, $forcedownload, $options);



            break;
        case 'exportlist':


        require_login($course, false, $cm);
        require_capability('mod/ogte:manage', $context);
        $listid = intval(array_shift($args));
        $list = $DB->get_record(constants::M_LISTSTABLE,['id'=>$listid]);
        if(!$list){return false;}
        $delim = ','; //csv delimiter
        $filerows=[];

        //make a nice filename
        $filename = clean_filename(strip_tags(format_string($list->name)).'.csv');
        $filename = preg_replace('/\s+/', '_', $filename);

        //make content
        $headwords =$DB->get_records_sql("SELECT DISTINCT headword FROM {" . constants::M_WORDSTABLE . "} WHERE list = ?", array($listid));
        foreach($headwords as $headword){
            $words=$DB->get_records_sql("SELECT * FROM {" . constants::M_WORDSTABLE . "} WHERE list = ? AND headword = ?", array($listid, $headword->headword));
            $wordstring = [];
            foreach($words as $word){
                if(count($wordstring)==0){
                    $wordstring[] = $word->listrank;
                    $wordstring[] = $word->headword;
                }
                if($word->word!==$word->headword){
                    $wordstring[] = $word->word;
                }
            }
            if(count($wordstring)>0){
                $filerows[] = implode($delim, $wordstring);
            }
        }

        //make the file to return
        if(count($filerows)==0){return false;}
        $filecontent = implode("\r\n", $filerows);

        //return to the browser that called us
        send_file($filecontent, $filename, 0, 0, true, true);
        break;


        default:
            return false;
    }


}

function ogte_format_entry_text($entry, $course = false, $cm = false) {

    if (!$cm) {
        if ($course) {
            $courseid = $course->id;
        } else {
            $courseid = 0;
        }
        $cm = get_coursemodule_from_instance('ogte', $entry->ogte, $courseid);
    }

    $context = context_module::instance($cm->id);
    $entrytext = file_rewrite_pluginfile_urls($entry->text, 'pluginfile.php', $context->id, 'mod_ogte', 'entry', $entry->id);

    $formatoptions = array(
        'context' => $context,
        'noclean' => false,
        'trusted' => false
    );
    return format_text($entrytext, $entry->format, $formatoptions);
}

/**
  * Obtains the automatic completion state for this ogte based on any conditions
  * in ogte settings.
  *
  * @param object $course Course
  * @param object $cm Course-module
  * @param int $userid User ID
  * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
  * @return bool True if completed, false if not, $type if conditions not set.
  */
function ogte_get_completion_state($course,$cm,$userid,$type) {
    global $CFG,$DB;

    // Get ogte details
    $ogte = $DB->get_record('ogte', array('id' => $cm->instance), '*', MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false 
    if($ogte->completionanswer) {
        return $ogte->completionanswer <= $DB->get_field_sql("
SELECT 
    COUNT(1) 
FROM 
    {ogte} s
    INNER JOIN {ogte_entries} se ON s.id=se.ogte
WHERE
    se.userid=:userid AND se.ogte=:ogteid",
            array('userid'=>$userid,'ogteid'=>$ogte->id));
    } else {
        // Completion option is not enabled so just return $type
        return $type;
    }
}

