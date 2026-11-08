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
 * Edit one OGTE AI prompt.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use mod_ogte\constants;
use mod_ogte\local\ai\prompts;
use mod_ogte\local\form\promptform;

$name = required_param('name', PARAM_ALPHANUMEXT);

// Checks login and mod/ogte:manageprompts.
admin_externalpage_setup('modogteprompts', '', null, new moodle_url(
    constants::M_URL . '/prompts/editprompt.php',
    ['name' => $name]
));

if (!array_key_exists($name, prompts::definitions())) {
    throw new moodle_exception('invalidprompt', constants::M_COMPONENT);
}

$returnurl = new moodle_url(constants::M_URL . '/prompts/prompts.php');
$form = new promptform($PAGE->url, ['name' => $name]);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    if (!empty($data->resetbutton)) {
        prompts::reset($name);
        redirect(
            $PAGE->url,
            get_string('promptreset', constants::M_COMPONENT),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    prompts::save($name, $data);
    redirect(
        $returnurl,
        get_string('promptsaved', constants::M_COMPONENT),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$record = prompts::get($name);
$form->set_data($record);

$PAGE->navbar->add(s($name));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editprompt', constants::M_COMPONENT, s($name)));
$form->display();

// The preview fills the prompt being edited, for a sample text and a word list level.
$listoptions = \mod_ogte\utils::get_list_options(SITEID);
if ($listoptions) {
    $istopic = $name === 'generate_article';
    echo $OUTPUT->render_from_template('mod_ogte/promptpreview', [
        'name' => $name,
        'listoptions' => $listoptions,
        'levelsjson' => json_encode(\mod_ogte\utils::get_level_options(SITEID)),
        'sample' => get_string($istopic ? 'promptpreview_sampletopic' : 'promptpreview_sampletext', constants::M_COMPONENT),
        'istopic' => $istopic,
    ]);
}
echo $OUTPUT->footer();
