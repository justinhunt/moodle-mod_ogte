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
 * @copyright  2023 Justin Hunt - justin@poodll.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once("../../config.php");
require_once($CFG->dirroot.'/lib/completionlib.php');

use \mod_ogte\constants;
use \mod_ogte\utils;

$id = required_param('id', PARAM_INT);    // Course Module ID.
$entryid = optional_param('entryid',0, PARAM_INT);    // Course Module ID.
$action = optional_param('action',null, PARAM_ALPHA);    // Course Module ID.
$passagetext = optional_param('text',null, PARAM_RAW);    // Course Module ID.

if (!$cm = get_coursemodule_from_id('ogte', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}

if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
    throw new \moodle_exception('coursemisconf');
}

$context = context_module::instance($cm->id);

require_login($course, false, $cm);

require_capability('mod/ogte:addentries', $context);

if (! $ogte = $DB->get_record("ogte", array("id" => $cm->instance))) {
    throw new \moodle_exception('invalidcoursemodule');
}
if (!empty($ogte->preventry)){
    $prev_ogte = $DB->get_record("ogte", array("id" => $ogte->preventry));
    $prev_entry = $DB->get_record("ogte_entries", array("userid" => $USER->id, "ogte" => $ogte->preventry));
}
// Header.
$PAGE->set_url('/mod/ogte/edit.php', array('id' => $id, 'entryid' => $entryid));
$PAGE->navbar->add(get_string('edit'));
$PAGE->set_title(format_string($ogte->name));
$PAGE->set_heading($course->fullname);
$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

//get the existing entry if it exists
if($entryid) {
    $entry = $DB->get_record("ogte_entries", array("userid" => $USER->id, "ogte" => $ogte->id, "id" => $entryid));
} else {
    $entry = false;
}

//handle delete actions
//we always head back to view page after delete and cancel actions
$redirecturl = new moodle_url('/mod/ogte/view.php', array('id'=>$cm->id));
if($action == 'confirmdelete'){

    echo $renderer->header();
    echo $renderer->confirm(get_string("confirmentrydelete",constants::M_COMPONENT,$entry->title),
        new moodle_url('/mod/ogte/edit.php', array('action'=>'delete','id'=>$cm->id,'entryid'=>$entryid)),
        $redirecturl);
    echo $renderer->footer();
    return;

    /////// Delete item NOW////////
}elseif ($action == 'delete'){
    require_sesskey();
    $success = $DB->delete_records(constants::M_ENTRIESTABLE, array("userid" => $USER->id, "ogte" => $ogte->id, "id" => $entryid));
    redirect($redirecturl);
}

//get the form (if it has data OR been cancelled - we need it)
$form = new \mod_ogte\local\form\entryform(null, array('entryid' => $entryid));
if ($form->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/ogte/view.php?id=' . $cm->id);
} else if ($fromform = $form->get_data()) {
    // If data submitted, then process and store.

    // Prevent CSFR.
    confirm_sesskey();
    $timenow = time();
    $newentry = new stdClass();
    $newentry->text = $fromform->text;
    $newentry->title = $fromform->title;
    $newentry->listid = $fromform->listid;
    $newentry->levelid =$fromform->levelid;
    $newentry->ignores = $fromform->ignores;
    $newentry->jsonrating = $fromform->jsonrating;
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

//show a form with data or empty form
$data = new stdClass();
if ($entryid && $entry) {
    $data->entryid = $entry->id;
    $data->text = $entry->text;
    $data->title = $entry->title;
    $data->listid = $entry->listid;
    $data->levelid = $entry->levelid;
    $data->ignores = $entry->ignores;
    $data->jsonrating = $entry->jsonrating;
} else {
    $data->entryid = null;
    $data->text = '';
    if(!empty($passagetext)){
        $data->text = $passagetext;
    }
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


$form->set_data($data);
$renderer = $PAGE->get_renderer(constants::M_COMPONENT);
echo $renderer->header();
echo $renderer->heading(format_string($ogte->name));

//echo our ai and level widgets and tabs
$listlevels =utils::get_level_options();
$leveloptions=isset($data->listid) && isset($listlevels[$data->listid]) ? $listlevels[$data->listid]:[];

//here we sneakily add the form to the template data as well as list options
//in the case where there is no form (for display on top page of site) then template gracefully ignores its absence
$params =['cloudpoodlltoken'=>$token,'ogteid'=>$cm->instance,
    'listoptions'=>utils::get_list_options(),'leveloptions'=>$leveloptions,
    'listlevels'=>$listlevels,'passage'=>$data->text,'form'=>$form->render()];


//we put the opts in html on the page because moodle/AMD doesn't like lots of opts in js
$jsonstring = json_encode($params);
$optsid='amdopts_mod_ogte_editopts';
$opts_html =
    \html_writer::tag('input', '', array('id' => $optsid , 'type' => 'hidden', 'value' => $jsonstring));
echo $opts_html;
$opts = array('optsid' => $optsid);
$PAGE->requires->js_call_amd("mod_ogte/articleleveler", 'init', array($opts));
echo $renderer->render_from_template('mod_ogte/tabsandeditor', $params) ;
$PAGE->requires->strings_for_js(['alreadyignored','selecttoignore','doignore',
    'entersomething','texttoolong5000','ignored','outoflist','outoflevel','outoflevelfreq'],constants::M_COMPONENT);


//echo the footer
echo $renderer->footer();
