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

use \mod_ogte\constants;
use \mod_ogte\utils;

$id = required_param('id', PARAM_INT);    // Course Module ID.

if (! $cm = get_coursemodule_from_id('ogte', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array('id' => $cm->course))) {
    throw new \moodle_exception('coursemisconf');
}

$context = context_module::instance($cm->id);

require_login($course, true, $cm);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$entriesmanager = has_capability('mod/ogte:manageentries', $context);
$canadd = has_capability('mod/ogte:addentries', $context);
$isogteguest = !$entriesmanager && !$canadd;

require_capability('mod/ogte:addentries', $context);
if (!$entriesmanager && !$canadd) {
    throw new \moodle_exception('accessdenied');
}

if (! $ogte = $DB->get_record("ogte", array("id" => $cm->instance))) {
    throw new \moodle_exception('invalidcoursemodule');
}

if (! $cw = $DB->get_record("course_sections", array("id" => $cm->section))) {
    throw new \moodle_exception('invalidcoursemodule');
}

$ogtename = format_string($ogte->name, true, array('context' => $context));

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

// Header.
$PAGE->set_url('/mod/ogte/view.php', array('id' => $id));
$PAGE->navbar->add($ogtename);
$PAGE->set_title($ogtename);
$PAGE->set_heading($course->fullname);

$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

echo $renderer->header();


// Check to see if groups are being used here.
$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);
// groups_print_activity_menu($cm, $CFG->wwwroot . "/mod/ogte/view.php?id=$cm->id");


$intro = format_module_intro('ogte', $ogte, $cm->id);
echo $renderer->box($intro);

//template data
$tdata=[];
$tdata['cloudpoodlltoken']=$token;

//intro
if (!empty($ogte->intro)) {
    $ogte->intro = trim($ogte->intro);
    $tdata['intro'] = format_module_intro('ogte', $ogte, $cm->id);
}

//Check download mode, and display the download page button
//is this old code? I think we do not need it. Justin 20/01/2024
/*
if ($ogte->mode == 1){
    $tdata['downloadbutton'] = $renderer->single_button('download.php?id='.$cm->id, get_string('download', 'ogte'), 'get',
                array("class" => "singlebutton ogtestart"));
    echo $renderer->render_from_template('mod_ogte/downloadpage', $tdata);
    echo $renderer->footer();
    die;
}
*/

// Display entries
$lists=utils::get_level_options();
$entries = $DB->get_records(constants::M_ENTRIESTABLE, array('userid' => $USER->id, 'ogte' => $ogte->id));
if($entries) {
    $theentries =[];
    $sesskey = sesskey();
    foreach(array_values($entries) as $i=>$entry){
        $arrayitem = (Array)$entry;
        $arrayitem['index']=($i+1);
        //get list and level info
        $thelevels=utils::get_level_options($entry->listid);
        if(array_key_exists($entry->levelid, $thelevels)) {
            $arrayitem['listinfo'] = $thelevels[$entry->levelid]['listname'] . ' - ' . $thelevels[$entry->levelid]['label'];
        }else{
            $arrayitem['listinfo'] ='';
        }

        $editurl=new moodle_url('/mod/ogte/edit.php', array('id'=>$cm->id, 'entryid'=>$entry->id,'sesskey'=>$sesskey ,'action'=>'edit'));
        $downloadurl_pdf=new moodle_url('/mod/ogte/download.php', array('id'=>$cm->id, 'entryid'=>$entry->id,'sesskey'=>$sesskey ,'action'=>'download','format'=>'pdf'));
        $downloadurl_txt=new moodle_url('/mod/ogte/download.php', array('id'=>$cm->id, 'entryid'=>$entry->id,'sesskey'=>$sesskey ,'action'=>'download','format'=>'txt'));
        $deleteurl=new moodle_url('/mod/ogte/edit.php', array('id'=>$cm->id, 'entryid'=>$entry->id,'sesskey'=>$sesskey ,'action'=>'confirmdelete'));
        $arrayitem['editurl']=$editurl->out();
        $arrayitem['downloadurlpdf']=$downloadurl_pdf->out();
        $arrayitem['downloadurltxt']=$downloadurl_txt->out();
        $arrayitem['deleteurl']=$deleteurl->out();

        $theentries[]= $arrayitem;
    }
    $tdata['haveentries']=true;
    $tdata['entries'] =  $theentries;
    $tdata['downloadallurlpdf']=new moodle_url('/mod/ogte/download.php', array('id'=>$cm->id, 'entryid'=>0,'sesskey'=>$sesskey ,'action'=>'download','format'=>'pdf'));
    $tdata['downloadallurltxt']=new moodle_url('/mod/ogte/download.php', array('id'=>$cm->id, 'entryid'=>0,'sesskey'=>$sesskey ,'action'=>'download','format'=>'txt'));
}

if ($canadd) {
    $tdata['addnewbutton'] = $renderer->single_button('edit.php?id='.$cm->id, get_string('addnew', 'ogte'), 'get',
        array("class" => "singlebutton ogtestart"));
    $addnewurl =new moodle_url('/mod/ogte/edit.php', array('id'=>$cm->id));
    $tdata['addnewurl'] = $addnewurl->out();
}

//lists page button
if(has_capability('mod/ogte:manage', $context)) {
    $tdata['backtolistsbutton'] = $renderer->back_to_lists_button($cm, get_string('addeditlists', constants::M_COMPONENT));
}

$tdata['isogteguest']=$isogteguest;
echo $renderer->render_from_template('mod_ogte/viewpage', $tdata);



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
