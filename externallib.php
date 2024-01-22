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
 * External Library
 *
 * @package mod_ogte
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2021 Tengku Alauddin - din@pukunui.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use \mod_ogte\utils;

class mod_ogte_external extends external_api {

    public static function get_coverage_parameters() {
        return new external_function_parameters(
            array(
                'ogteid' => new external_value(PARAM_INT, 'id of ogte activity'),
                'listid' => new external_value(PARAM_INT, 'id of list to compare'),
                'listlevel' => new external_value(PARAM_INT, 'level of list to compare'),
                'passage' => new external_value(PARAM_RAW, 'passage text'),
                'ignore' => new external_value(PARAM_RAW, 'ignore text')
            )
        );
    }

    public static function get_coverage_returns() {
        return new external_value(PARAM_RAW);
    }


    public static function get_coverage($ogteid,$listid,$listlevel,$passage,$ignore) {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_coverage_parameters(),
            array('ogteid' => $ogteid,'listid'=>$listid,'listlevel'=>$listlevel,'passage'=>$passage,'ignore'=>$ignore));

        if (! $ogte = $DB->get_record("ogte", array("id" => $ogteid))) {
            throw new invalid_parameter_exception("OGTE id is incorrect");
        }
        $course     = $DB->get_record('course', array('id' => $ogte->course), '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('ogte', $ogte->id, $course->id, false, MUST_EXIST);

        $context = context_module::instance($cm->id);

        //disable these for now
       // self::validate_context($context);;
        //require_capability('mod/ogte:use', $context);

        //here we do the list comparison
        $result = utils::get_coverage($passage,$ignore,$listid,$listlevel);
        if(true){
            return json_encode($result);
        } else {
            return "{}";
        }
    }

    public static function get_entry_parameters() {
        return new external_function_parameters(
            array(
                'ogteid' => new external_value(PARAM_INT, 'id of ogte')
            )
        );
    }

    public static function get_entry_returns() {
        return new external_single_structure(
            array(
                'text' => new external_value(PARAM_RAW, 'ogte text'),
                'modified' => new external_value(PARAM_INT, 'last modified time'),
                'rating' => new external_value(PARAM_FLOAT, 'teacher rating'),
                'comment' => new external_value(PARAM_RAW, 'teacher comment'),
                'teacher' => new external_value(PARAM_INT, 'id of teacher')
            )
        );
    }

    public static function get_entry($ogteid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_entry_parameters(), array('ogteid' => $ogteid));

        if (! $cm = get_coursemodule_from_id('ogte', $params['ogteid'])) {
            throw new invalid_parameter_exception('Course Module ID was incorrect');
        }

        if (! $course = $DB->get_record("course", array('id' => $cm->course))) {
            throw new invalid_parameter_exception("Course is misconfigured");
        }

        if (! $ogte = $DB->get_record("ogte", array("id" => $cm->instance))) {
            throw new invalid_parameter_exception("Course module is incorrect");
        }

        $context = context_module::instance($cm->id);
        self::validate_context($context);;
        require_capability('mod/ogte:addentries', $context);

        if ($entry = $DB->get_record('ogte_entries', array('userid' => $USER->id, 'ogte' => $ogte->id))) {
            return array(
                'text' => $entry->text,
                'modified' => $entry->modified,
                'rating' => $entry->rating,
                'comment' => $entry->entrycomment,
                'teacher' => $entry->teacher
            );
        } else {
            return "";
        }
    }


    public static function set_text_parameters() {
        return new external_function_parameters(
            array(
                'ogteid' => new external_value(PARAM_INT, 'id of ogte'),
                'text' => new external_value(PARAM_RAW, 'text to set'),
                'format' => new external_value(PARAM_INT, 'format of text')
            )
        );
    }

    public static function set_text_returns() {
        return new external_value(PARAM_RAW, 'new text');
    }

    public static function set_text($ogteid, $text, $format) {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::set_text_parameters(),
            array('ogteid' => $ogteid, 'text' => $text, 'format' => $format)
        );

        if (! $cm = get_coursemodule_from_id('ogte', $params['ogteid'])) {
            throw new invalid_parameter_exception('Course Module ID was incorrect');
        }

        if (! $course = $DB->get_record("course", array('id' => $cm->course))) {
            throw new invalid_parameter_exception("Course is misconfigured");
        }

        if (! $ogte = $DB->get_record("ogte", array("id" => $cm->instance))) {
            throw new invalid_parameter_exception("Course module is incorrect");
        }

        $context = context_module::instance($cm->id);
        self::validate_context($context);;
        require_capability('mod/ogte:addentries', $context);

        $entry = $DB->get_record('ogte_entries', array('userid' => $USER->id, 'ogte' => $ogte->id));

        $timenow = time();
        $newentry = new stdClass();
        $newentry->text = $params['text'];
        $newentry->format = $params['format'];
        $newentry->modified = $timenow;

        if ($entry) {
            $newentry->id = $entry->id;
            $DB->update_record("ogte_entries", $newentry);
        } else {
            $newentry->userid = $USER->id;
            $newentry->ogte = $ogte->id;
            $newentry->id = $DB->insert_record("ogte_entries", $newentry);
        }

        if ($entry) {
            // Trigger module entry updated event.
            $event = \mod_ogte\event\entry_updated::create(array(
                'objectid' => $ogte->id,
                'context' => $context
            ));
        } else {
            // Trigger module entry created event.
            $event = \mod_ogte\event\entry_created::create(array(
                'objectid' => $ogte->id,
                'context' => $context
            ));

        }
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('ogte', $ogte);
        $event->trigger();

        return $newentry->text;
    }
}