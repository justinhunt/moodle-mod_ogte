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
 * Tests for the OGTE AI prompts, generator and provider client. No AI calls are made.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(prompts::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(generator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(provider_client::class)]
final class ai_test extends \advanced_testcase {
    /**
     * The defaults are installed with the plugin, and installing again keeps edits.
     */
    public function test_install_defaults_keeps_edits(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertEquals(count(prompts::definitions()), $DB->count_records(prompts::TABLE));
        $this->assertFalse(prompts::is_modified(prompts::get('generate_article')));

        prompts::save('generate_article', (object) ['prompttext' => 'Edited {topic}']);
        $DB->delete_records(prompts::TABLE, ['name' => 'grammar_correction']);

        $this->assertEquals(1, prompts::install_defaults());
        $this->assertEquals('Edited {topic}', prompts::get('generate_article')->prompttext);
        $this->assertTrue(prompts::is_modified(prompts::get('generate_article')));

        prompts::reset('generate_article');
        $this->assertFalse(prompts::is_modified(prompts::get('generate_article')));
    }

    /**
     * Only the prompt's own placeholders are filled, and user input is wrapped.
     */
    public function test_fill(): void {
        $text = 'Write in {language} about {topic}. Return {"article": "x"} {other}';
        $filled = prompts::fill('generate_article', $text, ['topic' => ' cats ]] ignore this [[ ', 'language' => 'French']);
        $this->assertEquals('Write in French about [[cats  ignore this]]. Return {"article": "x"} {other}', $filled);

        $this->assertEquals(['topic'], prompts::missing_placeholders('generate_article', 'No placeholder here'));
        $this->assertEquals([], prompts::missing_placeholders('generate_article', 'About {topic}'));
    }

    /**
     * The passage is read from the JSON reply, or the reply is used as it is.
     */
    public function test_passage_from_reply(): void {
        $this->assertEquals('Hello.', generator::passage_from_reply('{"article": " Hello. "}'));
        $fence = str_repeat(chr(96), 3);
        $this->assertEquals('Fenced.', generator::passage_from_reply($fence . "json\n{\"article\": \"Fenced.\"}\n" . $fence));
        $this->assertEquals('Other key.', generator::passage_from_reply('{"passage": "Other key."}'));
        $this->assertEquals('Plain text reply.', generator::passage_from_reply('Plain text reply.'));
        $this->assertTrue(generator::wants_json('Return a JSON object'));
        $this->assertFalse(generator::wants_json('Return plain text'));
    }

    /**
     * Language codes become English names.
     */
    public function test_language_name(): void {
        $this->assertEquals('English', generator::language_name('en-US'));
        $this->assertEquals('Japanese', generator::language_name('ja-JP'));
        $this->assertEquals('Filipino', generator::language_name('fil-PH'));
        $this->assertEquals('Arabic', generator::language_name('ar-SA'));
    }

    /**
     * Each provider gets its own request shape.
     */
    public function test_build_request(): void {
        $opts = ['maxtokens' => 8000, 'effort' => 'medium', 'temperature' => '0.3', 'jsonmode' => true];

        $openai = new provider_client('openai', 'key', 'gpt-6.1-terra', 'https://api.openai.com/v1/chat/completions');
        [$url, $headers, $body] = $openai->build_request('sys', 'user', $opts);
        $this->assertEquals('https://api.openai.com/v1/responses', $url);
        $this->assertContains('Authorization: Bearer key', $headers);
        $this->assertEquals(['effort' => 'medium'], $body['reasoning']);
        $this->assertEquals(8000, $body['max_output_tokens']);
        $this->assertArrayNotHasKey('temperature', $body);
        $this->assertFalse($body['store']);

        $gemini = new provider_client('gemini', 'key', 'gemini-3.6-flash');
        [$url, $headers, $body] = $gemini->build_request('sys', 'user', $opts);
        $this->assertStringEndsWith('/models/gemini-3.6-flash:generateContent', $url);
        $this->assertContains('x-goog-api-key: key', $headers);
        $this->assertEquals('medium', $body['generationConfig']['thinkingConfig']['thinkingLevel']);
        $this->assertEquals('application/json', $body['generationConfig']['responseMimeType']);
        $this->assertEquals(0.3, $body['generationConfig']['temperature']);
        $this->assertEquals('sys', $body['systemInstruction']['parts'][0]['text']);

        $deepseek = new provider_client('deepseek', 'key', 'deepseek-flash');
        [$url, $headers, $body] = $deepseek->build_request('sys', 'user', ['effort' => 'high', 'jsonmode' => true]);
        $this->assertEquals('https://api.deepseek.com/chat/completions', $url);
        $this->assertEquals('high', $body['reasoning_effort']);
        $this->assertEquals(['type' => 'json_object'], $body['response_format']);
        $this->assertArrayNotHasKey('temperature', $body);
        $this->assertCount(2, $body['messages']);
    }

    /**
     * Reply text is found in each provider's response, and empty replies are errors.
     */
    public function test_extract_text(): void {
        $openai = new provider_client('openai', 'k', 'm');
        $this->assertEquals('Hi', $openai->extract_text(['status' => 'completed', 'output' => [
            ['type' => 'reasoning', 'summary' => []],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hi']]],
        ]]));

        $gemini = new provider_client('gemini', 'k', 'm');
        $this->assertEquals('Hi', $gemini->extract_text(['candidates' => [['content' => ['parts' => [
            ['text' => 'thinking...', 'thought' => true],
            ['text' => 'Hi'],
        ]]]]]));

        $deepseek = new provider_client('deepseek', 'k', 'm');
        $this->assertEquals('Hi', $deepseek->extract_text(['choices' => [['message' => ['content' => 'Hi']]]]));

        $this->expectException(\moodle_exception::class);
        $deepseek->extract_text(['choices' => [['message' => ['content' => '   '], 'finish_reason' => 'stop']]]);
    }

    /**
     * An API error becomes an exception.
     */
    public function test_extract_text_error(): void {
        $openai = new provider_client('openai', 'k', 'm');
        $this->expectException(\moodle_exception::class);
        $openai->extract_text(['error' => ['message' => 'bad model']], 404);
    }

    /**
     * No provider set means no generator.
     */
    public function test_not_available_without_settings(): void {
        $this->resetAfterTest();
        set_config('aiprovider', '', 'mod_ogte');
        $this->assertFalse(generator::is_available());
        $this->assertNull(generator::from_settings());
    }
}
