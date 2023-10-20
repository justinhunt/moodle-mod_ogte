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
 * Restore function
 *
 * @package mod_ogte
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2021 Tengku Alauddin - din@pukunui.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
 
defined('MOODLE_INTERNAL') || die();

class restore_ogte_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $paths[] = new restore_path_element('ogte', '/activity/ogte');

        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('ogte_entry', '/activity/ogte/entries/entry');
        }

        return $this->prepare_activity_structure($paths);
    }

    protected function process_ogte($data) {

        global $DB;

        $data = (Object)$data;

        unset($data->id);

        $data->course = $this->get_courseid();
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->preventry = $this->get_mappingid('ogte', $data->preventry);

        $newid = $DB->insert_record('ogte', $data);
        $this->apply_activity_instance($newid);
    }

    protected function process_ogte_entry($data) {

        global $DB;

        $data = (Object)$data;

        $oldid = $data->id;
        unset($data->id);

        $data->ogte = $this->get_new_parentid('ogte');
        $data->modified = $this->apply_date_offset($data->modified);
        $data->timemarked = $this->apply_date_offset($data->timemarked);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->teacher = $this->get_mappingid('user', $data->teacher);

        $newid = $DB->insert_record('ogte_entries', $data);
        $this->set_mapping('ogte_entry', $oldid, $newid);
    }

    protected function after_execute() {
        $this->add_related_files('mod_ogte', 'intro', null);
        $this->add_related_files('mod_ogte_entries', 'text', null);
        $this->add_related_files('mod_ogte_entries', 'entrycomment', null);
    }
}
