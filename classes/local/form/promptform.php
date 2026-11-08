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

namespace mod_ogte\local\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use mod_ogte\constants;
use mod_ogte\local\ai\prompts;

/**
 * Form for editing one AI prompt.
 *
 * Custom data: name, the prompt's key.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class promptform extends \moodleform {
    /**
     * Define the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $name = $this->_customdata['name'];
        $def = prompts::definition($name);

        $mform->addElement('hidden', 'name', $name);
        $mform->setType('name', PARAM_ALPHANUMEXT);

        $mform->addElement(
            'static',
            'promptdescription',
            get_string('prompt', constants::M_COMPONENT),
            get_string('prompt_' . $name, constants::M_COMPONENT)
        );

        if (!$def['fragment']) {
            $mform->addElement(
                'textarea',
                'systemprompt',
                get_string('systemprompt', constants::M_COMPONENT),
                ['rows' => 3, 'cols' => 80]
            );
            $mform->setType('systemprompt', PARAM_RAW);
            $mform->addHelpButton('systemprompt', 'systemprompt', constants::M_COMPONENT);
        }

        $mform->addElement(
            'textarea',
            'prompttext',
            get_string('prompttext', constants::M_COMPONENT),
            ['rows' => $def['fragment'] ? 4 : 14, 'cols' => 80]
        );
        $mform->setType('prompttext', PARAM_RAW);
        $mform->addRule('prompttext', get_string('required'), 'required', null, 'client');

        if ($def['placeholders']) {
            $placeholders = array_map(function ($placeholder) use ($def) {
                $label = \html_writer::tag('code', '{' . $placeholder . '}');
                if (in_array($placeholder, $def['required'])) {
                    $label .= ' (' . get_string('required') . ')';
                }
                $stringid = 'placeholder_' . $placeholder;
                if (get_string_manager()->string_exists($stringid, constants::M_COMPONENT)) {
                    $label .= ': ' . get_string($stringid, constants::M_COMPONENT);
                }
                return $label;
            }, $def['placeholders']);
            $mform->addElement(
                'static',
                'placeholders',
                get_string('placeholders', constants::M_COMPONENT),
                \html_writer::alist($placeholders, ['class' => 'mb-1'])
                    . get_string('placeholders_desc', constants::M_COMPONENT)
            );
        }

        if (!$def['fragment']) {
            $mform->addElement('text', 'maxtokens', get_string('maxtokens', constants::M_COMPONENT), ['size' => 8]);
            $mform->setType('maxtokens', PARAM_INT);
            $mform->addHelpButton('maxtokens', 'maxtokens', constants::M_COMPONENT);

            $efforts = [];
            foreach (prompts::EFFORTS as $effort) {
                $efforts[$effort] = get_string('effort_' . ($effort === '' ? 'default' : $effort), constants::M_COMPONENT);
            }
            $mform->addElement('select', 'effort', get_string('effort', constants::M_COMPONENT), $efforts);
            $mform->addHelpButton('effort', 'effort', constants::M_COMPONENT);

            $mform->addElement('text', 'temperature', get_string('temperature', constants::M_COMPONENT), ['size' => 5]);
            $mform->setType('temperature', PARAM_RAW_TRIMMED);
            $mform->addHelpButton('temperature', 'temperature', constants::M_COMPONENT);
        }

        $buttons = [];
        $buttons[] = $mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        $buttons[] = $mform->createElement('submit', 'resetbutton', get_string('resettodefault', constants::M_COMPONENT));
        $buttons[] = $mform->createElement('cancel');
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Validate the form.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['resetbutton'])) {
            return $errors;
        }
        $missing = prompts::missing_placeholders($data['name'], $data['prompttext']);
        if ($missing) {
            $errors['prompttext'] = get_string(
                'missingplaceholders',
                constants::M_COMPONENT,
                '{' . implode('}, {', $missing) . '}'
            );
        }
        if (isset($data['maxtokens']) && $data['maxtokens'] < 0) {
            $errors['maxtokens'] = get_string('invalidnumber', constants::M_COMPONENT);
        }
        if (
            isset($data['temperature']) && $data['temperature'] !== ''
                && (!is_numeric($data['temperature']) || $data['temperature'] < 0 || $data['temperature'] > 2)
        ) {
            $errors['temperature'] = get_string('invalidtemperature', constants::M_COMPONENT);
        }
        return $errors;
    }
}
