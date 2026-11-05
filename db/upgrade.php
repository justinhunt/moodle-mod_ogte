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
 * Upgrade script
 *
 * @package mod_ogte
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2021 Tengku Alauddin - din@pukunui.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/ogte/lib.php');

use \mod_ogte\constants;

function xmldb_ogte_upgrade($oldversion=0) {
    global $DB;

    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    if($oldversion < 2023112302){
        // fields to change the notnull definition for] viewstart and viewend
        $table = new xmldb_table(constants::M_ENTRIESTABLE);
        $fields=[];
        $fields[] = new xmldb_field('title', XMLDB_TYPE_CHAR, 255, null,XMLDB_NOTNULL, null, 'untitled');
        $fields[] = new xmldb_field('jsonrating', XMLDB_TYPE_TEXT, null,null, null, null, null);


        // Alter fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }


        upgrade_mod_savepoint(true, 2023112302, 'ogte');
    }

    if($oldversion < 2023112500){
        // fields to change the notnull definition for] viewstart and viewend
        $table = new xmldb_table(constants::M_ENTRIESTABLE);
        $fields=[];

        $fields[] = new xmldb_field('ignores', XMLDB_TYPE_TEXT, null,null, null, null, null);
        $fields[] = new xmldb_field('levelid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $fields[] = new xmldb_field('listid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);

        // Alter fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }


        upgrade_mod_savepoint(true, 2023112500, 'ogte');
    }

    if($oldversion < 2024022500){
        //Adding a proper nouns flag (proper nouns are not shown in lists dropdowns, used internally)
        $table = new xmldb_table(constants::M_LISTSTABLE);
        $fields=[];
        $fields[] = new xmldb_field('ispropernouns', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0);

        // Alter fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2024022500, 'ogte');
    }

    if($oldversion < 2024060800){
        // Adding courseid (if 0 it's a site list)
        $table = new xmldb_table(constants::M_LISTSTABLE);
        $fields=[];
        $fields[] = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);

        // Alter fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }


        upgrade_mod_savepoint(true, 2024060800, 'ogte');
    }

    if($oldversion < 2024070500){
        //Adding a proper nouns flag (proper nouns are not shown in lists dropdowns, used internally)
        $table = new xmldb_table(constants::M_LISTSTABLE);
        $fields=[];
        $fields[] = new xmldb_field('hasmultiwordterms', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0);

        // Alter fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2024070500, 'ogte');
    }


    return true;
}
