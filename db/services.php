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
 * Service file
 *
 * @package mod_ogte
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2021 Tengku Alauddin - din@pukunui.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'mod_ogte_get_entry' => array(
        'classname'   => 'mod_ogte_external',
        'methodname'  => 'get_entry',
        'classpath'   => 'mod/ogte/externallib.php',
        'description' => 'Gets the user\'s ogte.',
        'type'        => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_ogte_set_text' => array(
        'classname'   => 'mod_ogte_external',
        'methodname'  => 'set_text',
        'classpath'   => 'mod/ogte/externallib.php',
        'description' => 'Sets the ogte text.',
        'type'        => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_ogte_get_coverage' => array(
        'classname'   => 'mod_ogte_external',
        'methodname'  => 'get_coverage',
        'description' => 'see how a passage is covered by a list/level' ,
        'classpath'   => 'mod/ogte/externallib.php',
        'capabilities' => 'mod/ogte:use',
        'type'        => 'read',
        'ajax'        => true,
    ),
    'mod_ogte_call_ai' => array(
        'classname'   => 'mod_ogte_external',
        'methodname'  => 'call_ai',
        'classpath'   => 'mod/ogte/externallib.php',
        'description' => 'Write, rewrite or correct a passage with the site AI provider.',
        'capabilities' => 'mod/ogte:useai',
        'type'        => 'read',
        'ajax'        => true,
        // A call can take a minute, so do not hold the session lock while it runs.
        'readonlysession' => true,
    ),
    'mod_ogte_analyse_text' => array(
        'classname'   => 'mod_ogte_external',
        'methodname'  => 'analyse_text',
        'classpath'   => 'mod/ogte/externallib.php',
        'description' => 'Text Rewriter: coverage of a passage at each level of a list, and its sentence figures.',
        'capabilities' => 'mod/ogte:use',
        'type'        => 'read',
        'ajax'        => true,
    ),
    'mod_ogte_simplify_text' => array(
        'classname'   => 'mod_ogte_external',
        'methodname'  => 'simplify_text',
        'classpath'   => 'mod/ogte/externallib.php',
        'description' => 'Text Rewriter: simplify a passage to a level, or repair the last attempt, and check it.',
        'capabilities' => 'mod/ogte:useai',
        'type'        => 'read',
        'ajax'        => true,
        'readonlysession' => true,
    ),
);