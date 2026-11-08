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

use mod_ogte\constants;
use mod_ogte\local\rewriter;

/**
 * Preview a prompt as it would be sent, for a sample text and a word list level. No AI call is made.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preview {
    /**
     * Fill a prompt, edited or not, with the values it would get for a sample text.
     *
     * The rewriter prompts get the values the rewriter would give them: for revise_to_level the
     * sample is treated as a first rewrite that still has problems at the level.
     *
     * @param string $name Prompt name.
     * @param string $systemprompt
     * @param string $prompttext
     * @param int $listid The word list.
     * @param int $level The level index, from 0.
     * @param string $sample The sample text, or topic for generate_article.
     * @return array systemprompt, userprompt, jsonmode, values (name, value, used, empty, description)
     *     and unknown (placeholders in the text that this prompt does not have).
     */
    public static function fill(
        string $name,
        string $systemprompt,
        string $prompttext,
        int $listid,
        int $level,
        string $sample
    ): array {
        global $DB;
        $def = prompts::definition($name);
        $list = $DB->get_record(constants::M_LISTSTABLE, ['id' => $listid, 'ispropernouns' => 0], '*', MUST_EXIST);

        switch ($name) {
            case 'simplify_to_level':
                $vars = (new rewriter($list))->prompt_vars($sample, $level)['vars'];
                break;
            case 'revise_to_level':
                $vars = (new rewriter($list))->prompt_vars($sample, $level, $sample)['vars'];
                break;
            case 'generate_article':
                $vars = ['language' => generator::language_name($list->lang), 'topic' => $sample];
                break;
            default:
                $vars = ['language' => generator::language_name($list->lang), 'passage' => $sample];
        }

        $values = [];
        foreach ($def['placeholders'] as $placeholder) {
            $value = trim((string) ($vars[$placeholder] ?? ''));
            $stringid = 'placeholder_' . $placeholder;
            $values[] = [
                'name' => $placeholder,
                'value' => $value,
                'used' => strpos($prompttext, '{' . $placeholder . '}') !== false,
                'empty' => $value === '',
                'description' => get_string_manager()->string_exists($stringid, constants::M_COMPONENT)
                    ? get_string($stringid, constants::M_COMPONENT) : '',
            ];
        }

        // Placeholder-like words this prompt does not fill: likely typos. JSON examples such as
        // {"article": ...} do not match.
        preg_match_all('/\{([a-z]+)\}/', $prompttext, $matches);
        $unknown = array_values(array_unique(array_diff($matches[1], $def['placeholders'])));

        return [
            'systemprompt' => $systemprompt,
            'userprompt' => prompts::fill($name, $prompttext, $vars),
            'jsonmode' => generator::wants_json($prompttext),
            'values' => $values,
            'unknown' => $unknown,
        ];
    }
}
