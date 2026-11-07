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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use \mod_ogte\utils;
use \mod_ogte\local\ai\generator;
use \mod_ogte\local\rewriter;
use core_external\external_multiple_structure;

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

        self::validate_context($context);
        require_capability('mod/ogte:use', $context);

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

    /**
     * Parameters for call_ai.
     *
     * @return external_function_parameters
     */
    public static function call_ai_parameters() {
        return new external_function_parameters([
            'ogteid' => new external_value(PARAM_INT, 'id of the ogte activity'),
            'action' => new external_value(PARAM_ALPHANUMEXT, 'generate_article or grammar_correction'),
            'text' => new external_value(PARAM_RAW, 'the topic or passage'),
            'language' => new external_value(PARAM_ALPHANUMEXT, 'language code, e.g. en-US', VALUE_DEFAULT, 'en-US'),
        ]);
    }

    /**
     * Run one of the AI tools against the site's AI provider.
     *
     * @param int $ogteid
     * @param string $action
     * @param string $text
     * @param string $language
     * @return array
     */
    public static function call_ai($ogteid, $action, $text, $language = 'en-US') {
        $params = self::validate_parameters(self::call_ai_parameters(), [
            'ogteid' => $ogteid, 'action' => $action, 'text' => $text, 'language' => $language,
        ]);
        self::activity_context($params['ogteid'], 'mod/ogte:useai');
        $passage = self::ai_generator()->run($params['action'], $params['text'], $params['language']);
        return ['text' => $passage];
    }

    /**
     * Check the user can use an activity, and get its course id.
     *
     * @param int $ogteid
     * @param string $capability
     * @return int The course id.
     */
    protected static function activity_context(int $ogteid, string $capability): int {
        global $DB;
        $ogte = $DB->get_record('ogte', ['id' => $ogteid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('ogte', $ogte->id, $ogte->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability($capability, $context);
        return (int) $ogte->course;
    }

    /**
     * The AI generator, or an error if no AI provider is set.
     *
     * @return generator
     */
    protected static function ai_generator(): generator {
        $generator = generator::from_settings();
        if (!$generator) {
            throw new moodle_exception('noaiprovider', 'mod_ogte');
        }
        return $generator;
    }

    /**
     * Return structure for call_ai.
     *
     * @return external_single_structure
     */
    public static function call_ai_returns() {
        return new external_single_structure([
            'text' => new external_value(PARAM_RAW, 'the passage the AI wrote'),
        ]);
    }

    /**
     * Parameters for analyse_text.
     *
     * @return external_function_parameters
     */
    public static function analyse_text_parameters() {
        return new external_function_parameters([
            'ogteid' => new external_value(PARAM_INT, 'id of the ogte activity'),
            'listid' => new external_value(PARAM_INT, 'id of the word list'),
            'passage' => new external_value(PARAM_RAW, 'the passage'),
        ]);
    }

    /**
     * Analyse a passage for the Text Rewriter: its sentence figures and its coverage at each level.
     *
     * @param int $ogteid
     * @param int $listid
     * @param string $passage
     * @return array
     */
    public static function analyse_text($ogteid, $listid, $passage) {
        $params = self::validate_parameters(self::analyse_text_parameters(),
            ['ogteid' => $ogteid, 'listid' => $listid, 'passage' => $passage]);
        $courseid = self::activity_context($params['ogteid'], 'mod/ogte:use');
        return rewriter::for_course($params['listid'], $courseid)->analyse($params['passage']);
    }

    /**
     * Return structure for analyse_text.
     *
     * @return external_single_structure
     */
    public static function analyse_text_returns() {
        return new external_single_structure([
            'stats' => new external_single_structure([
                'words' => new external_value(PARAM_INT, 'words'),
                'sentences' => new external_value(PARAM_INT, 'sentences'),
                'avgsentence' => new external_value(PARAM_FLOAT, 'average words per sentence'),
                'longest' => new external_value(PARAM_INT, 'words in the longest sentence'),
                'fkgrade' => new external_value(PARAM_FLOAT, 'Flesch-Kincaid grade'),
            ]),
            'levels' => new external_multiple_structure(new external_single_structure([
                'index' => new external_value(PARAM_INT, 'level index, from 0'),
                'number' => new external_value(PARAM_INT, 'level number, from 1'),
                'name' => new external_value(PARAM_TEXT, 'level name'),
                'label' => new external_value(PARAM_TEXT, 'short label, e.g. Level 3 (A2)'),
                'cefr' => new external_value(PARAM_TEXT, 'CEFR level, may be empty'),
                'avgsentence' => new external_value(PARAM_INT, 'target average sentence, 0 if none'),
                'maxsentence' => new external_value(PARAM_INT, 'sentence ceiling, 0 if none'),
                'coverage' => new external_value(PARAM_FLOAT, 'coverage in percent, rounded down to 1 decimal place'),
                'covered' => new external_value(PARAM_BOOL, 'is the passage at this level'),
            ])),
            'currentlevel' => new external_value(PARAM_INT, 'lowest level reached, or one more than the top level'),
            'currentlevellabel' => new external_value(PARAM_TEXT, 'label of the current level'),
        ]);
    }

    /**
     * Parameters for simplify_text.
     *
     * @return external_function_parameters
     */
    public static function simplify_text_parameters() {
        return new external_function_parameters([
            'ogteid' => new external_value(PARAM_INT, 'id of the ogte activity'),
            'listid' => new external_value(PARAM_INT, 'id of the word list'),
            'level' => new external_value(PARAM_INT, 'target level index, from 0'),
            'original' => new external_value(PARAM_RAW, 'the passage the user pasted'),
            'current' => new external_value(PARAM_RAW, 'the last attempt to repair, or empty for the first rewrite',
                VALUE_DEFAULT, ''),
            'glossary' => new external_multiple_structure(self::glossary_structure(), 'the last attempt\'s glossary',
                VALUE_DEFAULT, []),
        ]);
    }

    /**
     * One glossary entry.
     *
     * @return external_single_structure
     */
    protected static function glossary_structure() {
        return new external_single_structure([
            'word' => new external_value(PARAM_TEXT, 'the kept word'),
            'explanation' => new external_value(PARAM_TEXT, 'what it means'),
        ]);
    }

    /**
     * Simplify a passage to a level, or repair the last attempt, with one AI call, and check the result.
     *
     * @param int $ogteid
     * @param int $listid
     * @param int $level
     * @param string $original
     * @param string $current
     * @param array $glossary
     * @return array
     */
    public static function simplify_text($ogteid, $listid, $level, $original, $current = '', $glossary = []) {
        $params = self::validate_parameters(self::simplify_text_parameters(), ['ogteid' => $ogteid,
            'listid' => $listid, 'level' => $level, 'original' => $original, 'current' => $current,
            'glossary' => $glossary]);
        $courseid = self::activity_context($params['ogteid'], 'mod/ogte:useai');
        $rewriter = rewriter::for_course($params['listid'], $courseid);
        return $rewriter->simplify(self::ai_generator(), $params['original'], $params['level'], $params['current'],
            $params['glossary']);
    }

    /**
     * Return structure for simplify_text.
     *
     * @return external_single_structure
     */
    public static function simplify_text_returns() {
        return new external_single_structure([
            'text' => new external_value(PARAM_RAW, 'the rewritten passage'),
            'html' => new external_value(PARAM_RAW, 'the rewritten passage as HTML, glossary words marked'),
            'glossary' => new external_multiple_structure(self::glossary_structure()),
            'glossarymax' => new external_value(PARAM_INT, 'the most glossary words allowed'),
            'ignores' => new external_multiple_structure(new external_value(PARAM_RAW, 'a word'),
                'names and glossary words, for the Text Profiler to ignore'),
            'met' => new external_value(PARAM_BOOL, 'are the coverage and sentence targets met'),
            'score' => new external_value(PARAM_FLOAT, 'for choosing the best pass: higher is better'),
            'title' => new external_value(PARAM_TEXT, 'e.g. Simplified from Level 7 to Level 3 (A2)'),
            'levellabel' => new external_value(PARAM_TEXT, 'the target level label'),
            'rows' => new external_multiple_structure(new external_single_structure([
                'measure' => new external_value(PARAM_TEXT, 'what is measured'),
                'original' => new external_value(PARAM_TEXT, 'the original figure'),
                'rewritten' => new external_value(PARAM_TEXT, 'the rewritten figure'),
                'target' => new external_value(PARAM_TEXT, 'the target'),
                'status' => new external_value(PARAM_ALPHA, 'met, notmet, note or ref'),
                'statuslabel' => new external_value(PARAM_TEXT, 'the result, in words'),
                'ismet' => new external_value(PARAM_BOOL, 'target met'),
                'isnotmet' => new external_value(PARAM_BOOL, 'target not met'),
            ])),
            'notes' => new external_multiple_structure(new external_single_structure([
                'text' => new external_value(PARAM_TEXT, 'a note'),
            ])),
        ]);
    }
}
