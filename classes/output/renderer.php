<?php

namespace mod_ogte\output;

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


defined('MOODLE_INTERNAL') || die();

use \mod_ogte\constants;
use \mod_ogte\utils;

/**
 * A custom renderer class that extends the plugin_renderer_base.
 *
 * @package mod_ogte
 * @copyright COPYRIGHTNOTICE
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

 /**
 * Return HTML to display add first page links
 * @param ogte $ogte
 * @return string
 */
 public function add_listpage_buttons($ogte) {
		global $CFG;
        $listid = 0;

        $output = $this->output->heading(get_string("managelists", "ogte"), 3);
        $output .=  \html_writer::div(get_string('listinstructions',constants::M_COMPONENT ),constants::M_COMPONENT .'_instructions');
        $links = array();

		$addurl = new \moodle_url(constants::M_URL . '/list/managelists.php',
			array('moduleid'=>$this->page->cm->instance, 'id'=>$listid));
        $links[] = \html_writer::link($addurl,  get_string('addlist', constants::M_COMPONENT),
            array('class'=>'btn ' . constants::M_COMPONENT .'_menubutton ' . constants::M_COMPONENT .'_activemenubutton'));



    $buttonsdiv = \html_writer::div(implode('', $links),constants::M_COMPONENT .'_mbuttons');
     return $this->output->box($output . $buttonsdiv, 'generalbox firstpageoptions');
    }
	
	/**
	 * Return the html table of lists
	 * @param array homework objects
	 * @param integer $courseid
	 * @return string html of table
	 */
	function show_lists_list($lists,$tableid,$cm){
	
		if(!$lists){
			return $this->output->heading(get_string('nolists',constants::M_COMPONENT), 3, 'main');
		}

		//prepare AMD
        $opts=array('activityid'=>$cm->instance);
     //   $this->page->requires->js_call_amd("mod_ogte/updateselectedlist", 'init', array($opts));

        //prepare table with data
		$table = new \html_table();
		$table->id = $tableid;
		$table->attributes =array('class'=>constants::M_CLASS_LISTSCONTAINER);


		$table->head = array(
         //    get_string('listselected', constants::M_COMPONENT),
			get_string('listname', constants::M_COMPONENT),
			get_string('listprops', constants::M_COMPONENT),
            get_string('listdescription', constants::M_COMPONENT),
            get_string('liststatus', constants::M_COMPONENT),
            get_string('timecreated', constants::M_COMPONENT),
			get_string('actions', constants::M_COMPONENT),
            ''
		);
		$table->headspan = array(1,1,1,1,3);
		$table->colclasses = array(
			'listname', 'listdescription', 'liststatus','timecreated','upload', 'edit','delete'
		);


		//loop through the lists and add to table
        $currentlist=0;
		foreach ($lists as $list) {
            $currentlist++;
            $row = new \html_table_row();

            //list name
            $listnamecell = new \html_table_cell($list->name);
            //list description
            $listdescriptioncell = new \html_table_cell($list->description);
            //list status
            switch($list->liststatus) {
                case constants::M_LISTSTATUS_EMPTY:
                    $liststatus = get_string('liststatusempty',constants::M_COMPONENT);
                    break;
                case constants::M_LISTSTATUS_READY:
                default:
                    $liststatus = get_string('liststatusready',constants::M_COMPONENT);
                    break;
            }
            $liststatuscell = new \html_table_cell($liststatus);

            //created date
            $listtimecreated_content = date("Y-m-d H:i:s",$list->timecreated);
            $listtimecreatedcell = new \html_table_cell($listtimecreated_content);

            //import edit
            $importurl = '/mod/ogte/list/importlist.php';
            $importurl = new \moodle_url($importurl, array('id' => $list->moduleid, 'listid' => $list->id));
            $importlink = \html_writer::link($importurl, get_string('importlist', constants::M_COMPONENT));
            $importcell = new \html_table_cell($importlink);

            //list edit
            $actionurl = '/mod/ogte/list/managelists.php';
            $editurl = new \moodle_url($actionurl, array('moduleid' => $list->moduleid, 'id' => $list->id));
            $editlink = \html_writer::link($editurl, get_string('editlist', constants::M_COMPONENT));
            $editcell = new \html_table_cell($editlink);

		    //list delete
            $deleteurl = new \moodle_url($actionurl,
                    array('moduleid' => $cm->instance, 'id' => $list->id, 'action' => 'confirmdelete'));
            $deletelink = \html_writer::link($deleteurl, get_string('deletelist', constants::M_COMPONENT));
			$deletecell = new \html_table_cell($deletelink);

			$row->cells = array(
                    $listnamecell, $listdescriptioncell,$liststatuscell, $listtimecreatedcell,$importcell, $editcell, $deletecell
			);
			$table->data[] = $row;
		}
		return \html_writer::table($table);

	}

    function setup_datatables($tableid){
        global $USER;

        $tableprops = array();
        $notorderable = array('orderable'=>false);
        $columns = [$notorderable,null,null,$notorderable,$notorderable,null,$notorderable,$notorderable];
        $tableprops['columns']=$columns;

        //default ordering
        $order = array();
        $order[0] =array(1, "asc");
        $tableprops['order']=$order;

        //here we set up any info we need to pass into javascript
        $opts =Array();
        $opts['tableid']=$tableid;
        $opts['tableprops']=$tableprops;
        $this->page->requires->js_call_amd("mod_ogte/datatables", 'init', array($opts));
        $this->page->requires->css( new \moodle_url('https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css'));
    }
}