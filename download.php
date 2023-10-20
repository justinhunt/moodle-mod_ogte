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
 * Download functions
 *
 * @package mod_ogte
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2021 Tengku Alauddin - din@pukunui.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/pdflib.php');

$id = required_param('id', PARAM_INT);    // Course Module ID.

if (! $cm = get_coursemodule_from_id('ogte', $id)) {
    print_error("Course Module ID was incorrect");
}

if (! $course = $DB->get_record("course", array('id' => $cm->course))) {
    print_error("Course is misconfigured");
}
$categoryname = $DB->get_record("course_categories", array('id' => $course->category));
$categoryname = $categoryname->name;
$coursename = $course->fullname;
$username = fullname($USER);

$context = context_module::instance($cm->id);

require_login($course, true, $cm);

if (! $ogte = $DB->get_record("ogte", array("id" => $cm->instance))) {
    print_error("Course module is incorrect");
}
//Retrieve sections from course, sort by section and retrieve course module ids
if (! $cw = $DB->get_records("course_sections", array('course' => $cm->course), 'section')) {
    print_error("Course module is incorrect");
}

$moduleid = $DB->get_record("modules", array('name' => 'ogte'));

//Retrieve ogte modules in the course, remove deleted/hiddden
//build $modulesearch for $item 
$moduleslist = $DB->get_records("course_modules", array('course' => $cm->course, 'module' => $moduleid->id));

$modulesearch = array();
foreach ($moduleslist as $module){
    if ($module->deletioninprogress || !$module->visible){
        unset ($moduleslist[$module->id]);
    }else{
        array_push($modulesearch, $module->id);
    }
}
//$item is sorted using section name for display.
$item = array();
foreach ($cw as $section){
    if (empty($section->name)){
        if ($section->section == 0){
            $item[$section->section]->section_name="General";
        }else{
            $item[$section->section]->section_name="Topic " . (string)$section->section;            
        }
    }else{
        $item[$section->section]->section_name=$section->name;
    }
    // Filter out unrelated modules in sequence
    if (!empty($section->sequence)){
        if (strpos($section->sequence, ',') !== false){
            $seq = array();
            $seq = explode(',',$section->sequence);
            $prep = array();
            foreach ($seq as $s){
                if (in_array($s, $modulesearch)){
                    array_push($prep, $s);
                }
            }
            if (empty($prep)){
                // Remove unneeded section
                unset($item[$section->section]);
            }else{
                $item[$section->section]->sequence = $prep;
            }
        }else{
            if (in_array($section->sequence, $modulesearch)){
                $item[$section->section]->sequence=[$section->sequence];
            }else{
                unset($item[$section->section]);
            }
        }
    }else{
        unset($item[$section->section]);
    }
}

$moduleid = $DB->get_record("modules", array('name' => 'ogte'));
//Retrieve ogte modules in the course, remove deleted/hiddden and order by
$moduleslist = $DB->get_records("course_modules", array('course' => $cm->course, 'module' => $moduleid->id));
$modulesearch = array(); 
$moduleinstance = array();
foreach ($moduleslist as $module){
    if ($module->deletioninprogress || !$module->visible){
        unset ($moduleslist[$module->id]);
    }else{
        array_push($modulesearch, $module->id);
        $moduleinstance[$module->id] = $module->instance;
    }
}

$sp = $DB->get_records("ogte", array("course" => $course->id));
foreach ($sp as $s){
    if ($s->mode == 1){
        unset($sp[$s->id]);
    }
}
ob_clean();
$doc = new pdf();
$doc->setPrintHeader(false);
$doc->setPrintFooter(false);
$doc->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

$doc->AddPage();

$html = '<h1>' . $categoryname . ':' . $coursename . '</h1>';
$html .= '<h4>' .get_string('name', 'ogte') . $username.'</h4>';

foreach ($item as $list){
    $htmlsection = $htmlmodule = '';
    $section_count = 0;
    $htmlsection .= '<hr><h3>'. format_text($list->section_name, FORMAT_PLAIN) . '</h3>';
    foreach ($list->sequence as $l){
        $section_count++;
        $obj = $sp[$moduleinstance[$l]];
        if (empty($obj)){
            continue;
        }
        $pagetitle = $obj->name;
        $question = $obj->intro;
        $htmlmodule = '<strong><u>'.format_text($pagetitle, FORMAT_PLAIN).'</u></strong><br>';
        $htmlmodule .= $question;
        
        $entry = $DB->get_record('ogte_entries', array('userid' => $USER->id, 'ogte' => $obj->id));
        $text = format_text($entry->text, FORMAT_PLAIN);
        $htmlmodule .= '<p><em>'.$text.'</em></p>';
        
        if (!empty($htmlmodule)){
            if ($section_count == 1){
                $html .= $htmlsection;                
            }
            $html .= $htmlmodule;
            $html .='<br>';
        }
    }
}
// output the HTML content
$doc->writeHTML($html, true, false, true, false, '');

$doc->Output('OGTE - '.$coursename.' - '.$username.'.pdf', 'D');