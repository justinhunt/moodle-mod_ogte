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
use mod_ogte\local\ai\provider_client;

/**
 * Tests for the Text Rewriter and its text figures. No AI calls are made.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(rewriter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(text_stats::class)]
final class rewriter_test extends \advanced_testcase {
    /**
     * Make a small three level word list.
     *
     * Level 1 is the, cat, sat; level 2 adds on, a, mat; level 3 adds dog, ran, fast.
     *
     * @return \stdClass The list record.
     */
    protected function make_list(): \stdClass {
        global $DB;
        $levels = [
            ['name' => 'One', 'top' => 3, 'bottom' => 1, 'cefr' => 'A1', 'avgsentence' => 3, 'maxsentence' => 4,
                'description' => 'Tiny.'],
            ['name' => 'Two', 'top' => 6, 'bottom' => 4],
            ['name' => 'Three', 'top' => 9, 'bottom' => 7],
        ];
        $list = (object) ['courseid' => 0, 'name' => 'Test list ' . random_string(6), 'lang' => 'en-US',
            'props' => json_encode($levels), 'ispropernouns' => 0, 'hasmultiwordterms' => 0, 'status' => 1,
            'timecreated' => time(), 'timemodified' => time()];
        $list->id = $DB->insert_record(constants::M_LISTSTABLE, $list);
        foreach (['the', 'cat', 'sat', 'on', 'a', 'mat', 'dog', 'ran', 'fast'] as $rank => $word) {
            $DB->insert_record(constants::M_WORDSTABLE, ['list' => $list->id, 'listrank' => $rank + 1,
                'word' => $word, 'headword' => $word]);
        }
        return $list;
    }

    /**
     * Sentences, lengths and the Flesch-Kincaid grade.
     */
    public function test_text_stats(): void {
        $stats = new text_stats("A title\nThe cat sat on the mat. \"Did it?\" she asked! Yes, 3 times.");
        $this->assertEquals(['A title', 'The cat sat on the mat.', '"Did it?"', 'she asked!', 'Yes, 3 times.'],
            $stats->sentences);
        $this->assertEquals(15, $stats->words);
        $this->assertEquals(6, $stats->longest_sentence());
        $this->assertEquals([1 => 6], $stats->sentences_over(5));
        $this->assertEquals(1, text_stats::syllables('cat'));
        $this->assertEquals(2, text_stats::syllables('table'));
        $this->assertEquals(4, text_stats::syllables('remarkable'));
        $this->assertEquals(0, (new text_stats(''))->fk_grade());
    }

    /**
     * The length, glossary and sentence targets.
     */
    public function test_targets(): void {
        // 612 words from level 7 to level 3, as in the specification: 4 levels, 28% shorter.
        $this->assertEquals(441, rewriter::max_words(612, 7, 3));
        // Above level 7 counts as level 8.
        $this->assertEquals(398, rewriter::max_words(612, 8, 3));
        $this->assertEquals(500, rewriter::max_words(1000, 9, 1));
        $this->assertEquals(300, rewriter::max_words(300, 2, 2));
        $this->assertNull(rewriter::max_words(199, 8, 1));
        $this->assertEquals(3, rewriter::glossary_max(150));
        $this->assertEquals(5, rewriter::glossary_max(500));
        $this->assertEquals(7, rewriter::glossary_max(501));

        $level = ['maxsentence' => 10];
        $twenty = str_repeat('One two three. ', 19) . 'One two three four five six seven eight nine ten eleven.';
        $this->assertTrue(rewriter::sentence_check(new text_stats($twenty), $level)['ok']);
        $nineteen = str_repeat('One two three. ', 18) . 'One two three four five six seven eight nine ten eleven.';
        $this->assertFalse(rewriter::sentence_check(new text_stats($nineteen), $level)['ok']);
        $toolong = str_repeat('One two three. ', 19) . str_repeat('word ', 15) . 'end.';
        $this->assertFalse(rewriter::sentence_check(new text_stats($toolong), $level)['ok']);
        $this->assertTrue(rewriter::sentence_check(new text_stats($toolong), ['maxsentence' => 0])['ok']);
    }

    /**
     * Coverage at every level from one profiler run, and the current level.
     */
    public function test_analyse(): void {
        $this->resetAfterTest();
        $rewriter = new rewriter($this->make_list());
        $result = $rewriter->analyse('The cat sat on a mat. The dog ran fast.');

        $this->assertEquals([40, 70, 100], array_column($result['levels'], 'coverage'));
        $this->assertEquals([false, false, true], array_column($result['levels'], 'covered'));
        $this->assertEquals(3, $result['currentlevel']);
        $this->assertEquals('Level 1 (A1)', $result['levels'][0]['label']);
        $this->assertEquals('Level 2', $result['levels'][1]['label']);
        $this->assertEquals(10, $result['stats']['words']);
        $this->assertEquals(2, $result['stats']['sentences']);

        $none = $rewriter->analyse('Zebras graze.');
        $this->assertEquals(4, $none['currentlevel']);
        $this->assertEquals('above Level 3', $none['currentlevellabel']);
    }

    /**
     * Names and glossary words count as covered, and coverage is never rounded up to 95%.
     */
    public function test_names_and_glossary(): void {
        $this->resetAfterTest();
        $rewriter = new rewriter($this->make_list());

        // Frisch is capitalised in mid sentence, so it is a name. Met is not.
        $profile = $rewriter->profile('The cat met Frisch on a mat.');
        $this->assertEquals(6 / 7 * 100, $rewriter->coverage($profile, 1));
        $this->assertEquals(['met'], $rewriter->uncovered_words($profile, 1));

        // A kept word covers its simple forms.
        $check = $rewriter->check('The cat sat on a mat with zebras.', 1, [
            ['word' => 'Zebra', 'explanation' => 'a striped horse'],
            ['word' => 'lion', 'explanation' => 'not in the text, so dropped'],
        ]);
        $this->assertEquals([['word' => 'zebra', 'explanation' => 'a striped horse']], $check['glossary']);
        $this->assertEquals(['with'], $check['uncovered']);
        // The Text Profiler is told to ignore the forms the glossary covers.
        $this->assertEquals(['zebras'], $check['ignores']);
        $this->assertEquals(['frisch'], $rewriter->profiler_ignores($profile, 1));
        $this->assertFalse($check['met']);

        $this->assertEquals(94.9, rewriter::display_coverage(94.99));
    }

    /**
     * The passage and glossary are read from the reply.
     */
    public function test_parse_reply(): void {
        [$text, $glossary] = rewriter::parse_reply('{"article": " Text. ", "glossary": [{"word": "a", '
            . '"explanation": "b"}, "junk"]}');
        $this->assertEquals('Text.', $text);
        $this->assertEquals([['word' => 'a', 'explanation' => 'b']], $glossary);
        $this->assertEquals(['Plain.', []], rewriter::parse_reply('Plain.'));

        $html = rewriter::highlight("Zebras <run>.\n\nA zebra.", ['zebra']);
        $this->assertEquals('<p><mark class="ogte-glossary-word">Zebras</mark> &lt;run&gt;.</p>'
            . '<p>A <mark class="ogte-glossary-word">zebra</mark>.</p>', $html);
    }

    /**
     * A first rewrite, then a repair that names the hard words, as the browser runs them.
     */
    public function test_simplify_and_repair(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $rewriter = new rewriter($this->make_list());
        $generator = new class (new provider_client('openai', 'k', 'm')) extends generator {
            /** @var string[] The replies to give. */
            public array $replies = [];
            /** @var array The prompts asked for. */
            public array $calls = [];

            /**
             * Answer with the next reply.
             *
             * @param string $name
             * @param array $vars
             * @return string
             */
            public function complete(string $name, array $vars): string {
                $this->calls[] = [$name, $vars];
                return array_shift($this->replies);
            }
        };
        $generator->replies = ['{"article": "The cat sat. The dog sat.", "glossary": []}',
            '{"article": "The cat sat. The cat sat.", "glossary": []}'];
        $original = 'The dog ran fast on a mat.';

        $first = $rewriter->simplify($generator, $original, 0);
        $this->assertEquals('simplify_to_level', $generator->calls[0][0]);
        $this->assertEquals($original, $generator->calls[0][1]['passage']);
        $this->assertStringContainsString('One of the', $generator->calls[0][1]['levelname']);
        $this->assertStringContainsString('4 words or fewer', $generator->calls[0][1]['sentencerule']);
        $this->assertFalse($first['met']);
        $this->assertEquals('Simplified from Level 3 to Level 1 (A1)', $first['title']);

        $second = $rewriter->simplify($generator, $original, 0, $first['text'], $first['glossary']);
        $this->assertEquals('revise_to_level', $generator->calls[1][0]);
        $this->assertStringContainsString('dog', $generator->calls[1][1]['problems']);
        $this->assertTrue($second['met']);
        $this->assertGreaterThan($first['score'], $second['score']);
        $this->assertEquals('100%', $second['rows'][1]['rewritten']);
        $this->assertEmpty($second['notes']);
    }

    /**
     * The NGSL 1.2 targets are added once, and edits are kept.
     */
    public function test_add_ngsl_targets(): void {
        global $DB;
        $this->resetAfterTest();
        $levels = [];
        for ($i = 1; $i <= 7; $i++) {
            $levels[] = ['name' => 'Level ' . $i, 'top' => $i * 400, 'bottom' => $i * 400 - 399];
        }
        $levels[2]['maxsentence'] = 99;
        $id = $DB->insert_record(constants::M_LISTSTABLE, ['courseid' => 0, 'name' => 'NGSL 1.2 (General English)',
            'lang' => 'en-US', 'props' => json_encode($levels), 'ispropernouns' => 0]);

        $this->assertEquals(1, rewriter::add_ngsl_targets());
        $this->assertEquals(0, rewriter::add_ngsl_targets());
        $props = json_decode($DB->get_field(constants::M_LISTSTABLE, 'props', ['id' => $id]), true);
        $this->assertEquals('A1', $props[0]['cefr']);
        $this->assertEquals(22, $props[6]['maxsentence']);
        $this->assertEquals(['name' => 'Level 3', 'top' => 1200, 'bottom' => 801, 'maxsentence' => 99], $props[2]);
    }
}
