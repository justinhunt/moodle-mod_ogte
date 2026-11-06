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

namespace mod_ogte\local\ai;

/**
 * The site wide AI prompts: their defaults, storage and placeholder filling.
 *
 * The defaults live here in code. install_defaults() copies any that are missing into the
 * ogte_prompts table, where admins can edit them. It never overwrites an edited prompt, so a
 * later version can add prompts without losing anyone's changes.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prompts {
    /** @var string The prompts table. */
    const TABLE = 'ogte_prompts';

    /** @var string[] The reasoning effort values a prompt may use. Empty means the model default. */
    const EFFORTS = ['', 'low', 'medium', 'high'];

    /** @var string Output rules shared by the passage prompts. */
    const FORMAT_RULES = "Write plain paragraphs separated by a blank line. Do not add a title, headings, lists, "
        . "markdown, or any comment before or after the passage.\n"
        . 'Return a JSON object of the form {"article": "the passage"}.';

    /** @var string System prompt shared by the passage prompts. */
    const PASSAGE_SYSTEM = 'You write graded reading passages for language learners.';

    /**
     * The default prompts.
     *
     * Each entry has:
     * - systemprompt, prompttext, maxtokens, effort, temperature: the defaults for the editable fields.
     * - placeholders: the placeholders the prompt text may use.
     * - required: the placeholders the prompt text must keep.
     * - userinput: placeholders filled with what the user typed. These are wrapped in [[ and ]] by
     *   the code, not by the prompt, so an edited prompt cannot drop that guard.
     * - fragment: true for text that is put inside another prompt rather than sent on its own. A
     *   fragment has no system prompt or AI parameters.
     *
     * @return array
     */
    public static function definitions(): array {
        $defs = [
            'generate_article' => [
                'systemprompt' => self::PASSAGE_SYSTEM,
                'prompttext' => "Write a non-fiction reading passage in {language} for language learners at about "
                    . "CEFR level B1 (intermediate). The topic is given below between [[ and ]].\n"
                    . "Write 200 to 350 words in 3 to 5 paragraphs. Use clear sentences and everyday words. "
                    . "Keep the facts accurate.\n"
                    . self::FORMAT_RULES . "\n\n{topic}",
                'maxtokens' => 8000,
                'effort' => 'medium',
                'temperature' => '',
                'placeholders' => ['topic', 'language'],
                'required' => ['topic'],
                'userinput' => ['topic'],
                'fragment' => false,
            ],
            'rewrite_article' => [
                'systemprompt' => self::PASSAGE_SYSTEM,
                'prompttext' => "Rewrite the {language} passage below, between [[ and ]], for language learners at "
                    . "this level: {leveldescription}\n"
                    . "Write it in {language}. Keep the meaning, the facts and the order of ideas. Keep about the "
                    . "same length as the original, though a simpler level may be shorter.\n"
                    . self::FORMAT_RULES . "\n\n{passage}",
                'maxtokens' => 8000,
                'effort' => 'medium',
                'temperature' => '',
                'placeholders' => ['passage', 'language', 'leveldescription'],
                'required' => ['passage', 'leveldescription'],
                'userinput' => ['passage'],
                'fragment' => false,
            ],
            'grammar_correction' => [
                'systemprompt' => 'You are a helpful assistant.',
                'prompttext' => "Correct the grammar and spelling of the {language} passage below, between [[ and ]]. "
                    . "Write in {language}. Keep the writer's meaning, and keep their wording where it is already "
                    . "correct.\n"
                    . 'Return a JSON object of the form {"article": "the corrected passage"}.' . "\n\n{passage}",
                'maxtokens' => 4000,
                'effort' => 'low',
                'temperature' => '',
                'placeholders' => ['passage', 'language'],
                'required' => ['passage'],
                'userinput' => ['passage'],
                'fragment' => false,
            ],
        ];

        // The rewrite levels. The text rewriter sends 1 to 5, with 1 the simplest.
        $levels = [
            1 => 'CEFR A1 (beginner): very short, simple sentences; only the most common words; '
                . 'the simple present, and the simple past for past events.',
            2 => 'CEFR A2 (elementary): short sentences joined with simple words such as "and", "but" '
                . 'and "because"; common everyday words; present, simple past and simple future tenses.',
            3 => 'CEFR B1 (intermediate): clear sentences, some of them compound or complex; everyday '
                . 'words plus the key words of the topic; the common tenses.',
            4 => 'CEFR B2 (upper intermediate): varied sentences, including complex ones; a wider '
                . 'vocabulary, including less common words for the topic; the full range of tenses.',
            5 => 'CEFR C1 (advanced): natural, varied and complex sentences; precise and less common '
                . 'vocabulary, and idioms where they are natural.',
        ];
        foreach ($levels as $level => $description) {
            $defs['rewrite_level_' . $level] = [
                'systemprompt' => '',
                'prompttext' => $description,
                'maxtokens' => 0,
                'effort' => '',
                'temperature' => '',
                'placeholders' => [],
                'required' => [],
                'userinput' => [],
                'fragment' => true,
            ];
        }
        return $defs;
    }

    /**
     * The default for one prompt.
     *
     * @param string $name
     * @return array
     */
    public static function definition(string $name): array {
        $defs = self::definitions();
        if (!isset($defs[$name])) {
            throw new \coding_exception('Unknown OGTE prompt: ' . $name);
        }
        return $defs[$name];
    }

    /**
     * Add any default prompts that are not in the table yet. Edited prompts are left alone.
     *
     * @return int The number of prompts added.
     */
    public static function install_defaults(): int {
        global $DB;
        $existing = $DB->get_fieldset_select(self::TABLE, 'name', '1 = 1');
        $added = 0;
        foreach (self::definitions() as $name => $def) {
            if (in_array($name, $existing)) {
                continue;
            }
            $DB->insert_record(self::TABLE, self::record_from_definition($name, $def));
            $added++;
        }
        return $added;
    }

    /**
     * A table record holding a prompt's defaults.
     *
     * @param string $name
     * @param array $def
     * @return \stdClass
     */
    protected static function record_from_definition(string $name, array $def): \stdClass {
        return (object) [
            'name' => $name,
            'systemprompt' => $def['systemprompt'],
            'prompttext' => $def['prompttext'],
            'maxtokens' => $def['maxtokens'],
            'effort' => $def['effort'],
            'temperature' => $def['temperature'],
            'usermodified' => 0,
            'timemodified' => time(),
        ];
    }

    /**
     * Get a prompt as stored, or its default if it has not been installed.
     *
     * @param string $name
     * @return \stdClass
     */
    public static function get(string $name): \stdClass {
        global $DB;
        $def = self::definition($name);
        $record = $DB->get_record(self::TABLE, ['name' => $name]);
        if (!$record) {
            $record = self::record_from_definition($name, $def);
        }
        return $record;
    }

    /**
     * All stored prompts, in definition order.
     *
     * @return \stdClass[]
     */
    public static function get_all(): array {
        $ret = [];
        foreach (array_keys(self::definitions()) as $name) {
            $ret[$name] = self::get($name);
        }
        return $ret;
    }

    /**
     * Has a prompt been changed from its default?
     *
     * @param \stdClass $record
     * @return bool
     */
    public static function is_modified(\stdClass $record): bool {
        $def = self::definition($record->name);
        foreach (['systemprompt', 'prompttext', 'maxtokens', 'effort', 'temperature'] as $field) {
            if ((string) $record->$field !== (string) $def[$field]) {
                return true;
            }
        }
        return false;
    }

    /**
     * Save an edited prompt.
     *
     * @param string $name
     * @param \stdClass $data Any of systemprompt, prompttext, maxtokens, effort, temperature.
     */
    public static function save(string $name, \stdClass $data): void {
        global $DB, $USER;
        self::definition($name);
        $record = $DB->get_record(self::TABLE, ['name' => $name]);
        if (!$record) {
            self::install_defaults();
            $record = $DB->get_record(self::TABLE, ['name' => $name], '*', MUST_EXIST);
        }
        foreach (['systemprompt', 'prompttext', 'maxtokens', 'effort', 'temperature'] as $field) {
            if (isset($data->$field)) {
                $record->$field = $data->$field;
            }
        }
        $record->usermodified = $USER->id;
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);
    }

    /**
     * Put a prompt back to its default.
     *
     * @param string $name
     */
    public static function reset(string $name): void {
        $def = self::definition($name);
        self::save($name, self::record_from_definition($name, $def));
    }

    /**
     * The required placeholders missing from a prompt text.
     *
     * @param string $name
     * @param string $prompttext
     * @return string[] Placeholder names, without braces.
     */
    public static function missing_placeholders(string $name, string $prompttext): array {
        $missing = [];
        foreach (self::definition($name)['required'] as $placeholder) {
            if (strpos($prompttext, '{' . $placeholder . '}') === false) {
                $missing[] = $placeholder;
            }
        }
        return $missing;
    }

    /**
     * Fill a prompt's placeholders.
     *
     * Only the prompt's own placeholders are replaced, so other braces, such as the JSON example
     * in the output rules, are left alone. What the user typed is wrapped in [[ and ]], with any
     * [[ or ]] of its own taken out so it cannot close the wrapper early.
     *
     * @param string $name
     * @param string $prompttext
     * @param array $vars Placeholder name => value.
     * @return string
     */
    public static function fill(string $name, string $prompttext, array $vars): string {
        $def = self::definition($name);
        $replace = [];
        foreach ($def['placeholders'] as $placeholder) {
            $value = isset($vars[$placeholder]) ? trim((string) $vars[$placeholder]) : '';
            if (in_array($placeholder, $def['userinput'])) {
                $value = '[[' . trim(str_replace(['[[', ']]'], '', $value)) . ']]';
            }
            $replace['{' . $placeholder . '}'] = $value;
        }
        return strtr($prompttext, $replace);
    }
}
