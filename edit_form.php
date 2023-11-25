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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

use \mod_ogte\constants;

class mod_ogte_entry_form extends moodleform {

    public function definition() {

        //text field
        $this->_form->addElement('hidden', 'text');
        $this->_form->setType('text', PARAM_RAW);

        //json rating field
        $this->_form->addElement('hidden', 'jsonrating');
        $this->_form->setType('jsonrating', PARAM_RAW);
        $this->_form->setDefault('jsonrating','{}');

        //item id
        $this->_form->addElement('hidden', 'id');
        $this->_form->setType('id', PARAM_INT);

        //entry id
        $this->_form->addElement('hidden', 'entryid');
        $this->_form->setType('entryid', PARAM_INT);

        //action
        $this->_form->addElement('hidden', 'action');
        $this->_form->setType('action', PARAM_TEXT);
        $this->_form->setDefault('action','form');

        //title field
        $this->_form->addElement('text', 'title', get_string('title', constants::M_COMPONENT), array('size' => '64'));
        $this->_form->setType('title', PARAM_TEXT);
        $this->_form->setDefault('title',get_string('untitled',constants::M_COMPONENT) );

        $this->add_action_buttons();
    }
}
