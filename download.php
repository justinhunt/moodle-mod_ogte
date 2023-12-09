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
 * @copyright  2023 Justin Hunt - justin@poodll.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/pdflib.php');

use \mod_ogte\constants;
use \mod_ogte\utils;

$id = required_param('id', PARAM_INT);    // Course Module ID.
$entryid = required_param('entryid', PARAM_INT);    // Course Module ID.
$format = optional_param('format', 'pdf', PARAM_ALPHA);    // Course Module ID.

if (! $cm = get_coursemodule_from_id('ogte', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array('id' => $cm->course))) {
    throw new \moodle_exception('coursemisconf');
}
$categoryname ="";
$categoryname = $DB->get_record("course_categories", array('id' => $course->category));
if($categoryname) {
    $categoryname = $categoryname->name;
}
$coursename = $course->fullname;
$username = fullname($USER);

$context = context_module::instance($cm->id);

require_login($course, true, $cm);

if (! $ogte = $DB->get_record("ogte", array("id" => $cm->instance))) {
    throw new \moodle_exception('invalidcoursemodule');
}

//Do we want all entries for this user or just one?
if($entryid) {
    $entries = $DB->get_records('ogte_entries', array('userid' => $USER->id, 'id' => $entryid));
}else{
    $entries = $DB->get_records('ogte_entries', array('userid' => $USER->id, 'ogte' => $ogte->id));
}

//get Level options
$thelevels =utils::get_level_options();

//get the document title
if($entryid && count($entries)==1 && array_key_exists($entryid, $entries)) {
    $doctitle= $entries[$entryid]->title;
}else{
    $doctitle= 'passages';
}

//If its a text document
if($format=='txt'){
    $filename = 'OGTE - '.$doctitle.' - '.$username.'.txt';
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    foreach ($entries as $entry) {
        echo get_string('title', 'ogte') .': ' . $entry->title . "\n";
        echo get_string('author', 'ogte') .': ' . $username . "\n";
        if(array_key_exists($entry->listid, $thelevels) && array_key_exists($entry->levelid, $thelevels[$entry->listid])) {
            $thelevel=$thelevels[$entry->listid];
            $listandlevel = $thelevel[$entry->levelid]['listname'] . ' - ' . $thelevel[$entry->levelid]['label'];
        }else{
            $listandlevel ='';
        }
        $listandlevel = get_string('listlevel', 'ogte') . ": " . $listandlevel;
        if (utils::is_json($entry->jsonrating)) {
            $jsonrating = json_decode($entry->jsonrating);
            $coverage = $jsonrating->coverage;
        } else {
            $coverage = '';
        }
        if(!empty($coverage)){$coverage = $coverage . '%';}
        $coverage = get_string('coverage', 'ogte') . ": " . $coverage;
        echo $listandlevel ."\n";
        echo $coverage ."\n";
        $ignoring = get_string('ignoring', 'ogte') . ": ";
        echo "$ignoring" . $entry->ignores . "\n";
        echo "\n";
        $text = html_entity_decode($entry->text, ENT_COMPAT, 'UTF-8');
        $text = str_replace('<br />', "\n", $text);
        // Remove consecutive newline characters
        $text = preg_replace("/\n+/", "\n", $text);

        echo $text . "\n";
        echo "-----------------------------------------------\n";
        echo "\n";
    }
    exit;

//If its a PDF document
}else {

    ob_clean();
    $doc = new pdf();
    $doc->setPrintHeader(false);
    $doc->setPrintFooter(false);
    $doc->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

//loop through entries each on a new page
    foreach ($entries as $entry) {
        //new page
        $doc->AddPage();

        //title and author
        $html = '';
        $html .= '<h1>' . format_text($entry->title, FORMAT_PLAIN) . '</h1>';
        $html .= '<h4>' . get_string('author', 'ogte') . ': ' . $username . '</h4>';
        $htmlsection = $htmlmodule = '';
        $pagetitle = $entry->title;

        //list and level and coverage

        if (array_key_exists($entry->listid, $thelevels) && array_key_exists($entry->levelid, $thelevels[$entry->listid])) {
            $thelevel = $thelevels[$entry->listid];
            $listandlevel = $thelevel[$entry->levelid]['listname'] . ' - ' . $thelevel[$entry->levelid]['label'];
        } else {
            $listandlevel = '';
        }
        $listandlevel = get_string('listlevel', 'ogte') . ": " . $listandlevel;
        if (utils::is_json($entry->jsonrating)) {
            $jsonrating = json_decode($entry->jsonrating);
            $coverage = $jsonrating->coverage;
        } else {
            $coverage = '';
        }
        if(!empty($coverage)){$coverage = $coverage . '%';}
        $coverage = get_string('coverage', 'ogte') . ": " . $coverage;
        $htmlmodule = $listandlevel . '<br>';
        $htmlmodule .= $coverage . '<br>';
        $ignoring = get_string('ignoring', 'ogte') . ": <em>";
        $htmlmodule .= "$ignoring" . $entry->ignores . "</em><br>";
        $text = format_text($entry->text, FORMAT_PLAIN);
        $htmlmodule .= '<p>' . $text . '</p>';

        if (!empty($htmlmodule)) {
            $html .= $htmlsection;
            $html .= $htmlmodule;
            $html .= '<br>';
        }
        // output the HTML content
        $doc->writeHTML($html, true, false, true, false, '');
    }
    //save the file
    $doc->Output('OGTE - ' . $doctitle . ' - ' . $username . '.pdf', 'D');
}
function decodeEntities($text) {
    return preg_replace_callback('/&#(\d+);/', function($matches) {
        return mb_convert_encoding('&#' . intval($matches[1]) . ';', 'UTF-8', 'HTML-ENTITIES');
    }, $text);
}