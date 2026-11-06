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
 * Sends a chat request straight to the AI provider the admin chose for OGTE.
 *
 * OGTE takes the API key from one of the site's AI provider instances and makes the HTTP call
 * itself, instead of going through the core AI manager. That way guests can use the tools and
 * each prompt can set its own token limit and reasoning effort.
 *
 * The cost of that is that the provider records are read directly. Their shape is internal to
 * core and changed between Moodle 4.5 and 5.0, so all the reading is kept in this class.
 *
 * Each provider gets one request shape, for its current model family. When a new family changes
 * the parameters, this is the class to update.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_client {
    /** @var string[] Provider plugins OGTE can talk to, keyed by our short name. */
    const PROVIDERS = [
        'openai' => 'aiprovider_openai',
        'gemini' => 'aiprovider_gemini',
        'deepseek' => 'aiprovider_deepseek',
    ];

    /** @var string The text generation action, whose settings hold the model and endpoint. */
    const GENERATE_TEXT = 'core_ai\\aiactions\\generate_text';

    /** @var int Seconds to wait for a reply. Reasoning models writing a passage can be slow. */
    const TIMEOUT = 120;

    /** @var string One of the keys of PROVIDERS. */
    protected string $type;

    /** @var string */
    protected string $apikey;

    /** @var string */
    protected string $model;

    /** @var string */
    protected string $endpoint;

    /** @var string OpenAI organisation id, if any. */
    protected string $orgid;

    /**
     * Constructor.
     *
     * @param string $type One of the keys of PROVIDERS.
     * @param string $apikey
     * @param string $model
     * @param string $endpoint The provider's text generation endpoint, or empty for the default.
     * @param string $orgid OpenAI organisation id.
     */
    public function __construct(string $type, string $apikey, string $model, string $endpoint = '', string $orgid = '') {
        if (!isset(self::PROVIDERS[$type])) {
            throw new \coding_exception('Unsupported AI provider type: ' . $type);
        }
        $this->type = $type;
        $this->apikey = $apikey;
        $this->model = $model;
        $this->endpoint = $endpoint;
        $this->orgid = $orgid;
    }

    /**
     * The provider type, one of the keys of PROVIDERS.
     *
     * @return string
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * The model that will be called.
     *
     * @return string
     */
    public function get_model(): string {
        return $this->model;
    }

    /**
     * The AI providers that can be chosen in the OGTE settings.
     *
     * On Moodle 5.0 and later these are the enabled provider instances of a supported type. On
     * 4.5 providers were plugins with one set of settings each, and of ours only OpenAI existed.
     *
     * @return array Setting value => label.
     */
    public static function provider_options(): array {
        global $DB, $CFG;
        $options = [];
        if ($CFG->branch >= 500) {
            $dbman = $DB->get_manager();
            if (!$dbman->table_exists('ai_providers')) {
                return $options;
            }
            [$insql, $params] = $DB->get_in_or_equal(self::provider_classes());
            $records = $DB->get_records_select('ai_providers', "provider $insql AND enabled = 1", $params, 'name');
            foreach ($records as $record) {
                $options[$record->id] = format_string($record->name) . ' (' . self::type_from_class($record->provider) . ')';
            }
        } else {
            foreach (self::PROVIDERS as $type => $plugin) {
                if (get_config($plugin, 'apikey')) {
                    $options[$plugin] = get_string('pluginname', $plugin);
                }
            }
        }
        return $options;
    }

    /**
     * The provider class names of the supported providers, as stored in ai_providers.
     *
     * @return string[]
     */
    protected static function provider_classes(): array {
        return array_map(function ($plugin) {
            return $plugin . '\\provider';
        }, array_values(self::PROVIDERS));
    }

    /**
     * Our short type name from a provider class name.
     *
     * @param string $class E.g. aiprovider_openai\provider.
     * @return string|null
     */
    protected static function type_from_class(string $class): ?string {
        $plugin = explode('\\', $class)[0];
        $type = array_search($plugin, self::PROVIDERS);
        return $type === false ? null : $type;
    }

    /**
     * The client for the provider chosen in the OGTE settings.
     *
     * @return self|null Null if no usable provider is set.
     */
    public static function from_settings(): ?self {
        global $DB, $CFG;
        $choice = get_config(constants::M_COMPONENT, 'aiprovider');
        if (empty($choice)) {
            return null;
        }
        $model = trim((string) get_config(constants::M_COMPONENT, 'aimodel'));

        if ($CFG->branch >= 500) {
            if (!is_numeric($choice)) {
                return null;
            }
            $record = $DB->get_record('ai_providers', ['id' => $choice, 'enabled' => 1]);
            if (!$record) {
                return null;
            }
            $type = self::type_from_class($record->provider);
            $config = json_decode($record->config, true) ?: [];
            $actionconfig = json_decode((string) $record->actionconfig, true) ?: [];
            $settings = $actionconfig[self::GENERATE_TEXT]['settings'] ?? [];
            $apikey = $config['apikey'] ?? '';
            $orgid = $config['orgid'] ?? '';
            $endpoint = $settings['endpoint'] ?? '';
            if ($model === '') {
                $model = $settings['model'] ?? '';
            }
        } else {
            $type = array_search($choice, self::PROVIDERS);
            if ($type === false) {
                return null;
            }
            $apikey = (string) get_config($choice, 'apikey');
            $orgid = (string) get_config($choice, 'orgid');
            $endpoint = (string) get_config($choice, 'action_generate_text_endpoint');
            if ($model === '') {
                $model = (string) get_config($choice, 'action_generate_text_model');
            }
        }

        if (!$type || $apikey === '' || $model === '') {
            return null;
        }
        return new self($type, $apikey, $model, $endpoint, $orgid);
    }

    /**
     * Build the HTTP request for one chat call.
     *
     * @param string $systemprompt
     * @param string $userprompt
     * @param array $opts Any of: maxtokens (int), effort (low|medium|high), temperature (string or
     *                    float, empty for the default), jsonmode (bool).
     * @return array [url, headers, body array]
     */
    public function build_request(string $systemprompt, string $userprompt, array $opts = []): array {
        $maxtokens = !empty($opts['maxtokens']) ? (int) $opts['maxtokens'] : 0;
        $effort = isset($opts['effort']) && in_array($opts['effort'], prompts::EFFORTS) ? $opts['effort'] : '';
        $temperature = isset($opts['temperature']) && is_numeric($opts['temperature']) ? (float) $opts['temperature'] : null;
        $jsonmode = !empty($opts['jsonmode']);

        switch ($this->type) {
            case 'openai':
                // The Responses API. The current GPT models reason, and reject temperature while
                // reasoning, so it is never sent.
                $url = $this->endpoint ?: 'https://api.openai.com/v1/responses';
                $url = preg_replace('~/chat/completions$~', '/responses', $url);
                $headers = ['Authorization: Bearer ' . $this->apikey];
                if ($this->orgid !== '') {
                    $headers[] = 'OpenAI-Organization: ' . $this->orgid;
                }
                $body = [
                    'model' => $this->model,
                    'instructions' => $systemprompt,
                    'input' => $userprompt,
                    // Responses stores requests by default. We send student writing, so opt out.
                    'store' => false,
                ];
                if ($maxtokens) {
                    // Counts reasoning tokens as well as the reply.
                    $body['max_output_tokens'] = $maxtokens;
                }
                if ($effort !== '') {
                    $body['reasoning'] = ['effort' => $effort];
                }
                if ($jsonmode) {
                    $body['text'] = ['format' => ['type' => 'json_object']];
                }
                break;

            case 'gemini':
                // We always use our own model, so the endpoint is built rather than taken from
                // the provider, whose endpoint names the provider's model.
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($this->model)
                    . ':generateContent';
                $headers = ['x-goog-api-key: ' . $this->apikey];
                $generationconfig = [];
                if ($maxtokens) {
                    $generationconfig['maxOutputTokens'] = $maxtokens;
                }
                if ($effort !== '') {
                    $generationconfig['thinkingConfig'] = ['thinkingLevel' => $effort];
                }
                if ($temperature !== null) {
                    // Google advises leaving Gemini 3 at its default, so only sent when set.
                    $generationconfig['temperature'] = $temperature;
                }
                if ($jsonmode) {
                    $generationconfig['responseMimeType'] = 'application/json';
                }
                $body = [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $userprompt]]]],
                ];
                if ($systemprompt !== '') {
                    $body['systemInstruction'] = ['parts' => [['text' => $systemprompt]]];
                }
                if ($generationconfig) {
                    $body['generationConfig'] = $generationconfig;
                }
                break;

            case 'deepseek':
            default:
                // OpenAI style chat completions. Thinking is on by default; temperature has no
                // effect while thinking, but is harmless, so it is sent when set.
                $url = $this->endpoint ?: 'https://api.deepseek.com/chat/completions';
                $headers = ['Authorization: Bearer ' . $this->apikey];
                $messages = [];
                if ($systemprompt !== '') {
                    $messages[] = ['role' => 'system', 'content' => $systemprompt];
                }
                $messages[] = ['role' => 'user', 'content' => $userprompt];
                $body = ['model' => $this->model, 'messages' => $messages, 'stream' => false];
                if ($maxtokens) {
                    $body['max_tokens'] = $maxtokens;
                }
                if ($effort !== '') {
                    $body['reasoning_effort'] = $effort;
                }
                if ($temperature !== null) {
                    $body['temperature'] = $temperature;
                }
                if ($jsonmode) {
                    $body['response_format'] = ['type' => 'json_object'];
                }
                break;
        }
        $headers[] = 'Content-Type: application/json';
        return [$url, $headers, $body];
    }

    /**
     * Send one chat request and return the reply text.
     *
     * @param string $systemprompt
     * @param string $userprompt
     * @param array $opts See build_request().
     * @return string The reply text.
     * @throws \moodle_exception If the request fails or the reply has no text.
     */
    public function chat(string $systemprompt, string $userprompt, array $opts = []): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        [$url, $headers, $body] = $this->build_request($systemprompt, $userprompt, $opts);

        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => self::TIMEOUT, 'CURLOPT_CONNECTTIMEOUT' => 20]);
        $curl->setHeader($headers);
        $raw = $curl->post($url, json_encode($body));
        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if ($curl->get_errno()) {
            $this->fail('connection error: ' . $curl->error);
        }
        $response = json_decode((string) $raw, true);
        if (!is_array($response)) {
            $this->fail('HTTP ' . $httpcode . ', reply was not JSON');
        }
        return $this->extract_text($response, $httpcode);
    }

    /**
     * Take the reply text out of a provider's response.
     *
     * @param array $response The decoded response.
     * @param int $httpcode
     * @return string
     * @throws \moodle_exception If the response is an error or has no text.
     */
    public function extract_text(array $response, int $httpcode = 200): string {
        if (isset($response['error'])) {
            $error = $response['error'];
            $message = is_array($error) ? ($error['message'] ?? json_encode($error)) : (string) $error;
            $this->fail('HTTP ' . $httpcode . ': ' . $message);
        }

        $text = '';
        switch ($this->type) {
            case 'openai':
                // The text sits in output[].content[].text. The output_text field only exists in
                // the SDKs, not in the raw JSON.
                foreach ($response['output'] ?? [] as $item) {
                    if (($item['type'] ?? '') !== 'message') {
                        continue;
                    }
                    foreach ($item['content'] ?? [] as $part) {
                        if (($part['type'] ?? '') === 'output_text') {
                            $text .= $part['text'];
                        }
                    }
                }
                if (trim($text) === '' && ($response['status'] ?? '') === 'incomplete') {
                    $this->fail('reply incomplete: ' . ($response['incomplete_details']['reason'] ?? 'no reason given')
                        . '. If this is max_output_tokens, raise the prompt\'s max tokens or lower its effort.');
                }
                break;

            case 'gemini':
                $candidate = $response['candidates'][0] ?? [];
                foreach ($candidate['content']['parts'] ?? [] as $part) {
                    if (empty($part['thought']) && isset($part['text'])) {
                        $text .= $part['text'];
                    }
                }
                if (trim($text) === '' && isset($candidate['finishReason'])) {
                    $this->fail('no text, finish reason ' . $candidate['finishReason']
                        . '. If this is MAX_TOKENS, raise the prompt\'s max tokens or lower its effort.');
                }
                break;

            case 'deepseek':
            default:
                $choice = $response['choices'][0] ?? [];
                $text = (string) ($choice['message']['content'] ?? '');
                // DeepSeek blanks a reply that was not valid JSON in JSON mode.
                if (trim($text) === '' && isset($choice['finish_reason'])) {
                    $this->fail('no text, finish reason ' . $choice['finish_reason']
                        . '. If this is length, raise the prompt\'s max tokens or lower its effort.');
                }
                break;
        }

        $text = trim($text);
        if ($text === '') {
            $this->fail('the reply had no text');
        }
        return $text;
    }

    /**
     * Log a failed call and throw.
     *
     * The user sees a general message. The detail goes to the error log, since debugging()
     * shows nothing on a production site, and to the exception's debug info.
     *
     * @param string $detail
     * @throws \moodle_exception
     */
    protected function fail(string $detail): void {
        $detail = $this->type . ' (' . $this->model . '): ' . $detail;
        // Logged as well as thrown, so failures show up on production sites.
        error_log('mod_ogte AI call failed: ' . $detail); // phpcs:ignore moodle.PHP.ForbiddenFunctions.FoundWithAlternative
        throw new \moodle_exception('aicallfailed', constants::M_COMPONENT, '', null, $detail);
    }
}
