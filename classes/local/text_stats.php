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

/**
 * Sentence and readability figures for a passage: word and sentence counts, sentence lengths
 * and the Flesch-Kincaid grade.
 *
 * Words here are space separated tokens with a letter or digit in them, so these counts can
 * differ a little from the profiler's, which leaves out numbers.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_stats {
    /** @var string[] The sentences. */
    public array $sentences = [];

    /** @var int[] The number of words in each sentence. */
    public array $sentencelengths = [];

    /** @var int Words in the passage. */
    public int $words = 0;

    /** @var int Syllables in the passage. */
    public int $syllables = 0;

    /**
     * Work out the figures for a passage.
     *
     * @param string $text
     */
    public function __construct(string $text) {
        $text = trim(strip_tags($text));
        // Paragraphs and lines are always sentence breaks, so a heading without a full stop
        // does not join the sentence after it.
        $lines = preg_split('/\R+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($lines as $line) {
            // A sentence ends at . ! or ? and a closing quote or bracket if there is one, then a space.
            $parts = preg_split(
                '/(?<=[.!?])\s+|(?<=[.!?]["\'\x{201D}\x{2019})\]])\s+/u',
                trim($line),
                -1,
                PREG_SPLIT_NO_EMPTY
            );
            foreach ($parts as $part) {
                $words = self::words($part);
                if (!$words) {
                    continue;
                }
                $this->sentences[] = trim($part);
                $this->sentencelengths[] = count($words);
                $this->words += count($words);
                foreach ($words as $word) {
                    $this->syllables += self::syllables($word);
                }
            }
        }
    }

    /**
     * The words in a piece of text.
     *
     * @param string $text
     * @return string[]
     */
    public static function words(string $text): array {
        $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($tokens, fn($token) => preg_match('/[\p{L}\p{N}]/u', $token)));
    }

    /**
     * Estimate the syllables in an English word.
     *
     * @param string $word
     * @return int At least 1.
     */
    public static function syllables(string $word): int {
        $word = strtolower(preg_replace('/[^a-zA-Z]/', '', $word));
        if ($word === '') {
            // A number or a word in another script: count it as one.
            return 1;
        }
        if (strlen($word) <= 3) {
            return 1;
        }
        // A silent e or es/ed ending, but not "-le" as in "table".
        $word = preg_replace('/(?:[^laeiouy]es|[^laeiouy]ed|[^laeiouy]e)$/', '', $word);
        $word = preg_replace('/^y/', '', $word);
        $count = preg_match_all('/[aeiouy]+/', $word);
        return max(1, $count);
    }

    /**
     * The number of sentences.
     *
     * @return int
     */
    public function sentence_count(): int {
        return count($this->sentences);
    }

    /**
     * Average words per sentence.
     *
     * @return float
     */
    public function average_sentence(): float {
        return $this->sentences ? $this->words / count($this->sentences) : 0;
    }

    /**
     * Words in the longest sentence.
     *
     * @return int
     */
    public function longest_sentence(): int {
        return $this->sentencelengths ? max($this->sentencelengths) : 0;
    }

    /**
     * The sentences longer than a number of words.
     *
     * @param int $maxwords
     * @return array Sentence index => length.
     */
    public function sentences_over(int $maxwords): array {
        return array_filter($this->sentencelengths, fn($length) => $length > $maxwords);
    }

    /**
     * The Flesch-Kincaid grade level.
     *
     * @return float
     */
    public function fk_grade(): float {
        if (!$this->words || !$this->sentences) {
            return 0;
        }
        $grade = 0.39 * $this->words / count($this->sentences) + 11.8 * $this->syllables / $this->words - 15.59;
        return round($grade, 1);
    }
}
