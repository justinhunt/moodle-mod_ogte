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

require_once($CFG->dirroot.'/mod/ogte/backup/moodle2/restore_ogte_stepslib.php');

class restore_ogte_activity_task extends restore_activity_task {

    protected function define_my_settings() {
    }

    protected function define_my_steps() {
        $this->add_step(new restore_ogte_activity_structure_step('ogte_structure', 'ogte.xml'));
    }

    static public function define_decode_contents() {

        $contents = array();
        $contents[] = new restore_decode_content('ogte', array('intro'), 'ogte');
        $contents[] = new restore_decode_content('ogte_entries', array('text', 'entrycomment'), 'ogte_entry');

        return $contents;
    }

    static public function define_decode_rules() {

        $rules = array();
        $rules[] = new restore_decode_rule('OGTEINDEX', '/mod/ogte/index.php?id=$1', 'course');
        $rules[] = new restore_decode_rule('OGTEVIEWBYID', '/mod/ogte/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('OGTEREPORT', '/mod/ogte/report.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('OGTEEDIT', '/mod/ogte/edit.php?id=$1', 'course_module');

        return $rules;

    }

    public static function define_restore_log_rules() {

        $rules = array();
        $rules[] = new restore_log_rule('ogte', 'view', 'view.php?id={course_module}', '{ogte}');
        $rules[] = new restore_log_rule('ogte', 'view responses', 'report.php?id={course_module}', '{ogte}');
        $rules[] = new restore_log_rule('ogte', 'add entry', 'edit.php?id={course_module}', '{ogte}');
        $rules[] = new restore_log_rule('ogte', 'update entry', 'edit.php?id={course_module}', '{ogte}');
        $rules[] = new restore_log_rule('ogte', 'update feedback', 'report.php?id={course_module}', '{ogte}');

        return $rules;
    }

    public static function define_restore_log_rules_for_course() {

        $rules = array();
        $rules[] = new restore_log_rule('ogte', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
