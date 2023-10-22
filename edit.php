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
 * Edit page
 *
 * @package mod_ogte
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2021 Tengku Alauddin - din@pukunui.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once("../../config.php");
require_once('./edit_form.php');
require_once($CFG->dirroot.'/lib/completionlib.php');

use \mod_ogte\constants;
use \mod_ogte\utils;

$id = required_param('id', PARAM_INT);    // Course Module ID.

if (!$cm = get_coursemodule_from_id('ogte', $id)) {
    print_error("Course Module ID was incorrect");
}

if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error("Course is misconfigured");
}

$context = context_module::instance($cm->id);

require_login($course, false, $cm);

require_capability('mod/ogte:addentries', $context);

if (! $ogte = $DB->get_record("ogte", array("id" => $cm->instance))) {
    print_error("Course module is incorrect");
}
if (!empty($ogte->preventry)){
    $prev_ogte = $DB->get_record("ogte", array("id" => $ogte->preventry));
    $prev_entry = $DB->get_record("ogte_entries", array("userid" => $USER->id, "ogte" => $ogte->preventry));
}
// Header.
$PAGE->set_url('/mod/ogte/edit.php', array('id' => $id));
$PAGE->navbar->add(get_string('edit'));
$PAGE->set_title(format_string($ogte->name));
$PAGE->set_heading($course->fullname);

$data = new stdClass();

$entry = $DB->get_record("ogte_entries", array("userid" => $USER->id, "ogte" => $ogte->id));
if ($entry) {
    $data->entryid = $entry->id;
    $data->text = $entry->text;
} else {
    $data->entryid = null;
    $data->text = '';
}

$data->id = $cm->id;
//get token
//first confirm we are authorised before we try to get the token
$config = get_config(constants::M_COMPONENT);
if(empty($config->apiuser) || empty($config->apisecret)){
    $errormessage = get_string('nocredentials',constants::M_COMPONENT,
        $CFG->wwwroot . constants::M_PLUGINSETTINGS);
    return $this->show_problembox($errormessage);
}else {
    //fetch token
    $token = utils::fetch_token($config->apiuser,$config->apisecret);

    //check token authenticated and no errors in it
    $errormessage = utils::fetch_token_error($token);
    if(!empty($errormessage)){
        return $this->show_problembox($errormessage);
    }
}

$params =['cloudpoodlltoken'=>$token];
$abovetextarea = '';
$belowtextarea = $OUTPUT->render_from_template('mod_ogte/belowtextarea', $params) ;

$form = new mod_ogte_entry_form(null, array('entryid' => $data->entryid,'abovetextarea'=>$abovetextarea,'belowtextarea'=>$belowtextarea));
$form->set_data($data);

if ($form->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/ogte/view.php?id=' . $cm->id);
} else if ($fromform = $form->get_data()) {
    // If data submitted, then process and store.

    // Prevent CSFR.
    confirm_sesskey();
    $timenow = time();

    // This will be overwriten after being we have the entryid.
    $newentry = new stdClass();
    $newentry->text = $fromform->text;
    $newentry->format = FORMAT_HTML;
    $newentry->modified = $timenow;

    if ($entry) {
        $newentry->id = $entry->id;
        if (!$DB->update_record("ogte_entries", $newentry)) {
            print_error("Could not update your ogte");
        }
    } else {
        $newentry->userid = $USER->id;
        $newentry->ogte = $ogte->id;
        if (!$newentry->id = $DB->insert_record("ogte_entries", $newentry)) {
            print_error("Could not insert a new ogte entry");
        }
    }

    // Update completion state.
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm) && $ogte->completionanswer) {
        $completion->update_state($cm, COMPLETION_COMPLETE);
    }


    $DB->update_record('ogte_entries', $newentry);

    if ($entry) {
        // Trigger module entry updated event.
        $event = \mod_ogte\event\entry_updated::create(array(
            'objectid' => $ogte->id,
            'context' => $context
        ));
    } else {
        // Trigger module entry created event.
        $event = \mod_ogte\event\entry_created::create(array(
            'objectid' => $ogte->id,
            'context' => $context
        ));

    }
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('ogte', $ogte);
    $event->trigger();

    redirect(new moodle_url('/mod/ogte/view.php?id='.$cm->id));
    die;
}


echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($ogte->name));

if(!empty($prev_ogte)){
    $prev_intro = format_module_intro('ogte', $prev_ogte, $cm->id);
}
if (!empty($prev_intro)){
    echo '<table border="2" width="99%"><tr><td>';
    echo $OUTPUT->box($prev_intro);
    
    if (!empty($prev_entry->text)){
        echo $OUTPUT->box($prev_entry->text);
    }
    echo '</td></tr></table>';
}

$intro = format_module_intro('ogte', $ogte, $cm->id);
echo $OUTPUT->box($intro);

// Otherwise fill and print the form.
$form->display();

echo $OUTPUT->footer();
