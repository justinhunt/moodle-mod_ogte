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
 * Plugin view page
 *
 * @package mod_ogte
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2021 Tengku Alauddin - din@pukunui.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->dirroot.'/lib/completionlib.php');

use mod_ogte\constants;

$id = required_param('id', PARAM_INT);    // Course Module ID.

if (! $cm = get_coursemodule_from_id('ogte', $id)) {
    print_error("Course Module ID was incorrect");
}

if (! $course = $DB->get_record("course", array('id' => $cm->course))) {
    print_error("Course is misconfigured");
}

$context = context_module::instance($cm->id);

require_login($course, true, $cm);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$entriesmanager = has_capability('mod/ogte:manageentries', $context);
$canadd = has_capability('mod/ogte:addentries', $context);

if (!$entriesmanager && !$canadd) {
    print_error('accessdenied', 'ogte');
}

if (! $ogte = $DB->get_record("ogte", array("id" => $cm->instance))) {
    print_error("Course module is incorrect");
}
if (!empty($ogte->preventry)){
    $prev_ogte = $DB->get_record("ogte", array("id" => $ogte->preventry));
}

if (! $cw = $DB->get_record("course_sections", array("id" => $cm->section))) {
    print_error("Course module is incorrect");
}

$ogtename = format_string($ogte->name, true, array('context' => $context));

// Header.
$PAGE->set_url('/mod/ogte/view.php', array('id' => $id));
$PAGE->navbar->add($ogtename);
$PAGE->set_title($ogtename);
$PAGE->set_heading($course->fullname);

$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

echo $OUTPUT->header();
echo $OUTPUT->heading($ogtename);

// Check to see if groups are being used here.
$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);
// groups_print_activity_menu($cm, $CFG->wwwroot . "/mod/ogte/view.php?id=$cm->id");

// if ($entriesmanager) {
    // $entrycount = ogte_count_entries($ogte, $currentgroup);
    // echo '<div class="reportlink"><a href="report.php?id='.$cm->id.'">'.
          // get_string('viewallentries', 'ogte', $entrycount).'</a></div>';
// }

//Check download mode, and display the download page button
if ($ogte->mode == 1){
    // echo get_string("downloadmesage", "ogte");
    if (!empty($ogte->intro)) {
        $intro = format_module_intro('ogte', $ogte, $cm->id);
        echo '<table><tr><td>' . $intro .'</td></tr></table>';
    }
    echo '<br /><br />';
    echo $OUTPUT->single_button('download.php?id='.$cm->id, get_string('download', 'ogte'), 'get',
                array("class" => "singlebutton ogtestart"));
    echo $OUTPUT->footer();
    die;
}

if (!empty($prev_ogte)){
    echo '<table border="2" width="99%"><tr><td>';

    $prev_ogte->intro = trim($prev_ogte->intro);
    if (!empty($prev_ogte->intro)) {
        $intro = format_module_intro('ogte', $prev_ogte, $cm->id);
        echo '<table><tr><td>' . $OUTPUT->image_icon('q-button', '', 'ogte', array('style'=>'height:48px; width:48px')) . '</td><td>' . $intro .'</td></tr></table>';
    }

    // echo '<br />';
    $timenow = time();
    if ($course->format == 'weeks' and $prev_ogte->days) {
    $timestart = $course->startdate + (($cw->section - 1) * 604800);
    if ($prev_ogte->days) {
        $timefinish = $timestart + (3600 * 24 * $ogte->days);
    } else {
        $timefinish = $course->enddate;
    }
    } else {  // Have no time limits on the ogtes.

    $timestart = $timenow - 1;
    $timefinish = $timenow + 1;
    $prev_ogte->days = 0;
    }
    if ($timenow > $timestart) {

    // echo $OUTPUT->box_start();

    // Display entry.
    if ($prev_entry = $DB->get_record('ogte_entries', array('userid' => $USER->id, 'ogte' => $prev_ogte->id))) {
        if (empty($prev_entry->text)) {
            echo '<p align="center"><b>'.get_string('blankentry', 'ogte').'</b></p>';
        } else {
            // echo '<br>'. $OUTPUT->image_icon('a-button', '', 'ogte') . ogte_format_entry_text($entry, $course, $cm);
            echo '<table><tr><td>' . $OUTPUT->image_icon('a-button', '', 'ogte', array('style'=>'height:48px; width:48px')) . '</td><td>' . ogte_format_entry_text($prev_entry, $course, $cm) .'</td></tr></table>';
        }
    } else {
        echo '<br><span class="warning">'.get_string('notstarted', 'ogte').'</span>';
    }

    // echo '<br />';

    // Edit button.
    // if ($timenow < $timefinish) {

        // if ($canadd) {
            // echo $OUTPUT->single_button('edit.php?id='.$cm->id, get_string('startoredit', 'ogte'), 'get',
                // array("class" => "singlebutton ogtestart"));
        // }
    // }

    // echo $OUTPUT->box_end();

    // Info.
    if ($timenow < $timefinish) {
        if (!empty($prev_entry->modified)) {
            echo '<div class="s"><strong>'.get_string('submitted', 'ogte') . ' ';
            echo userdate($prev_entry->modified);
            // echo ' ('.get_string('numwords', '', count_words($entry->text)).')</strong>';
            echo "</strong></div>";
        }
        // Added three lines to mark entry as being dirty and needing regrade.
        if (!empty($prev_entry->modified) AND !empty($prev_entry->timemarked) AND $prev_entry->modified > $prev_entry->timemarked) {
            echo "<div class=\"lastedit\">".get_string("needsregrade", "ogte"). "</div>";
        }

        if (!empty($prev_ogte->days)) {
            echo '<div class="editend"><strong>'.get_string('editingends', 'ogte').': </strong> ';
            echo userdate($timefinish).'</div>';
        }

    } else {
        echo '<div class="editend"><strong>'.get_string('editingended', 'ogte').': </strong> ';
        echo userdate($timefinish).'</div>';
    }

    // Feedback.
    if (!empty($prev_entry->entrycomment) or !empty($prev_entry->rating)) {
        $grades = make_grades_menu($prev_ogte->grade);
        echo $OUTPUT->heading(get_string('feedback'));
        ogte_print_feedback($course, $prev_entry, $grades);
    }

    } else {
    echo '<div class="warning">'.get_string('notopenuntil', 'ogte').': ';
    echo userdate($timestart).'</div>';
    }
    echo '</td></tr></table>';

echo '<hr>';
}

$ogte->intro = trim($ogte->intro);
if (!empty($ogte->intro)) {
    $intro = format_module_intro('ogte', $ogte, $cm->id);
    echo '<table><tr><td>' . $OUTPUT->image_icon('q-button', '', 'ogte', array('style'=>'height:48px; width:48px')) . '</td><td>' . $intro .'</td></tr></table>';
}

// echo '<br />';

$timenow = time();
if ($course->format == 'weeks' and $ogte->days) {
    $timestart = $course->startdate + (($cw->section - 1) * 604800);
    if ($ogte->days) {
        $timefinish = $timestart + (3600 * 24 * $ogte->days);
    } else {
        $timefinish = $course->enddate;
    }
} else {  // Have no time limits on the ogtes.

    $timestart = $timenow - 1;
    $timefinish = $timenow + 1;
    $ogte->days = 0;
}
if ($timenow > $timestart) {

    // echo $OUTPUT->box_start();

    // Display entry.
    if ($entry = $DB->get_record('ogte_entries', array('userid' => $USER->id, 'ogte' => $ogte->id))) {
        if (empty($entry->text)) {
            echo '<p align="center"><b>'.get_string('blankentry', 'ogte').'</b></p>';
        } else {
            // echo '<br>'. $OUTPUT->image_icon('a-button', '', 'ogte') . ogte_format_entry_text($entry, $course, $cm);
            echo '<table><tr><td>' . $OUTPUT->image_icon('a-button', '', 'ogte', array('style'=>'height:48px; width:48px')) . '</td><td>' . ogte_format_entry_text($entry, $course, $cm) .'</td></tr></table>';
        }
    } else {
        echo '<br><span class="warning">'.get_string('notstarted', 'ogte').'</span>';
    }
    
    // echo '<br />';
    
    // Edit button.
    if ($timenow < $timefinish) {
    
        if ($canadd) {
            echo '<br>' . $OUTPUT->single_button('edit.php?id='.$cm->id, get_string('startoredit', 'ogte'), 'get',
                array("class" => "singlebutton ogtestart"));
        }
    }

    // echo $OUTPUT->box_end();

    // Info.
    if ($timenow < $timefinish) {
        if (!empty($entry->modified)) {
            echo '<div class="s"><strong>'.get_string('submitted', 'ogte') . ' ';
            echo userdate($entry->modified);
            // echo ' ('.get_string('numwords', '', count_words($entry->text)).')</strong>';
            echo "</strong></div>";
        }
        // Added three lines to mark entry as being dirty and needing regrade.
        if (!empty($entry->modified) AND !empty($entry->timemarked) AND $entry->modified > $entry->timemarked) {
            echo "<div class=\"lastedit\">".get_string("needsregrade", "ogte"). "</div>";
        }

        if (!empty($ogte->days)) {
            echo '<div class="editend"><strong>'.get_string('editingends', 'ogte').': </strong> ';
            echo userdate($timefinish).'</div>';
        }

    } else {
        echo '<div class="editend"><strong>'.get_string('editingended', 'ogte').': </strong> ';
        echo userdate($timefinish).'</div>';
    }

    // Feedback.
    if (!empty($entry->entrycomment) or !empty($entry->rating)) {
        $grades = make_grades_menu($ogte->grade);
        echo $OUTPUT->heading(get_string('feedback'));
        ogte_print_feedback($course, $entry, $grades);
    }

} else {
    echo '<div class="warning">'.get_string('notopenuntil', 'ogte').': ';
    echo userdate($timestart).'</div>';
}

//lists page button
echo '<br><hr>';
echo $renderer->back_to_lists_button($cm,get_string('addeditlists',constants::M_COMPONENT));


// Trigger module viewed event.
$event = \mod_ogte\event\course_module_viewed::create(array(
   'objectid' => $ogte->id,
   'context' => $context
));
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('ogte', $ogte);
$event->trigger();

echo $OUTPUT->footer();
