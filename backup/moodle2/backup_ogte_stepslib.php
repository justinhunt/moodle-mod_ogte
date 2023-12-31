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
 * Backup function
 *
 * @package mod_ogte
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2021 Tengku Alauddin - din@pukunui.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

class backup_ogte_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        $ogte = new backup_nested_element('ogte', array('id'), array(
            'name', 'intro', 'introformat', 'days', 'grade', 'timemodified', 'preventry', 'mode', 'completionanswer'));

        $entries = new backup_nested_element('entries');

        $entry = new backup_nested_element('entry', array('id'), array(
            'userid', 'modified','title', 'text','jsonrating','levelid','listid','ignores', 'format', 'rating',
            'entrycomment', 'teacher', 'timemarked', 'mailed'));

        // OGTE -> entries -> entry.
        $ogte->add_child($entries);
        $entries->add_child($entry);

        // Sources.
        $ogte->set_source_table('ogte', array('id' => backup::VAR_ACTIVITYID));

        if ($this->get_setting_value('userinfo')) {
            $entry->set_source_table('ogte_entries', array('ogte' => backup::VAR_PARENTID));
        }

        // Define id annotations.
        $entry->annotate_ids('user', 'userid');
        $entry->annotate_ids('user', 'teacher');

        // Define file annotations.
        $ogte->annotate_files('mod_ogte', 'intro', null); // This file areas haven't itemid.
        $entry->annotate_files('mod_ogte_entries', 'text', null); // This file areas haven't itemid.
        $entry->annotate_files('mod_ogte_entries', 'entrycomment', null); // This file areas haven't itemid.

        return $this->prepare_activity_structure($ogte);
    }
}
