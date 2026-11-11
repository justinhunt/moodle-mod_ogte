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
 * Tests for the Text Generator. No AI calls are made.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(text_generator::class)]
final class text_generator_test extends \advanced_testcase {
    /**
     * Make a small two level word list.
     *
     * Level 1 is the, cat, sat; level 2 adds on, a, mat.
     *
     * @param bool $targets Do all the levels have sentence targets.
     * @return \stdClass The list record.
     */
    protected function make_list(bool $targets = true): \stdClass {
        global $DB;
        $levels = [
            ['name' => 'One', 'top' => 3, 'bottom' => 1, 'cefr' => 'A1', 'avgsentence' => 3, 'maxsentence' => 4],
            ['name' => 'Two', 'top' => 6, 'bottom' => 4],
        ];
        if ($targets) {
            $levels[1] += ['avgsentence' => 5, 'maxsentence' => 8];
        }
        $list = (object) ['courseid' => 0, 'name' => 'Test list ' . random_string(6), 'lang' => 'en-US',
            'props' => json_encode($levels), 'ispropernouns' => 0, 'hasmultiwordterms' => 0, 'status' => 1,
            'timecreated' => time(), 'timemodified' => time()];
        $list->id = $DB->insert_record(constants::M_LISTSTABLE, $list);
        foreach (['the', 'cat', 'sat', 'on', 'a', 'mat'] as $rank => $word) {
            $DB->insert_record(constants::M_WORDSTABLE, ['list' => $list->id, 'listrank' => $rank + 1,
                'word' => $word, 'headword' => $word]);
        }
        return $list;
    }

    /**
     * Only lists whose levels all have sentence targets are offered.
     */
    public function test_list_options(): void {
        $this->resetAfterTest();
        $with = $this->make_list();
        $without = $this->make_list(false);

        $keys = array_column(text_generator::list_options(SITEID), 'key');
        $this->assertContains((int) $with->id, $keys);
        $this->assertNotContains((int) $without->id, $keys);

        $options = array_column(text_generator::list_options(SITEID), null, 'key');
        $levels = $options[$with->id]['levels'];
        $this->assertCount(2, $levels);
        $this->assertEquals(['Level 1', '1–3 words'], [$levels[0]['label'], $levels[0]['range']]);
        $this->assertEquals('Level 1 (A1): sentences average about 3 words, longest 4', $levels[0]['summary']);

        $this->expectException(\invalid_parameter_exception::class);
        text_generator::for_course($without->id, SITEID);
    }

    /**
     * Words to include are tidied into a comma separated list.
     */
    public function test_clean_keywords(): void {
        $this->assertEquals('attic, compass, old map', text_generator::clean_keywords(" attic,compass;\nold map, attic, "));
        $this->assertEquals('', text_generator::clean_keywords(' , '));
    }

    /**
     * The length must be within the limits.
     */
    public function test_length_limits(): void {
        $this->resetAfterTest();
        $generator = new text_generator($this->make_list());
        $vars = $generator->generate_vars(0, 'fiction', text_generator::MAX_WORDS, 'A cat', '')['vars'];
        $this->assertEquals(text_generator::MAX_WORDS, $vars['targetwords']);

        $this->expectException(\moodle_exception::class);
        $generator->generate_vars(0, 'fiction', text_generator::MAX_WORDS + 1, 'A cat', '');
    }

    /**
     * A first attempt, then a repair that names the hard words, as the browser runs them.
     */
    public function test_generate_and_repair(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $textgenerator = new text_generator($this->make_list());
        $ai = new class (new provider_client('openai', 'k', 'm')) extends generator {
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
        $ai->replies = ['{"title": "The Cat", "article": "The cat sat. The dog sat.", "glossary": []}',
            '{"article": "The cat sat. The cat sat.", "glossary": []}'];

        $first = $textgenerator->generate($ai, 0, 'fiction', 300, 'A cat on a mat', 'mat, cat');
        $this->assertEquals('generate_article', $ai->calls[0][0]);
        $vars = $ai->calls[0][1];
        $this->assertEquals(
            ['A cat on a mat', 'mat, cat', 'fiction', 300, 5],
            [$vars['topic'], $vars['keywords'], $vars['texttype'], $vars['targetwords'], $vars['glossarymax']]
        );
        $this->assertStringContainsString('between 240 and 360', $vars['lengthrule']);
        $this->assertStringContainsString('4 words or fewer', $vars['sentencerule']);
        $this->assertStringNotContainsString('original', $vars['sentencerule']);
        $this->assertStringContainsString('story', $vars['texttyperule']);
        $this->assertStringContainsString('at least once', $vars['keywordrule']);
        $this->assertEquals('The Cat', $first['title']);
        $this->assertFalse($first['met']);
        $this->assertEquals(6, $first['words']);
        // The length is reported, but does not stop the targets being met.
        $rows = array_column($first['rows'], null, 'measure');
        $this->assertEquals('notmet', $rows['Words']['status']);

        $second = $textgenerator->generate(
            $ai,
            0,
            'fiction',
            300,
            'A cat on a mat',
            'mat, cat',
            $first['text'],
            $first['glossary']
        );
        $this->assertEquals('revise_to_level', $ai->calls[1][0]);
        $this->assertEquals('dog', $ai->calls[1][1]['hardwords']);
        $this->assertEquals('', $second['title']);
        $this->assertTrue($second['met']);
        $this->assertGreaterThan($first['score'], $second['score']);

        // Non-fiction, and no words to include.
        $vars = $textgenerator->generate_vars(1, 'nonfiction', 100, 'Mats', '')['vars'];
        $this->assertStringContainsString('factual', $vars['texttyperule']);
        $this->assertStringContainsString('no words', $vars['keywordrule']);
        $this->assertEquals(3, $vars['glossarymax']);
    }

    /**
     * The prompt preview fills generate_article with a sample description.
     */
    public function test_prompt_preview(): void {
        $this->resetAfterTest();
        $list = $this->make_list();
        $preview = \mod_ogte\local\ai\preview::fill(
            'generate_article',
            '',
            '{texttype} {targetwords}: {topic} / {keywords}',
            $list->id,
            0,
            'Bees'
        );
        $this->assertEquals('fiction 300: [[Bees]] / [[hive, flower]]', $preview['userprompt']);
        $this->assertEquals([], $preview['unknown']);
    }
}
