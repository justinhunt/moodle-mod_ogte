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

namespace mod_ogte\local;

use mod_ogte\constants;
use mod_ogte\local\ai\generator;

/**
 * The Text Generator: write a passage at a word list level with the AI, then check it with the
 * profiler and repair it as the Text Rewriter does.
 *
 * Only lists whose levels all have sentence targets can be used, because the targets are what
 * the prompt asks for and what the check measures.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_generator extends rewriter {
    /** @var int[] The lengths the length slider offers, in words. */
    const LENGTHS = [300, 600, 900, 1200];

    /** @var int The shortest passage that can be asked for, in words. */
    const MIN_WORDS = 50;

    /** @var int The longest passage that can be asked for, in words. */
    const MAX_WORDS = 1500;

    /** @var int How far, in percent, the length may be from the target and still count as met. */
    const LENGTH_TOLERANCE = 20;

    /** @var int The longest content description, in characters. */
    const MAX_TOPIC = 1000;

    /** @var int The longest list of words to include, in characters. */
    const MAX_KEYWORDS = 200;

    /** @var string[] The kinds of text. */
    const TEXTTYPES = ['fiction', 'nonfiction'];

    /**
     * The generator for a list that an activity's course may use.
     *
     * @param int $listid
     * @param int $courseid
     * @return self
     */
    public static function for_course(int $listid, int $courseid): self {
        global $DB;
        $list = $DB->get_record(constants::M_LISTSTABLE, ['id' => $listid, 'ispropernouns' => 0]);
        if (!$list || ($list->courseid != 0 && $list->courseid != $courseid)) {
            throw new \invalid_parameter_exception('This word list cannot be used here');
        }
        $generator = new self($list);
        if (!$generator->has_targets()) {
            throw new \invalid_parameter_exception('This word list has no level targets');
        }
        return $generator;
    }

    /**
     * Does every level have its sentence targets?
     *
     * @return bool
     */
    public function has_targets(): bool {
        foreach ($this->levels as $level) {
            if (!$level['avgsentence'] || !$level['maxsentence']) {
                return false;
            }
        }
        return true;
    }

    /**
     * The lists a course can generate texts for, with their levels, for the generator form.
     *
     * @param int $courseid
     * @return array Each has key, label and levels. Each level has index, label, range, cefr,
     *     avgsentence, maxsentence and summary.
     */
    public static function list_options(int $courseid): array {
        global $DB;
        $options = [];
        $lists = $DB->get_records_select(
            constants::M_LISTSTABLE,
            'ispropernouns = 0 AND (courseid = 0 OR courseid = ?)',
            [$courseid],
            'id'
        );
        foreach ($lists as $list) {
            try {
                $generator = new self($list);
            } catch (\invalid_parameter_exception $e) {
                // A list with no levels.
                continue;
            }
            if ($generator->has_targets()) {
                $options[] = ['key' => (int) $list->id, 'label' => $list->name, 'levels' => $generator->level_options()];
            }
        }
        return $options;
    }

    /**
     * What the generator form template needs.
     *
     * @param int $courseid
     * @return array generatorlists, generatorlistsjson, generatorlengths, generatorminwords,
     *     generatormaxwords, generatormaxtopic, generatormaxkeywords and generatorcoverage.
     */
    public static function template_context(int $courseid): array {
        $lists = self::list_options($courseid);
        $lengthnames = ['gen_lengthshort', 'gen_lengthmedium', 'gen_lengthlong', 'gen_lengthextralong'];
        $lengths = [];
        foreach (self::LENGTHS as $stop => $words) {
            $lengths[] = ['stop' => $stop, 'words' => $words,
                'label' => get_string($lengthnames[$stop], constants::M_COMPONENT)];
        }
        return [
            'generatorlists' => $lists,
            'generatorlistsjson' => json_encode($lists),
            'generatorlengths' => $lengths,
            'generatorlengthsjson' => json_encode(self::LENGTHS),
            'generatorlaststop' => count(self::LENGTHS) - 1,
            'generatorminwords' => self::MIN_WORDS,
            'generatormaxwords' => self::MAX_WORDS,
            'generatormaxtopic' => self::MAX_TOPIC,
            'generatormaxkeywords' => self::MAX_KEYWORDS,
            'generatorcoverage' => self::COVERED,
        ];
    }

    /**
     * The levels for the generator form.
     *
     * @return array
     */
    public function level_options(): array {
        $options = [];
        foreach ($this->levels as $level) {
            $label = $this->level_label($level['number']);
            $options[] = [
                'index' => $level['index'],
                'label' => get_string('rw_levelnumber', constants::M_COMPONENT, $level['number']),
                'range' => get_string('gen_levelrange', constants::M_COMPONENT, number_format($level['top'])),
                'cefr' => $level['cefr'],
                'avgsentence' => $level['avgsentence'],
                'maxsentence' => $level['maxsentence'],
                'summary' => get_string('gen_levelsummary', constants::M_COMPONENT, ['level' => $label,
                    'avg' => $level['avgsentence'], 'max' => $level['maxsentence']]),
            ];
        }
        return $options;
    }

    /**
     * Check the teacher's settings.
     *
     * @param int $index Level index.
     * @param string $texttype fiction or nonfiction.
     * @param int $words Target length.
     * @param string $topic The content description.
     * @param string $keywords Words to include.
     */
    protected function check_settings(int $index, string $texttype, int $words, string $topic, string $keywords): void {
        if (!isset($this->levels[$index])) {
            throw new \invalid_parameter_exception('Unknown level');
        }
        if (!in_array($texttype, self::TEXTTYPES)) {
            throw new \invalid_parameter_exception('Unknown text type');
        }
        if ($words < self::MIN_WORDS || $words > self::MAX_WORDS) {
            throw new \moodle_exception(
                'gen_lengthrange',
                constants::M_COMPONENT,
                '',
                ['min' => self::MIN_WORDS, 'max' => self::MAX_WORDS]
            );
        }
        if (trim($topic) === '') {
            throw new \moodle_exception('entersomething', constants::M_COMPONENT);
        }
        if (\core_text::strlen($topic) > self::MAX_TOPIC) {
            throw new \moodle_exception('aitexttoolong', constants::M_COMPONENT, '', self::MAX_TOPIC);
        }
        if (\core_text::strlen($keywords) > self::MAX_KEYWORDS) {
            throw new \moodle_exception('aitexttoolong', constants::M_COMPONENT, '', self::MAX_KEYWORDS);
        }
    }

    /**
     * The words to include, as a tidy comma separated list.
     *
     * @param string $keywords As typed: separated by commas, semicolons or new lines.
     * @return string
     */
    public static function clean_keywords(string $keywords): string {
        $words = array_map('trim', preg_split('/[,;\r\n]+/', strip_tags($keywords)));
        $words = array_unique(array_filter($words, fn($word) => $word !== ''));
        return implode(', ', $words);
    }

    /**
     * The prompt for one pass and its placeholder values: generate_article for the first
     * attempt, or revise_to_level to repair the last one.
     *
     * @param int $index Level index.
     * @param string $texttype fiction or nonfiction.
     * @param int $words Target length.
     * @param string $topic The content description.
     * @param string $keywords Words to include.
     * @param string $current The last attempt, or empty for the first.
     * @param array $glossary The last attempt's glossary.
     * @return array name, vars, and last (the check of the last attempt, or null).
     */
    public function generate_vars(
        int $index,
        string $texttype,
        int $words,
        string $topic,
        string $keywords,
        string $current = '',
        array $glossary = []
    ): array {
        $this->check_settings($index, $texttype, $words, $topic, $keywords);
        if (trim($current) !== '') {
            return $this->repair_vars($current, $index, $glossary);
        }

        $level = $this->levels[$index];
        $keywords = self::clean_keywords($keywords);
        $fiction = $texttype === 'fiction';
        return [
            'name' => 'generate_article',
            'last' => null,
            'vars' => $this->level_vars($level) + [
                'topic' => $topic,
                'keywords' => $keywords,
                'texttype' => $fiction ? 'fiction' : 'non-fiction',
                'texttyperule' => $fiction
                    ? 'Write a story with a clear beginning, middle and end. Simple dialogue is fine.'
                    : 'Write a factual text. Keep the facts accurate, and do not make up facts, quotations or '
                        . 'statistics.',
                'keywordrule' => $keywords === ''
                    ? 'The teacher gave no words to include.'
                    : 'Use each of the teacher\'s words to include at least once. If one is too hard for this level, '
                        . 'explain it briefly in the passage and list it in the glossary: it counts towards those '
                        . 'key words.',
                'targetwords' => $words,
                'lengthrule' => 'Write about ' . $words . ' words: between ' . self::length_floor($words) . ' and '
                    . self::length_ceiling($words) . '.',
                'sentencerule' => self::sentence_rule($level, false),
                'glossarymax' => self::glossary_max($words),
            ],
        ];
    }

    /**
     * Write a passage at a level, or repair an earlier attempt, with one AI call.
     *
     * @param generator $ai
     * @param int $index Level index.
     * @param string $texttype fiction or nonfiction.
     * @param int $words Target length.
     * @param string $topic The content description.
     * @param string $keywords Words to include.
     * @param string $current The last attempt, or empty for the first.
     * @param array $glossary The last attempt's glossary.
     * @return array The result for the browser. See \mod_ogte_external::generate_text_returns().
     */
    public function generate(
        generator $ai,
        int $index,
        string $texttype,
        int $words,
        string $topic,
        string $keywords,
        string $current = '',
        array $glossary = []
    ): array {
        $prompt = $this->generate_vars($index, $texttype, $words, $topic, $keywords, $current, $glossary);
        if ($prompt['last'] && $prompt['last']['met']) {
            // Nothing to repair.
            return $this->generated_result($prompt['last'], $index, $words, '');
        }
        $reply = $ai->complete($prompt['name'], $prompt['vars']);
        [$text, $newglossary] = self::parse_reply($reply);
        $decoded = generator::decode_reply($reply);
        $title = is_array($decoded) && isset($decoded['title']) && is_string($decoded['title'])
            ? \core_text::substr(trim(strip_tags($decoded['title'])), 0, 200) : '';
        return $this->generated_result($this->check($text, $index, $newglossary), $index, $words, $title);
    }

    /**
     * The fewest words that meet a target length.
     *
     * @param int $words
     * @return int
     */
    protected static function length_floor(int $words): int {
        return (int) round($words * (100 - self::LENGTH_TOLERANCE) / 100);
    }

    /**
     * The most words that meet a target length.
     *
     * @param int $words
     * @return int
     */
    protected static function length_ceiling(int $words): int {
        return (int) round($words * (100 + self::LENGTH_TOLERANCE) / 100);
    }

    /**
     * The result of one pass, for the browser.
     *
     * The length is reported but not repaired: met means the coverage and sentence targets.
     *
     * @param array $check From check().
     * @param int $index Level index.
     * @param int $words Target length.
     * @param string $title The title the AI gave, or empty (repairs do not give one).
     * @return array
     */
    protected function generated_result(array $check, int $index, int $words, string $title): array {
        $level = $this->levels[$index];
        $stats = $check['stats'];
        $coverageok = $check['coverage'] >= self::COVERED;
        $lengthok = $stats->words >= self::length_floor($words) && $stats->words <= self::length_ceiling($words);
        $over = count($check['sentences']['over']);
        $str = fn($key, $a = null) => get_string($key, constants::M_COMPONENT, $a);
        $status = fn(bool $met) => $met ? 'met' : 'notmet';

        $notes = [];
        if (!$coverageok) {
            $notes[] = $str('gen_note_coverage', self::display_coverage($check['coverage']));
        }
        if ($over) {
            $notes[] = $str('rw_note_sentences', ['count' => $over, 'ceiling' => $level['maxsentence'],
                'allowed' => $check['sentences']['allowed'], 'extra' => self::CEILING_ALLOWANCE]);
        }
        if (!$lengthok) {
            $notes[] = $str('gen_note_length', ['words' => $stats->words, 'target' => $words]);
        }

        $rows = [
            [
                'measure' => $str('rw_coverageat', $this->level_label($level['number'])),
                'value' => self::display_coverage($check['coverage']) . '%',
                'target' => $str('rw_coveragetarget', self::COVERED),
                'status' => $status($coverageok),
            ],
            [
                'measure' => $str('rw_words'),
                'value' => $stats->words,
                'target' => $str('rw_about', $words),
                'status' => $status($lengthok),
            ],
            [
                'measure' => $str('rw_avgsentence'),
                'value' => round($stats->average_sentence(), 1),
                'target' => $str('rw_about', $level['avgsentence']),
                'status' => $status($stats->average_sentence() <= $level['avgsentence'] + 1),
            ],
            [
                'measure' => $str('rw_longest'),
                'value' => $stats->longest_sentence(),
                'target' => $str('rw_ceiling', ['ceiling' => $level['maxsentence'], 'extra' => self::CEILING_ALLOWANCE]),
                'status' => $status($stats->longest_sentence() <= $level['maxsentence'] + self::CEILING_ALLOWANCE),
            ],
            [
                'measure' => $str('rw_overceiling'),
                'value' => $over,
                'target' => $str('rw_upto', $check['sentences']['allowed']),
                'status' => $status($over <= $check['sentences']['allowed']),
            ],
            [
                'measure' => $str('gen_keywords'),
                'value' => count($check['glossary']),
                'target' => $str('rw_upto', self::glossary_max($stats->words)),
                'status' => 'met',
            ],
            [
                'measure' => $str('rw_fkgrade'),
                'value' => $stats->fk_grade(),
                'target' => '—',
                'status' => 'ref',
            ],
        ];
        foreach ($rows as &$row) {
            $row['value'] = (string) $row['value'];
            $row['ismet'] = $row['status'] === 'met';
            $row['isnotmet'] = $row['status'] === 'notmet';
        }
        unset($row);

        return [
            'text' => $check['text'],
            'html' => self::highlight($check['text'], array_column($check['glossary'], 'word')),
            'title' => $title,
            'glossary' => $check['glossary'],
            'glossarymax' => self::glossary_max($stats->words),
            'ignores' => $check['ignores'],
            'met' => $check['met'],
            // The browser keeps the best pass, as the rewriter does.
            'score' => ($check['met'] ? 1000 : 0) + ($coverageok ? 500 : 0) + ($check['sentences']['ok'] ? 200 : 0)
                + $check['coverage'],
            'words' => $stats->words,
            'levellabel' => $this->level_label($level['number']),
            'rows' => $rows,
            'notes' => array_map(fn($note) => ['text' => $note], $notes),
        ];
    }
}
