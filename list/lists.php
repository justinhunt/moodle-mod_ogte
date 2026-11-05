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
 * Provides the interface for overall managing of lists
 *
 * @package mod_ogte
 * @copyright  2014 Justin Hunt  {@link http://poodll.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/ogte/lib.php');

use mod_ogte\constants;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$ogte = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);

//mode is necessary for tabs
$mode='lists';
//Set page url before require login, so post login will return here
$PAGE->set_url('/mod/ogte/list/lists.php', array('id'=>$cm->id,'mode'=>$mode));

//require login for this page
require_login($course, false, $cm);
$context = context_module::instance($cm->id);

$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

//prepare datatable(before header printed)
$listtableid = constants::M_ID_LISTSTABLE;
$renderer->setup_datatables($listtableid);

$PAGE->navbar->add(get_string('lists', constants::M_COMPONENT));
echo $renderer->header($ogte, $cm, $mode, null, get_string('lists', constants::M_COMPONENT));


// We need permission to be here
if (has_capability('mod/ogte:manage', $context)){
    echo $renderer->add_listpage_buttons($ogte);
}


//if we have lists, show em
$lists = $DB->get_records(constants::M_LISTSTABLE,[]);
if($lists){
	echo $renderer->show_lists_list($lists,$listtableid,$cm);
}
echo $renderer->back_to_viewpage_button($cm,get_string('backtoviewpage', constants::M_COMPONENT));
echo $renderer->footer();
