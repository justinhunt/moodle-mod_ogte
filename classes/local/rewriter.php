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
use mod_ogte\utils;

/**
 * The Text Rewriter: analyse a passage against every level of a word list, and simplify it to
 * one level with the AI, checking the result with the profiler after each pass.
 *
 * A level's targets come from its entry in the list's props JSON. Besides name, top and bottom,
 * a level may have:
 * - cefr: e.g. "A2".
 * - avgsentence: the target average sentence length, in words.
 * - maxsentence: the sentence ceiling, in words.
 * - description: what text at this level is like. It goes into the AI prompt.
 * A level without them is still simplified for vocabulary, with no sentence targets.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rewriter {
    /** @var float The coverage, in percent, at which a text is at a level. */
    const COVERED = 95.0;

    /** @var int The longest passage the rewriter takes, in characters. */
    const MAX_INPUT = 15000;

    /** @var int The most repair passes after the first rewrite. */
    const MAX_REPAIRS = 3;

    /** @var int How far a sentence may go over the ceiling, when it is one of the few allowed to. */
    const CEILING_ALLOWANCE = 4;

    /** @var int One sentence in this many may go over the ceiling. */
    const SENTENCES_PER_ALLOWANCE = 20;

    /** @var int How much shorter, in percent, a text may get for each level it drops. */
    const SHORTER_PER_LEVEL = 7;

    /** @var int The most a text may be shortened, in percent. */
    const MAX_SHORTER = 50;

    /** @var int Texts with fewer words than this are not shortened. */
    const NO_SHORTEN_UNDER = 200;

    /** @var int The most hard words named in one repair prompt. */
    const MAX_PROBLEM_WORDS = 60;

    /** @var array The NGSL 1.2 level targets, from the NGSL Text Rewriter specification. */
    const NGSL_LEVELS = [
        ['cefr' => 'A1', 'avgsentence' => 6, 'maxsentence' => 10,
            'description' => 'Very short, simple sentences. Only the most common words. Mostly the simple present, '
                . 'and the simple past for past events.'],
        ['cefr' => 'A1+', 'avgsentence' => 7, 'maxsentence' => 12,
            'description' => 'Short, simple sentences, sometimes joined with "and", "but" or "because". Very common '
                . 'everyday words. The simple present, past and future.'],
        ['cefr' => 'A2', 'avgsentence' => 8, 'maxsentence' => 14,
            'description' => 'Short, clear sentences, some joined with "and", "but", "because", "when" or "so". '
                . 'Common everyday words.'],
        ['cefr' => 'A2', 'avgsentence' => 9, 'maxsentence' => 16,
            'description' => 'Clear sentences, some with a simple second part (when, because, if, that). Common '
                . 'everyday words.'],
        ['cefr' => 'A2+', 'avgsentence' => 10, 'maxsentence' => 18,
            'description' => 'Clear sentences, some compound or complex. Everyday words, and a few common words '
                . 'for the topic.'],
        ['cefr' => 'B1', 'avgsentence' => 11, 'maxsentence' => 20,
            'description' => 'Clear, varied sentences, some compound or complex. Everyday words and the common '
                . 'words of the topic. The common tenses.'],
        ['cefr' => 'B1', 'avgsentence' => 12, 'maxsentence' => 22,
            'description' => 'Clear, varied sentences, including complex ones. Everyday words and the common words '
                . 'of the topic. The common tenses.'],
    ];

    /** @var \stdClass The word list. */
    protected \stdClass $list;

    /** @var array The list's levels, as arrays. See levels(). */
    protected array $levels = [];

    /**
     * Constructor.
     *
     * @param \stdClass $list An ogte_list record.
     */
    public function __construct(\stdClass $list) {
        $this->list = $list;
        $props = json_decode($list->props);
        if (!is_array($props) || !$props) {
            throw new \invalid_parameter_exception('The word list has no levels');
        }
        foreach (array_values($props) as $index => $level) {
            $this->levels[] = [
                'index' => $index,
                'number' => $index + 1,
                'name' => (string) ($level->name ?? ''),
                'top' => (int) ($level->top ?? 0),
                'cefr' => trim((string) ($level->cefr ?? '')),
                'avgsentence' => (int) ($level->avgsentence ?? 0),
                'maxsentence' => (int) ($level->maxsentence ?? 0),
                'description' => trim((string) ($level->description ?? '')),
            ];
        }
    }

    /**
     * Add the NGSL 1.2 level targets to the NGSL 1.2 word lists that do not have them yet.
     *
     * A level that already has any target is left alone, so an admin's edits are kept.
     *
     * @return int The number of lists changed.
     */
    public static function add_ngsl_targets(): int {
        global $DB;
        $changed = 0;
        $like = $DB->sql_like('name', ':name', false);
        $lists = $DB->get_records_select(
            constants::M_LISTSTABLE,
            $like . ' AND ispropernouns = 0',
            ['name' => 'NGSL 1.2%']
        );
        foreach ($lists as $list) {
            $levels = json_decode($list->props, true);
            if (!is_array($levels) || count($levels) !== count(self::NGSL_LEVELS)) {
                continue;
            }
            $update = false;
            foreach ($levels as $index => $level) {
                if (
                    !isset($level['cefr']) && !isset($level['avgsentence']) && !isset($level['maxsentence'])
                        && !isset($level['description'])
                ) {
                    $levels[$index] = $level + self::NGSL_LEVELS[$index];
                    $update = true;
                }
            }
            if ($update) {
                // One level per line, as the list form shows them.
                $lines = array_map(fn($level) => json_encode($level, JSON_UNESCAPED_UNICODE), $levels);
                $DB->set_field(constants::M_LISTSTABLE, 'props', '[' . implode(",\n", $lines) . ']', ['id' => $list->id]);
                $changed++;
            }
        }
        return $changed;
    }

    /**
     * The rewriter for a list that an activity's course may use.
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
        return new self($list);
    }

    /**
     * The list's levels.
     *
     * @return array Each has index (from 0), number (from 1), name, top, cefr, avgsentence,
     *     maxsentence and description. Missing targets are 0 or empty.
     */
    public function levels(): array {
        return $this->levels;
    }

    /**
     * A short label for a level, e.g. "Level 3 (A2)".
     *
     * @param int $number 1 to the number of levels, or one more for "above the top level".
     * @return string
     */
    public function level_label(int $number): string {
        if ($number > count($this->levels)) {
            return get_string('rw_abovelevel', constants::M_COMPONENT, count($this->levels));
        }
        $level = $this->levels[$number - 1];
        $label = get_string('rw_levelnumber', constants::M_COMPONENT, $number);
        return $level['cefr'] !== '' ? $label . ' (' . $level['cefr'] . ')' : $label;
    }

    /**
     * Run the profiler once, at the top level, so every word is tagged with its list rank.
     *
     * @param string $text
     * @return array The profiler's result. See utils::get_coverage().
     */
    public function profile(string $text): array {
        return utils::get_coverage($text, '', $this->list->id, count($this->levels) - 1);
    }

    /**
     * The words of a profiled passage that count towards coverage.
     *
     * The profiler only knows the names in its proper noun list. The rewriter also treats a word
     * that is not in the word list as a name if the passage capitalises it in the middle of a
     * sentence, such as "Frisch" or "Nobel". Otherwise the repair passes would ask the AI to
     * replace names, and the main facts would be lost.
     *
     * @param array $profile
     * @return array Each is [word, rank]: rank is the list rank, null for a word that is not in
     *     the list, or 0 for a proper noun, name or ignored word, which always counts as covered.
     */
    protected static function counted_words(array $profile): array {
        $worddata = $profile['worddata'] ?? [];

        // Find the names: out of list words capitalised other than at the start of a sentence.
        $names = [];
        $sentencestart = true;
        foreach ($worddata as $data) {
            $word = (string) $data['word'];
            if (
                $data['class'] === 'mod_ogte_outoflist' && !$sentencestart
                    && preg_match('/^[^\p{L}]*\p{Lu}/u', $word)
            ) {
                $names[self::clean_word($word)] = true;
            }
            if (trim($word) === '') {
                // A line break.
                $sentencestart = true;
            } else if (preg_match('/[\p{L}\p{N}]/u', $word)) {
                $sentencestart = (bool) preg_match('/[.!?:][^\p{L}\p{N}]*$/u', $word);
            }
        }

        $words = [];
        foreach ($worddata as $data) {
            $clean = self::clean_word($data['word']);
            switch ($data['class']) {
                case 'mod_ogte_inlevel':
                case 'mod_ogte_outoflevel':
                    $rank = (int) $data['data']['listrank'];
                    break;
                case 'mod_ogte_outoflist':
                    $rank = isset($names[$clean]) ? 0 : null;
                    break;
                case 'mod_ogte_propernoun':
                case 'mod_ogte_ignored':
                    $rank = 0;
                    break;
                default:
                    // Numbers, line breaks and punctuation do not count.
                    continue 2;
            }
            $words[] = [$clean, $rank];
        }
        return $words;
    }

    /**
     * A word as the profiler matches it: lower case, without punctuation.
     *
     * @param string $word
     * @return string
     */
    protected static function clean_word(string $word): string {
        return strtolower(trim(preg_replace('/[^\'a-zA-Z0-9 ]/', '', strip_tags($word)), " '"));
    }

    /**
     * Does a word match one of the glossary words? Simple endings are allowed, so "hive" covers
     * "hives".
     *
     * @param string $word A clean word.
     * @param string[] $glossarywords Clean glossary words.
     * @return bool
     */
    protected static function in_glossary(string $word, array $glossarywords): bool {
        foreach ($glossarywords as $glossaryword) {
            if (
                $word === $glossaryword ||
                    (str_starts_with($word, $glossaryword) && strlen($word) - strlen($glossaryword) <= 3)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is a word covered at a level?
     *
     * @param array $word [word, rank], from counted_words().
     * @param int $top The level's top rank.
     * @param string[] $glossarywords
     * @return bool
     */
    protected static function is_covered(array $word, int $top, array $glossarywords): bool {
        [$clean, $rank] = $word;
        if ($rank !== null && $rank <= $top) {
            return true;
        }
        return $glossarywords && self::in_glossary($clean, $glossarywords);
    }

    /**
     * Coverage at a level, in percent, not rounded.
     *
     * Counted as the profiler does: in-level words, proper nouns and ignored words, over all
     * words except numbers. Glossary words and names (see counted_words()) count as covered too.
     *
     * @param array $profile
     * @param int $index Level index.
     * @param string[] $glossarywords
     * @return float
     */
    public function coverage(array $profile, int $index, array $glossarywords = []): float {
        $words = self::counted_words($profile);
        if (!$words) {
            return 0;
        }
        $top = $this->levels[$index]['top'];
        $covered = count(array_filter($words, fn($word) => self::is_covered($word, $top, $glossarywords)));
        return $covered / count($words) * 100;
    }

    /**
     * The words that are not covered at a level, most frequent first.
     *
     * @param array $profile
     * @param int $index Level index.
     * @param string[] $glossarywords
     * @return string[]
     */
    public function uncovered_words(array $profile, int $index, array $glossarywords = []): array {
        $top = $this->levels[$index]['top'];
        $counts = [];
        foreach (self::counted_words($profile) as $word) {
            if (!self::is_covered($word, $top, $glossarywords) && $word[0] !== '') {
                $counts[$word[0]] = ($counts[$word[0]] ?? 0) + 1;
            }
        }
        arsort($counts);
        return array_map('strval', array_keys($counts));
    }

    /**
     * The words the Text Profiler must ignore to count a passage as the rewriter does: the
     * names, and the words that only the glossary covers.
     *
     * @param array $profile
     * @param int $index Level index.
     * @param string[] $glossarywords
     * @return string[] Clean single words, in the order they first appear.
     */
    public function profiler_ignores(array $profile, int $index, array $glossarywords = []): array {
        $top = $this->levels[$index]['top'];
        $ignores = [];
        foreach (self::counted_words($profile) as $word) {
            $byname = $word[1] === 0;
            $byglossary = !self::is_covered($word, $top, []) && self::is_covered($word, $top, $glossarywords);
            if ($byname || $byglossary) {
                foreach (explode(' ', $word[0]) as $part) {
                    if ($part !== '') {
                        $ignores[$part] = true;
                    }
                }
            }
        }
        return array_map('strval', array_keys($ignores));
    }

    /**
     * Coverage for display: one decimal place, rounded down, so 94.96% never shows as 95.0%.
     *
     * @param float $coverage
     * @return float
     */
    public static function display_coverage(float $coverage): float {
        return floor($coverage * 10) / 10;
    }

    /**
     * Analyse a passage: its sentence figures, and its coverage at every level.
     *
     * @param string $text
     * @return array With stats, levels, currentlevel (number, or one more than the number of
     *     levels if none is reached) and currentlevellabel.
     */
    public function analyse(string $text): array {
        self::check_input($text);
        $profile = $this->profile($text);
        $stats = new text_stats($text);
        $levels = [];
        $current = count($this->levels) + 1;
        foreach ($this->levels as $level) {
            $coverage = $this->coverage($profile, $level['index']);
            $covered = $coverage >= self::COVERED;
            if ($covered && $level['number'] < $current) {
                $current = $level['number'];
            }
            $levels[] = [
                'index' => $level['index'],
                'number' => $level['number'],
                'name' => $level['name'],
                'label' => $this->level_label($level['number']),
                'cefr' => $level['cefr'],
                'avgsentence' => $level['avgsentence'],
                'maxsentence' => $level['maxsentence'],
                'coverage' => self::display_coverage($coverage),
                'covered' => $covered,
            ];
        }
        return [
            'stats' => self::stats_summary($stats),
            'levels' => $levels,
            'currentlevel' => $current,
            'currentlevellabel' => $this->level_label($current),
        ];
    }

    /**
     * The sentence figures for display.
     *
     * @param text_stats $stats
     * @return array
     */
    protected static function stats_summary(text_stats $stats): array {
        return [
            'words' => $stats->words,
            'sentences' => $stats->sentence_count(),
            'avgsentence' => round($stats->average_sentence(), 1),
            'longest' => $stats->longest_sentence(),
            'fkgrade' => $stats->fk_grade(),
        ];
    }

    /**
     * Reject an empty or too long passage.
     *
     * @param string $text
     */
    protected static function check_input(string $text): void {
        if (trim($text) === '') {
            throw new \moodle_exception('entersomething', constants::M_COMPONENT);
        }
        if (\core_text::strlen($text) > self::MAX_INPUT) {
            throw new \moodle_exception('aitexttoolong', constants::M_COMPONENT, '', self::MAX_INPUT);
        }
    }

    /**
     * The most words the rewritten text should have.
     *
     * @param int $words Words in the original.
     * @param int $from The original's level number (one more than the top level if above it).
     * @param int $to The target level number.
     * @return int|null Null if the text is too short to be shortened.
     */
    public static function max_words(int $words, int $from, int $to): ?int {
        if ($words < self::NO_SHORTEN_UNDER) {
            return null;
        }
        $shorter = min(self::MAX_SHORTER, self::SHORTER_PER_LEVEL * max(0, $from - $to));
        return (int) round($words * (100 - $shorter) / 100);
    }

    /**
     * How many hard words may stay, with a glossary entry, in a text of this length.
     *
     * @param int $words
     * @return int
     */
    public static function glossary_max(int $words): int {
        if ($words < 200) {
            return 3;
        }
        return $words <= 500 ? 5 : 7;
    }

    /**
     * Check a passage's sentences against a level's ceiling.
     *
     * Up to one sentence in SENTENCES_PER_ALLOWANCE may go over the ceiling, by no more than
     * CEILING_ALLOWANCE words.
     *
     * @param text_stats $stats
     * @param array $level
     * @return array With ok, over (sentence index => length) and allowed.
     */
    public static function sentence_check(text_stats $stats, array $level): array {
        if (!$level['maxsentence']) {
            return ['ok' => true, 'over' => [], 'allowed' => 0];
        }
        $over = $stats->sentences_over($level['maxsentence']);
        $allowed = intdiv($stats->sentence_count(), self::SENTENCES_PER_ALLOWANCE);
        $toolong = array_filter($over, fn($length) => $length > $level['maxsentence'] + self::CEILING_ALLOWANCE);
        return ['ok' => !$toolong && count($over) <= $allowed, 'over' => $over, 'allowed' => $allowed];
    }

    /**
     * Check a rewritten passage at a level.
     *
     * The glossary is cut down to the words the passage actually needs it for, up to the number
     * allowed for its length.
     *
     * @param string $text
     * @param int $index Level index.
     * @param array $glossary Each has word and explanation.
     * @return array With text, stats (text_stats), glossary, coverage, uncovered, sentences
     *     (see sentence_check()) and met.
     */
    public function check(string $text, int $index, array $glossary): array {
        $profile = $this->profile($text);
        $stats = new text_stats($text);
        $uncovered = $this->uncovered_words($profile, $index);

        $kept = [];
        foreach ($glossary as $item) {
            $word = self::clean_word((string) ($item['word'] ?? ''));
            $explanation = trim(strip_tags((string) ($item['explanation'] ?? '')));
            if ($word === '' || isset($kept[$word]) || count($kept) >= self::glossary_max($stats->words)) {
                continue;
            }
            $needed = array_filter($uncovered, fn($uncoveredword) => self::in_glossary($uncoveredword, [$word]));
            if ($needed) {
                $kept[$word] = ['word' => $word, 'explanation' => \core_text::substr($explanation, 0, 200)];
            }
        }
        $glossarywords = array_keys($kept);

        $coverage = $this->coverage($profile, $index, $glossarywords);
        $sentences = self::sentence_check($stats, $this->levels[$index]);
        return [
            'text' => $text,
            'ignores' => $this->profiler_ignores($profile, $index, $glossarywords),
            'stats' => $stats,
            'glossary' => array_values($kept),
            'coverage' => $coverage,
            'uncovered' => $this->uncovered_words($profile, $index, $glossarywords),
            'sentences' => $sentences,
            'met' => $coverage >= self::COVERED && $sentences['ok'],
        ];
    }

    /**
     * Simplify a passage to a level, or repair an earlier attempt, with one AI call.
     *
     * @param generator $generator
     * @param string $original The passage the user pasted.
     * @param int $index Target level index.
     * @param string $current The last attempt, or empty for the first rewrite.
     * @param array $glossary The last attempt's glossary.
     * @return array The result for the browser. See \mod_ogte_external::simplify_text_returns().
     */
    public function simplify(
        generator $generator,
        string $original,
        int $index,
        string $current = '',
        array $glossary = []
    ): array {
        $prompt = $this->prompt_vars($original, $index, $current, $glossary);
        [$before, $origstats, $maxwords] = [$prompt['before'], $prompt['origstats'], $prompt['maxwords']];
        if ($prompt['last'] && $prompt['last']['met']) {
            // Nothing to repair.
            return $this->result($prompt['last'], $before, $origstats, $index, $maxwords);
        }
        $reply = $generator->complete($prompt['name'], $prompt['vars']);
        [$text, $newglossary] = self::parse_reply($reply);
        return $this->result($this->check($text, $index, $newglossary), $before, $origstats, $index, $maxwords);
    }

    /**
     * The prompt for one pass and its placeholder values: simplify_to_level for the first
     * rewrite, or revise_to_level to repair the last attempt.
     *
     * @param string $original The passage the user pasted.
     * @param int $index Target level index.
     * @param string $current The last attempt, or empty for the first rewrite.
     * @param array $glossary The last attempt's glossary.
     * @return array name, vars, and for the result: before (the analysis of the original),
     *     origstats, maxwords, and last (the check of the last attempt, or null).
     */
    public function prompt_vars(string $original, int $index, string $current = '', array $glossary = []): array {
        self::check_input($original);
        if (!isset($this->levels[$index])) {
            throw new \invalid_parameter_exception('Unknown level');
        }
        $level = $this->levels[$index];
        $before = $this->analyse($original);
        $origstats = new text_stats($original);
        $maxwords = self::max_words($origstats->words, $before['currentlevel'], $level['number']);
        $ret = ['before' => $before, 'origstats' => $origstats, 'maxwords' => $maxwords, 'last' => null];

        if (trim($current) === '') {
            $ret['name'] = 'simplify_to_level';
            $ret['vars'] = $this->level_vars($level) + [
                'passage' => $original,
                'originallevel' => $before['currentlevellabel'],
                'originalwords' => $origstats->words,
                'maxwords' => $maxwords ?? '',
                'sentencerule' => self::sentence_rule($level),
                'lengthrule' => self::length_rule($origstats->words, $maxwords),
                'glossarymax' => self::glossary_max($maxwords ?? $origstats->words),
            ];
            return $ret;
        }

        self::check_input($current);
        $last = $this->check($current, $index, $glossary);
        $ret['last'] = $last;
        $ret['name'] = 'revise_to_level';
        $ret['vars'] = $this->level_vars($level) + [
            'passage' => $current,
            'hardwords' => self::hard_words($last),
            'longsentences' => self::long_sentences($last),
            'problems' => self::problems($last, $level),
            'glossarywords' => self::glossary_text($last['glossary']),
            'glossarymax' => self::glossary_max($last['stats']->words),
        ];
        return $ret;
    }

    /**
     * The prompt placeholders that come from the list and the level. A target the level does
     * not have is empty.
     *
     * @param array $level
     * @return array Placeholder name => value.
     */
    protected function level_vars(array $level): array {
        return [
            'language' => generator::language_name($this->list->lang),
            'listname' => $this->list->name,
            'level' => $this->prompt_level_name($level),
            'levelname' => $level['name'] !== '' ? $level['name'] : 'Level ' . $level['number'],
            'levelnumber' => $level['number'],
            'cefr' => $level['cefr'],
            'toprank' => $level['top'] ?: '',
            'avgsentence' => $level['avgsentence'] ?: '',
            'maxsentence' => $level['maxsentence'] ?: '',
            'leveldescription' => $level['description'],
        ];
    }

    /**
     * The level in full, e.g. "Level 3 (words 801 to 1200) of the NGSL 1.2 word list, CEFR A2".
     *
     * @param array $level
     * @return string
     */
    protected function prompt_level_name(array $level): string {
        $name = ($level['name'] !== '' ? $level['name'] : 'level ' . $level['number']) . ' of the '
            . $this->list->name . ' word list';
        return $level['cefr'] !== '' ? $name . ', CEFR ' . $level['cefr'] : $name;
    }

    /**
     * The sentence rule for the first prompt.
     *
     * @param array $level
     * @return string
     */
    protected static function sentence_rule(array $level): string {
        if (!$level['maxsentence']) {
            return 'Use short, clear sentences that suit this level.';
        }
        $rule = 'Keep every sentence to ' . $level['maxsentence'] . ' words or fewer';
        if ($level['avgsentence']) {
            $rule .= ', with an average of about ' . $level['avgsentence'] . ' words per sentence';
        }
        return $rule . '. Do not make sentences longer than they are in the original.';
    }

    /**
     * The length rule for the first prompt.
     *
     * @param int $words Words in the original.
     * @param int|null $maxwords
     * @return string
     */
    protected static function length_rule(int $words, ?int $maxwords): string {
        if ($maxwords === null) {
            return 'The original is short (' . $words . ' words): simplify it, but do not shorten it. Keep it close '
                . 'to the original length: explain only what a learner needs.';
        }
        if ($maxwords >= $words) {
            return 'Keep about the same length as the original (' . $words . ' words).';
        }
        return 'The original has ' . $words . ' words. Keep the passage to ' . $maxwords . ' words or fewer, but '
            . 'never cut main ideas or key facts to get there.';
    }

    /**
     * What a repair prompt asks the AI to fix.
     *
     * @param array $check From check().
     * @param array $level
     * @return string
     */
    protected static function problems(array $check, array $level): string {
        $problems = [];
        $hardwords = self::hard_words($check);
        if ($hardwords !== '') {
            $problems[] = 'These words are too hard for this level. Replace every one of them: ' . $hardwords . '.';
        }
        $longsentences = self::long_sentences($check);
        if ($longsentences !== '') {
            $problems[] = 'These sentences are too long. Split or shorten each one to ' . $level['maxsentence']
                . ' words or fewer:' . "\n" . $longsentences;
        }
        return implode("\n\n", $problems);
    }

    /**
     * The words a repair must replace, most frequent first, or empty if coverage is met.
     *
     * @param array $check From check().
     * @return string Comma separated.
     */
    protected static function hard_words(array $check): string {
        if ($check['coverage'] >= self::COVERED || !$check['uncovered']) {
            return '';
        }
        return implode(', ', array_slice($check['uncovered'], 0, self::MAX_PROBLEM_WORDS));
    }

    /**
     * The sentences a repair must split, one per line with its length, or empty if the sentence
     * targets are met.
     *
     * @param array $check From check().
     * @return string
     */
    protected static function long_sentences(array $check): string {
        if ($check['sentences']['ok']) {
            return '';
        }
        $lines = [];
        foreach ($check['sentences']['over'] as $sentenceindex => $length) {
            $lines[] = '- ' . $check['stats']->sentences[$sentenceindex] . ' (' . $length . ' words)';
        }
        return implode("\n", $lines);
    }

    /**
     * The glossary as the repair prompt lists it.
     *
     * @param array $glossary
     * @return string
     */
    protected static function glossary_text(array $glossary): string {
        if (!$glossary) {
            return 'none';
        }
        return implode('; ', array_map(fn($item) => $item['word'] . ' (' . $item['explanation'] . ')', $glossary));
    }

    /**
     * The passage and glossary in an AI reply.
     *
     * @param string $reply
     * @return array [text, glossary]
     */
    public static function parse_reply(string $reply): array {
        $decoded = generator::decode_reply($reply);
        if ($decoded === null || !isset($decoded['article']) || !is_string($decoded['article'])) {
            // A prompt edited to ask for something else: take the passage the usual way.
            return [generator::passage_from_reply($reply), []];
        }
        $glossary = [];
        foreach ((array) ($decoded['glossary'] ?? []) as $item) {
            if (is_array($item) && isset($item['word']) && is_string($item['word'])) {
                $glossary[] = ['word' => $item['word'], 'explanation' => (string) ($item['explanation'] ?? '')];
            }
        }
        return [trim($decoded['article']), $glossary];
    }

    /**
     * The result of one pass, for the browser.
     *
     * @param array $check From check().
     * @param array $before The original's analysis.
     * @param text_stats $origstats
     * @param int $index Target level index.
     * @param int|null $maxwords
     * @return array
     */
    protected function result(array $check, array $before, text_stats $origstats, int $index, ?int $maxwords): array {
        $level = $this->levels[$index];
        $stats = $check['stats'];
        $coverageok = $check['coverage'] >= self::COVERED;
        $str = fn($key, $a = null) => get_string($key, constants::M_COMPONENT, $a);

        // The notes come first, so the rows can point to them.
        $notes = [];
        $lengthnote = 0;
        if ($maxwords !== null && $stats->words > $maxwords) {
            $notes[] = $str('rw_note_length', ['over' => $stats->words - $maxwords, 'max' => $maxwords]);
            $lengthnote = count($notes);
        }
        if (!$coverageok) {
            $notes[] = $str('rw_note_coverage', self::display_coverage($check['coverage']));
        }
        $over = count($check['sentences']['over']);
        if ($over) {
            $notes[] = $str('rw_note_sentences', ['count' => $over, 'ceiling' => $level['maxsentence'],
                'allowed' => $check['sentences']['allowed'], 'extra' => self::CEILING_ALLOWANCE]);
        }

        $status = fn(bool $met) => $met ? 'met' : 'notmet';
        $rows = [];
        $rows[] = [
            'measure' => $str('rw_words'),
            'original' => $origstats->words,
            'rewritten' => $stats->words,
            'target' => $maxwords !== null ? $str('rw_upto', $maxwords) : $str('rw_noshorten'),
            'status' => $maxwords === null ? 'ref' : ($lengthnote ? 'note' : 'met'),
            'note' => $lengthnote,
        ];
        $rows[] = [
            'measure' => $str('rw_coverageat', $this->level_label($level['number'])),
            'original' => $before['levels'][$index]['coverage'] . '%',
            'rewritten' => self::display_coverage($check['coverage']) . '%',
            'target' => $str('rw_coveragetarget', self::COVERED),
            'status' => $status($coverageok),
        ];
        $hastargets = $level['maxsentence'] > 0;
        $rows[] = [
            'measure' => $str('rw_avgsentence'),
            'original' => round($origstats->average_sentence(), 1),
            'rewritten' => round($stats->average_sentence(), 1),
            'target' => $level['avgsentence'] ? $str('rw_about', $level['avgsentence']) : '—',
            'status' => $level['avgsentence'] ?
                $status($stats->average_sentence() <= max($level['avgsentence'] + 1, $origstats->average_sentence()))
                : 'ref',
        ];
        $rows[] = [
            'measure' => $str('rw_longest'),
            'original' => $origstats->longest_sentence(),
            'rewritten' => $stats->longest_sentence(),
            'target' => $hastargets ? $str('rw_ceiling', ['ceiling' => $level['maxsentence'],
                'extra' => self::CEILING_ALLOWANCE]) : '—',
            'status' => $hastargets ?
                $status($stats->longest_sentence() <= $level['maxsentence'] + self::CEILING_ALLOWANCE) : 'ref',
        ];
        if ($hastargets) {
            $rows[] = [
                'measure' => $str('rw_overceiling'),
                'original' => count($origstats->sentences_over($level['maxsentence'])),
                'rewritten' => $over,
                'target' => $str('rw_upto', $check['sentences']['allowed']),
                'status' => $status($over <= $check['sentences']['allowed']),
            ];
        }
        $rows[] = [
            'measure' => $str('rw_glossarywords'),
            'original' => '—',
            'rewritten' => count($check['glossary']),
            'target' => $str('rw_upto', self::glossary_max($stats->words)),
            'status' => 'met',
        ];
        $rows[] = [
            'measure' => $str('rw_fkgrade'),
            'original' => $origstats->fk_grade(),
            'rewritten' => $stats->fk_grade(),
            'target' => '—',
            'status' => 'ref',
        ];
        foreach ($rows as &$row) {
            $row['statuslabel'] = match ($row['status']) {
                'met' => $str('rw_met'),
                'notmet' => $str('rw_notmet'),
                'note' => $str('rw_seenote', $row['note']),
                default => $str('rw_forreference'),
            };
            $row['ismet'] = $row['status'] === 'met';
            $row['isnotmet'] = $row['status'] === 'notmet' || $row['status'] === 'note';
            unset($row['note']);
        }
        unset($row);

        return [
            'text' => $check['text'],
            'html' => self::highlight($check['text'], array_column($check['glossary'], 'word')),
            'glossary' => $check['glossary'],
            'glossarymax' => self::glossary_max($stats->words),
            'ignores' => $check['ignores'],
            'met' => $check['met'],
            // The browser keeps the best pass: met first, then coverage met, then sentences met,
            // then the higher coverage.
            'score' => ($check['met'] ? 1000 : 0) + ($coverageok ? 500 : 0) + ($check['sentences']['ok'] ? 200 : 0)
                + $check['coverage'],
            'title' => $str('rw_simplifiedfrom', ['from' => $before['currentlevellabel'],
                'to' => $this->level_label($level['number'])]),
            'levellabel' => $this->level_label($level['number']),
            'rows' => $rows,
            'notes' => array_map(fn($note) => ['text' => $note], $notes),
        ];
    }

    /**
     * The passage as HTML paragraphs, with the glossary words marked.
     *
     * @param string $text
     * @param string[] $glossarywords
     * @return string
     */
    public static function highlight(string $text, array $glossarywords): string {
        $paragraphs = preg_split('/\R\s*\R/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $pattern = null;
        if ($glossarywords) {
            $quoted = array_map(fn($word) => preg_quote(s($word), '/'), $glossarywords);
            $pattern = '/\b(?:' . implode('|', $quoted) . ')[a-z]{0,3}\b/iu';
        }
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $escaped = nl2br(s(trim($paragraph)));
            if ($pattern) {
                $escaped = preg_replace($pattern, '<mark class="ogte-glossary-word">$0</mark>', $escaped);
            }
            $html .= '<p>' . $escaped . '</p>';
        }
        return $html;
    }
}
