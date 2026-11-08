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

    if ($oldversion < 2026110600) {
        // The site wide AI prompts, replacing the prompts that lived on Cloud Poodll.
        $table = new xmldb_table('ogte_prompts');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('systemprompt', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('prompttext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('maxtokens', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('effort', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('temperature', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_index('name', XMLDB_INDEX_UNIQUE, ['name']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        \mod_ogte\local\ai\prompts::install_defaults();

        // The Cloud Poodll credentials are no longer used.
        unset_config('apiuser', 'mod_ogte');
        unset_config('apisecret', 'mod_ogte');

        upgrade_mod_savepoint(true, 2026110600, 'ogte');
    }

    if ($oldversion < 2026110601) {
        // The new Text Rewriter replaces the five level rewrite prompts.
        $DB->delete_records_list('ogte_prompts', 'name', ['rewrite_article', 'rewrite_level_1', 'rewrite_level_2',
            'rewrite_level_3', 'rewrite_level_4', 'rewrite_level_5']);
        \mod_ogte\local\ai\prompts::install_defaults();

        // Its level targets, for the NGSL 1.2 list.
        \mod_ogte\local\rewriter::add_ngsl_targets();

        upgrade_mod_savepoint(true, 2026110601, 'ogte');
    }

    if ($oldversion < 2026110800) {
        // The rewriter prompts take the level's targets as placeholders. Prompts still holding the
        // old default text get the new one; edited prompts are kept.
        \mod_ogte\local\ai\prompts::update_default('simplify_to_level', ['9ec2b3d643b946dcdcb72d700412e292ca0fa611']);
        \mod_ogte\local\ai\prompts::update_default('revise_to_level', ['bb3e6ff69cc55d87bf0a44cbd21805d6d36419e2']);

        upgrade_mod_savepoint(true, 2026110800, 'ogte');
    }


    return true;
}
