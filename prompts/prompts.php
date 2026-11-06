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
 * Lists the OGTE AI prompts, with links to edit them.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use mod_ogte\constants;
use mod_ogte\local\ai\prompts;
use mod_ogte\local\ai\generator;

// Checks login and mod/ogte:manageprompts.
admin_externalpage_setup('modogteprompts');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('aiprompts', constants::M_COMPONENT));
echo html_writer::tag('p', get_string('aiprompts_desc', constants::M_COMPONENT));

if (!generator::is_available()) {
    $settingsurl = new moodle_url('/admin/settings.php', ['section' => 'modsettingogte']);
    echo $OUTPUT->notification(get_string('noaiprovider_admin', constants::M_COMPONENT, $settingsurl->out()), 'warning');
}

$table = new html_table();
$table->head = [
    get_string('prompt', constants::M_COMPONENT),
    get_string('description'),
    get_string('status'),
    get_string('lastmodified'),
    get_string('actions'),
];
$table->attributes['class'] = 'generaltable mod_ogte_promptstable';

foreach (prompts::get_all() as $name => $record) {
    $editurl = new moodle_url(constants::M_URL . '/prompts/editprompt.php', ['name' => $name]);
    $modified = prompts::is_modified($record);
    $table->data[] = [
        html_writer::tag('code', s($name)),
        get_string('prompt_' . $name, constants::M_COMPONENT),
        $modified ? get_string('promptmodified', constants::M_COMPONENT) : get_string('promptdefault', constants::M_COMPONENT),
        $record->timemodified && $modified ? userdate($record->timemodified) : '-',
        html_writer::link($editurl, get_string('edit')),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
