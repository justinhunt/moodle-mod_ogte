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
 * Settings page
 *
 * @package mod_ogte
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2021 Tengku Alauddin - din@pukunui.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

use mod_ogte\constants;
use mod_ogte\local\ai\provider_client;

// OGTE has a settings page and a prompts page, so it gets its own folder.
$modogtefolder = new admin_category(
    'modogtefolder',
    new lang_string('pluginname', constants::M_COMPONENT),
    $module->is_enabled() === false
);
$ADMIN->add('modsettings', $modogtefolder);
$settings->visiblename = new lang_string('settings');
$ADMIN->add('modogtefolder', $settings);

if ($ADMIN->fulltree) {
    $promptsurl = new moodle_url(constants::M_URL . '/prompts/prompts.php');
    $airesettingsurl = new moodle_url('/admin/settings.php', ['section' => 'aiprovider']);

    $settings->add(new admin_setting_heading(
        constants::M_COMPONENT . '/aiheading',
        get_string('aisettings', constants::M_COMPONENT),
        get_string(
            'aisettings_desc',
            constants::M_COMPONENT,
            ['providers' => $airesettingsurl->out(), 'prompts' => $promptsurl->out()]
        )
    ));

    $provideroptions = ['' => get_string('noaiproviderchosen', constants::M_COMPONENT)]
        + provider_client::provider_options();
    $settings->add(new admin_setting_configselect(
        constants::M_COMPONENT . '/aiprovider',
        get_string('aiprovider', constants::M_COMPONENT),
        get_string('aiprovider_desc', constants::M_COMPONENT),
        '',
        $provideroptions
    ));

    $settings->add(new admin_setting_configtext(
        constants::M_COMPONENT . '/aimodel',
        get_string('aimodel', constants::M_COMPONENT),
        get_string('aimodel_desc', constants::M_COMPONENT),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configselect(
        'ogte/showrecentactivity',
        get_string('showrecentactivity', 'ogte'),
        get_string('showrecentactivity', 'ogte'),
        0,
        ['0' => get_string('no'), '1' => get_string('yes')]
    ));

    $settings->add(new admin_setting_configselect(
        'ogte/overview',
        get_string('showoverview', 'ogte'),
        get_string('showoverview', 'ogte'),
        1,
        ['0' => get_string('no'), '1' => get_string('yes')]
    ));
}

$ADMIN->add('modogtefolder', new admin_externalpage(
    'modogteprompts',
    new lang_string('aiprompts', constants::M_COMPONENT),
    new moodle_url(constants::M_URL . '/prompts/prompts.php'),
    'mod/ogte:manageprompts',
    $module->is_enabled() === false
));

// Tell core we already added the settings structure.
$settings = null;
