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
 * Action for adding/editing a list.
 *
 * @package mod_ogte
 * @copyright  2019 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

use \mod_ogte\constants;
use \mod_ogte\utils;

require_once("../../../config.php");
require_once($CFG->dirroot.'/mod/ogte/lib.php');


global $USER,$DB;

// first get the nfo passed in to set up the page
$moduleid= required_param('moduleid',PARAM_INT);
$id     = optional_param('id',0, PARAM_INT);         // Course Module ID
$action = optional_param('action','edit',PARAM_TEXT);

// get the objects we need
$cm = get_coursemodule_from_instance(constants::M_MODNAME, $moduleid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$moduleinstance = $DB->get_record(constants::M_MODNAME, array('id' => $moduleid), '*', MUST_EXIST);

//make sure we are logged in and can see this form
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ogte:manage', $context);

//set up the page object
$PAGE->set_url('/mod/ogte/list/managelists.php', array('moduleid'=>$moduleid, 'id'=>$id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');

//are we in new or edit mode?
if ($id) {
    $list = $DB->get_record(constants::M_LISTSTABLE, array('id'=>$id), '*', MUST_EXIST);
    if(!$list){
        print_error('could not find list of id:' . $id);
    }
    $edit = true;
} else {
    $edit = false;
}

//we always head back to the ogte lists page
$redirecturl = new moodle_url('/mod/ogte/list/lists.php', array('id'=>$cm->id));

//handle delete actions
if($action == 'confirmdelete'){
    $renderer = $PAGE->get_renderer(constants::M_COMPONENT);
    echo $renderer->header($moduleinstance, $cm, 'lists', null, get_string('confirmlistdeletetitle', constants::M_COMPONENT));
    echo $renderer->confirm(get_string("confirmlistdelete",constants::M_COMPONENT,$list->name),
            new moodle_url('/mod/ogte/list/managelists.php', array('action'=>'delete','moduleid'=>$moduleid,'id'=>$id)),
            $redirecturl);
    echo $renderer->footer();
    return;

    /////// Delete list NOW////////
}elseif ($action == 'delete'){
    require_sesskey();
    //delete the list words
    $DB->delete_records(constants::M_WORDSTABLE, array('list'=>$id));
    //delete the list
    $DB->delete_records(constants::M_LISTSTABLE, array('id'=>$id));

    redirect($redirecturl);
}

$siteconfig = get_config(constants::M_COMPONENT);

//get the mform for our list
$mform = new \mod_ogte\local\form\listform(null, array('moduleinstance'=>$moduleinstance));

//if the cancel button was pressed, we are out of here
if ($mform->is_cancelled()) {
    redirect($redirecturl);
    exit;
}

//if we have data, then our job here is to save it and return to the quiz edit page
if ($data = $mform->get_data()) {
    require_sesskey();

    $thelist = $data;

    //$thelist->moduleid = $moduleinstance->id;
    //$thelist->listlevel = $data->listlevel;
    //$thelist->targetwords=  $data->targetwords;
    //$thelist->fonticon=  $data->fonticon;
    $thelist->timemodified=time();

    //first insert a new list if we need to
    //that will give us a listid, we need that for saving files
    if(!$edit){
        $thelist->id = null;
        $thelist->timecreated=time();

        //try to insert it
        if (!$thelist->id = $DB->insert_record(constants::M_LISTSTABLE,$thelist)){
            print_error("Could not insert ogte list!");
            redirect($redirecturl);
        }
    }else{
        //now update the db once we have saved files and stuff
        if (!$DB->update_record(constants::M_LISTSTABLE,$thelist)){
            print_error("Could not update ogte list!");
            redirect($redirecturl);
        }
    }


    //if we got here we did achieve some update
    redirect($redirecturl);

}


//if  we got here, there was no cancel, and no form data, so we are showing the form
//if edit mode load up the list into a data object
if ($edit) {
    $data = $list;


}else{
    $data=new stdClass;
    $data->id = null;
}
$data->courseid=$course->id;
$data->moduleid = $moduleid;


//Set up the list type specific parts of the form data
$renderer = $PAGE->get_renderer('mod_ogte');
$mform->set_data($data);
$PAGE->navbar->add(get_string('edit'), new moodle_url('/mod/ogte/list/lists.php', array('id'=>$moduleid)));
$PAGE->navbar->add(get_string('editinglist', constants::M_COMPONENT));
$mode='lists';
echo $renderer->header($moduleinstance, $cm,$mode, null, get_string('editlist', constants::M_COMPONENT));
$mform->display();
echo $renderer->footer();