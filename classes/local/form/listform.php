<?php

namespace mod_ogte\local\form;

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// This file is part of Moodle - http://moodle.org/                      //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//                                                                       //
// Moodle is free software: you can redistribute it and/or modify        //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation, either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// Moodle is distributed in the hope that it will be useful,             //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details.                          //
//                                                                       //
// You should have received a copy of the GNU General Public License     //
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.       //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

/**
 * Forms for ogte Activity
 *
 * @package    mod_ogte
 * @author     Justin Hunt <poodllsupport@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Justin Hunt  http://poodll.com
 */

require_once($CFG->libdir . '/formslib.php');

use \mod_ogte\constants;
use \mod_ogte\utils;

/**
 * Abstract class that item type's inherit from.
 *
 * This is the abstract class that add item type forms must extend.
 *
 * @abstract
 * @copyright  2014 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class listform extends \moodleform {



    /**
     * The module instance
     * @var array
     */
    protected $moduleinstance = null;

	
    /**
     * True if this is a standard item of false if it does something special.
     * items are standard items
     * @var bool
     */
    protected $standard = true;

    /**
     * Each item type can and should override this to add any custom elements to
     * the basic form that they want
     */
    public function custom_definition() {}

    /**
     * Item types can override this to add any custom elements to
     * the basic form that they want
     */
   public function custom_definition_after_data() {}

    /**
     * Used to determine if this is a standard item or a special item
     * @return bool
     */
    public final function is_standard() {
        return (bool)$this->standard;
    }

    /**
     * Add the required basic elements to the form.
     *
     * This method adds the basic elements to the form including title and contents
     * and then calls custom_definition();
     */
    public final function definition() {
        global $CFG, $COURSE;

      //  $this->filemanageroptions = $this->_customdata['filemanageroptions'];

        $mform = $this->_form;
	
        $mform->addElement('header', 'listheading', get_string('editinglist', constants::M_COMPONENT, get_string('listformtitle', constants::M_COMPONENT)));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
/*
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
*/
        $mform->addElement('hidden', 'moduleid');
        $mform->setType('moduleid', PARAM_INT);

        //name
        $mform->addElement('text', 'name', get_string('listname', constants::M_COMPONENT), array('size'=>70));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        //site or course
        $siteorcourse_opts = [0=>get_string('listsite',constants::M_COMPONENT),1=>get_string('listcourse',constants::M_COMPONENT)];
        $mform->addElement('select', 'siteorcourse', get_string('listsiteorcourse', constants::M_COMPONENT),$siteorcourse_opts);
        $mform->setType('siteorcourse', PARAM_INT);
        $mform->setDefault('siteorcourse', 0);
        $mform->addRule('siteorcourse', get_string('required'), 'required', null, 'client');

        //description
        $mform->addElement('textarea', 'description', get_string('listdescription', constants::M_COMPONENT), array('size'=>70));
        $mform->setType('description', PARAM_TEXT);
        $mform->addRule('description', get_string('required'), 'required', null, 'client');

        //lang
        $listlang_opts = utils::get_lang_options();
        $mform->addElement('select', 'lang', get_string('lang', constants::M_COMPONENT),$listlang_opts);
        $mform->setType('lang', PARAM_TEXT);
        $mform->setDefault('lang', constants::M_LANG_ENUS);
        $mform->addRule('lang', get_string('required'), 'required', null, 'client');

        //Proper Nouns list
        $mform->addElement('advcheckbox', 'ispropernouns', get_string('ispropernouns', constants::M_COMPONENT),  get_string('propernouns_details', constants::M_COMPONENT));

        //status
        /*
        $liststatus_opts = [constants::M_LISTSTATUS_EMPTY=>'empty',constants::M_LISTSTATUS_READY=>'ready'];
        $mform->addElement('select', 'status', get_string('liststatus', constants::M_COMPONENT),$liststatus_opts);
        $mform->setType('status', PARAM_INT);
        $mform->addRule('status', get_string('required'), 'required', null, 'client');
        */
        //props
        $mform->addElement('hidden', 'status');
        $mform->setType('status', PARAM_INT);

        //props
        //$mform->addElement('hidden', 'props');
       // $mform->setType('props', PARAM_TEXT);
        $mform->addElement('textarea', 'props', get_string('listprops', constants::M_COMPONENT), array('size'=>70));
        $mform->setType('props', PARAM_TEXT);
        $mform->addRule('props', get_string('required'), 'required', null, 'client');
        $levels=[['name'=>'levelone','top'=>75,'bottom'=>1],
            ['name'=>'leveltwo','top'=>175,'bottom'=>76],
            ['name'=>'levelthree','top'=>275,'bottom'=>176],
            ['name'=>'levelfour','top'=>375,'bottom'=>276],
            ['name'=>'levelfive','top'=>475,'bottom'=>376],
            ['name'=>'levelsix','top'=>675,'bottom'=>476],
            ['name'=>'levelseven','top'=>875,'bottom'=>676]
        ];
        $mform->setDefault('props', json_encode($levels));

        $this->custom_definition();

		//add the action buttons
        $this->add_action_buttons(get_string('cancel'), get_string('savelist', constants::M_COMPONENT));

    }

    public final function definition_after_data() {
        parent::definition_after_data();
        $this->custom_definition_after_data();
    }

    /**
     * A function that gets called upon init of this object by the calling script.
     *
     * This can be used to process an immediate action if required. Currently it
     * is only used in special cases by non-standard item types.
     *
     * @return bool
     */
    public function construction_override($itemid,  $ogte) {
        return true;
    }
}