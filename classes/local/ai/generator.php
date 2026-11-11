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

/**
 * The OGTE AI tools: write and correct passages, and send the rewriter's prompts.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator {
    /** @var string Correct a passage's grammar and spelling. */
    const ACTION_GRAMMAR = 'grammar_correction';

    /** @var int[] The longest input each action accepts, in characters. */
    const MAX_INPUT = [
        self::ACTION_GRAMMAR => 5000,
    ];

    /** @var provider_client */
    protected provider_client $client;

    /**
     * Constructor.
     *
     * @param provider_client $client
     */
    public function __construct(provider_client $client) {
        $this->client = $client;
    }

    /**
     * A generator for the provider set in the OGTE settings.
     *
     * @return self|null Null if no usable AI provider is set.
     */
    public static function from_settings(): ?self {
        $client = provider_client::from_settings();
        return $client ? new self($client) : null;
    }

    /**
     * Is an AI provider set up for OGTE?
     *
     * @return bool
     */
    public static function is_available(): bool {
        return provider_client::from_settings() !== null;
    }

    /**
     * Run one of the tools.
     *
     * @param string $action One of the ACTION_ constants.
     * @param string $text The passage.
     * @param string $langcode E.g. en-US.
     * @return string The passage.
     */
    public function run(string $action, string $text, string $langcode): string {
        if (!isset(self::MAX_INPUT[$action])) {
            throw new \invalid_parameter_exception('Unknown AI action: ' . $action);
        }
        $text = trim($text);
        if ($text === '') {
            throw new \moodle_exception('entersomething', constants::M_COMPONENT);
        }
        if (\core_text::strlen($text) > self::MAX_INPUT[$action]) {
            throw new \moodle_exception('aitexttoolong', constants::M_COMPONENT, '', self::MAX_INPUT[$action]);
        }

        return $this->send($action, ['language' => self::language_name($langcode), 'passage' => $text]);
    }

    /**
     * Fill a stored prompt, send it, and take the passage out of the reply.
     *
     * @param string $name Prompt name.
     * @param array $vars Placeholder values.
     * @return string
     */
    protected function send(string $name, array $vars): string {
        return self::passage_from_reply($this->complete($name, $vars));
    }

    /**
     * Fill a stored prompt, send it, and return the reply as it is.
     *
     * @param string $name Prompt name.
     * @param array $vars Placeholder values.
     * @return string
     */
    public function complete(string $name, array $vars): string {
        $prompt = prompts::get($name);
        $userprompt = prompts::fill($name, $prompt->prompttext, $vars);
        $reply = $this->client->chat((string) $prompt->systemprompt, $userprompt, [
            'maxtokens' => (int) $prompt->maxtokens,
            'effort' => $prompt->effort,
            'temperature' => $prompt->temperature,
            'jsonmode' => self::wants_json($prompt->prompttext),
        ]);
        return $reply;
    }

    /**
     * Should the reply be requested in JSON mode?
     *
     * The providers' JSON modes need the prompt to ask for JSON, and an admin may have edited
     * that out, so we only turn it on when the prompt mentions JSON.
     *
     * @param string $prompttext
     * @return bool
     */
    public static function wants_json(string $prompttext): bool {
        return stripos($prompttext, 'json') !== false;
    }

    /**
     * The passage in an AI reply.
     *
     * The default prompts ask for {"article": "..."}. A prompt edited to ask for plain text gets
     * the reply as it is.
     *
     * @param string $reply
     * @return string
     */
    public static function passage_from_reply(string $reply): string {
        $reply = trim($reply);
        $decoded = self::decode_reply($reply);
        if (is_array($decoded)) {
            if (isset($decoded['article']) && is_string($decoded['article'])) {
                return trim($decoded['article']);
            }
            // Some other shape: take the first string value.
            foreach ($decoded as $value) {
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
            throw new \moodle_exception('aicallfailed', constants::M_COMPONENT, '', null, 'reply JSON had no passage');
        }
        return $reply;
    }

    /**
     * Decode a JSON reply.
     *
     * @param string $reply
     * @return array|null Null if the reply is not a JSON object.
     */
    public static function decode_reply(string $reply): ?array {
        // Some models wrap JSON in a markdown code fence even in JSON mode.
        $unfenced = preg_replace('/^\x60{3}(?:json)?\s*(.*?)\s*\x60{3}$/s', '$1', trim($reply));
        $decoded = json_decode($unfenced, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * The English name of a language, for the prompts.
     *
     * @param string $langcode E.g. en-US or fil-PH.
     * @return string E.g. English. The code itself if the language is not known.
     */
    public static function language_name(string $langcode): string {
        // Names the ISO list does not have, or that the TTS style codes mean differently.
        $special = [
            'fil' => 'Filipino',
            'en-WL' => 'English',
            'en-AB' => 'English',
            'ar-AE' => 'Arabic',
            'ar-SA' => 'Arabic',
        ];
        if (isset($special[$langcode])) {
            return $special[$langcode];
        }
        $iso = strtolower(explode('-', $langcode)[0]);
        if (isset($special[$iso])) {
            return $special[$iso];
        }
        $languages = get_string_manager()->get_list_of_languages('en');
        if (isset($languages[$iso])) {
            // Names such as "Greek, Modern (1453-)" are cut at the comma or bracket.
            return trim(preg_split('/[,(;]/', $languages[$iso])[0]);
        }
        return $langcode !== '' ? $langcode : 'English';
    }
}
