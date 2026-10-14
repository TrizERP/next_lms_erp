<?php

namespace App\Domain\AI\Conversation;

use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Domain\AI\Support\FallbackModelClient;
use App\Domain\AI\Support\ModelClient;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Speaking and being answered in English, Hindi or Gujarati.
 *
 * WHY THIS EXISTS
 *
 * Everything between a question and its answer — the intent classifier, the planner, the tool
 * selection, the answer composer — is written in English: "fees", "pending", "show students".
 * A sentence in Devanagari or Gujarati script matched none of it, and a data answer came back in
 * English whatever language had been spoken. The browser's speech recognition was producing a
 * perfectly good Hindi or Gujarati transcript that nothing downstream could read.
 *
 * THE APPROACH
 *
 * The language is a layer around the existing pipeline, not a second pipeline:
 *
 *   1. {@see resolve()} decides which language the person is using and which they want back.
 *   2. {@see toEnglish()} turns a non-English question into English for the pipeline to read.
 *   3. The pipeline runs unchanged — same tools, same tenant scope, same approvals.
 *   4. {@see localize()} turns the words of the answer back into the person's language.
 *
 * Steps 2 and 4 use the model the conversational module is already configured with. No answer,
 * name or figure is written in this file.
 *
 * WHAT IS NEVER CHANGED
 *
 * Figures, identifiers, dates, names of people and the text of buttons that are sent back to the
 * server are left exactly as they are. A translation that loses or alters a number is rejected
 * string by string, and that string stays in English: a correct English line is better than a
 * fluent wrong one.
 *
 * WHAT HAPPENS WITHOUT A MODEL
 *
 * An unconfigured or failing provider never breaks the turn. The question is processed as it
 * arrived and the answer stays English; the result says so (`language.delivered`), so a screen
 * does not claim to be speaking Hindi when it is not.
 */
class ResponseLanguage
{
    /** @var array<string, array{name: string, tts: string, script: string}> */
    public const LANGUAGES = [
        'en' => ['name' => 'English', 'tts' => 'en-IN', 'script' => 'Latin'],
        'hi' => ['name' => 'Hindi', 'tts' => 'hi-IN', 'script' => 'Devanagari'],
        'gu' => ['name' => 'Gujarati', 'tts' => 'gu-IN', 'script' => 'Gujarati'],
    ];

    private const MODULE = 'conversational_ai';

    /** Gemini models tried, in order, when the configured one fails. Overridable in config/ai.php. */
    private const FALLBACK_MODELS = ['gemini-3.1-flash-lite', 'gemini-3.5-flash-lite'];

    /** Strings sent to the model in one call. */
    private const BATCH = 60;

    /**
     * How long one model call may take. A chat turn waits on these, so a provider that is slow or
     * overloaded must cost seconds, not the minute its own retries would.
     */
    private const READ_TIMEOUT = 15;

    private const WRITE_TIMEOUT = 25;

    /** After a failed call, the model is left alone for this long so the next turns answer at once. */
    private const DOWN_SECONDS = 60;

    private const DOWN_KEY = 'ai.response_language.provider_down';

    /** Keys whose string values are words for a person, and so are translated. */
    private const TEXT_KEYS = ['headline', 'title', 'body', 'label', 'value', 'summary', 'text', 'description', 'message', 'note', 'detail', 'reason', 'heading'];

    /** Keys holding a list of plain strings that are words for a person. */
    private const LIST_KEYS = ['lines', 'meta', 'follow_ups', 'suggestions'];

    /** Keys that are never translated: they are identifiers, or are sent back to the server as typed. */
    private const KEEP_KEYS = ['id', 'kind', 'source', 'type', 'intent', 'key', 'utterance', 'payload', 'style', 'row', 'module', 'route', 'observed_at', 'url', 'href', 'tool'];

    public function __construct(
        private readonly ModelClient $client,
        private readonly ?AiModelClientFactory $clients = null,
    ) {
    }

    // -------------------------------------------------------------- deciding

    /**
     * Which language the person is using, and which they want back.
     *
     * In order: a request made in the sentence itself ("reply in Gujarati"), the script the
     * sentence is written in, then the language a voice turn was recognised in. A sentence in plain
     * English with nothing else to go on is English.
     *
     * Every message is judged on its own. The language of the previous question or answer is
     * deliberately NOT consulted: someone who asked in Gujarati and then types an English question
     * wants English back, and a conversation that stays in a language after the person has left it
     * is the bug this rule exists to prevent.
     *
     * @param  array<string, mixed>  $options  `language` (a BCP-47 tag from voice input) and `input_mode`
     *                                         ('voice'|'text').
     * @return array{code: string, explicit: bool, question: string, translate: bool, script: string}
     */
    public function resolve(string $question, array $options = []): array
    {
        $script = self::scriptOf($question);
        $requested = self::requestedIn($question);

        $voice = ($options['input_mode'] ?? null) === 'voice' ? self::codeOf((string) ($options['language'] ?? '')) : null;

        $code = 'en';

        if ($requested !== null) {
            $code = $requested['code'];
        } elseif ($script !== 'en') {
            $code = $script;
        } elseif ($voice !== null && $voice !== 'en') {
            $code = $voice;
        }

        // What the pipeline is given: the sentence without the language request in it, unless
        // that request is all there was.
        $text = $question;

        if ($requested !== null) {
            $rest = trim(preg_replace($requested['pattern'], ' ', $question) ?? '', " \t\n\r\0\x0B,.;:!?-");

            if (mb_strlen($rest) >= 6) {
                $text = $rest;
            }
        }

        // Translation is needed when the words are not English: a non-Latin script, or a voice turn
        // recognised as Hindi/Gujarati that came out romanised ("fees pending students dikhao").
        $translate = self::scriptOf($text) !== 'en' || ($voice !== null && $voice !== 'en');

        return [
            'code' => $code,
            'explicit' => $requested !== null,
            'question' => $text,
            'translate' => $translate,
            'script' => $script,
        ];
    }

    /** The language of the characters in a sentence: hi (Devanagari), gu (Gujarati script) or en. */
    public static function scriptOf(string $text): string
    {
        $devanagari = preg_match_all('/[\x{0900}-\x{097F}]/u', $text);
        $gujarati = preg_match_all('/[\x{0A80}-\x{0AFF}]/u', $text);

        if ($gujarati > 0 && $gujarati >= $devanagari) {
            return 'gu';
        }

        return $devanagari > 0 ? 'hi' : 'en';
    }

    /** 'hi-IN' / 'hi' / 'Hindi' to 'hi'; null when it is not one of the three. */
    public static function codeOf(string $value): ?string
    {
        $value = strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        foreach (self::LANGUAGES as $code => $info) {
            if ($value === $code || str_starts_with($value, $code.'-') || $value === strtolower($info['name'])) {
                return $code;
            }
        }

        return null;
    }

    /**
     * A request about the language of the reply, made in the sentence: "answer in Hindi",
     * "switch to Gujarati", "हिंदी में बताओ", "ગુજરાતીમાં કહો", "English please".
     *
     * @return array{code: string, pattern: string}|null
     */
    private static function requestedIn(string $text): ?array
    {
        $names = [
            'en' => 'english|अंग्रेज़ी|अंग्रेजी|अँग्रेजी|अंग्रेज़ी|અંગ્રેજી',
            'hi' => 'hindi|हिंदी|हिन्दी|હિન્દી|હિંદી',
            'gu' => 'gujarati|गुजराती|ગુજરાતી',
        ];

        foreach ($names as $code => $alternatives) {
            $patterns = [
                // "reply in Hindi", "show me in Hindi", "speak to me in Hindi"
                '/\b(?:reply|respond|answer|speak|talk|say|tell|explain|show|give|write|translate)\b[^.?!]{0,30}?\b(?:in|into|using)\s+(?:'.$alternatives.')\b/iu',
                // "switch to Hindi", "use Hindi", "change language to Gujarati"
                '/\b(?:switch|change|go|move|use)\b[^.?!]{0,25}?\b(?:to|in)?\s*(?:'.$alternatives.')\b/iu',
                // "Hindi please", "Hindi only"
                '/(?:^|[\s,.])(?:'.$alternatives.')\s+(?:please|only|mein|me)\b/iu',
                // The language named in its own script: "हिंदी में", "ગુજરાતીમાં", "ગુજરાતી માં"
                '/(?:'.$alternatives.')\s*(?:में|मे|मा|માં|મા)(?=\s|$|[.,!?])/u',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    return ['code' => $code, 'pattern' => $pattern];
                }
            }
        }

        return null;
    }

    /** The BCP-47 tag a browser voice is chosen by. */
    public static function ttsTag(string $code): string
    {
        return self::LANGUAGES[$code]['tts'] ?? 'en-IN';
    }

    /**
     * The one line said when a sentence in Hindi or Gujarati could not be turned into English
     * (no model is reachable). It is a system message, not an answer: it carries no school data.
     */
    public static function unreadableNotice(string $code): string
    {
        return match ($code) {
            'hi' => 'मैं अभी आपका संदेश समझ नहीं सका क्योंकि अनुवाद सेवा उपलब्ध नहीं है। कृपया थोड़ी देर बाद फिर कोशिश करें, या अंग्रेज़ी में लिखें।',
            'gu' => 'અનુવાદ સેવા હાલ ઉપલબ્ધ ન હોવાથી હું તમારો સંદેશ સમજી શક્યો નથી. કૃપા કરીને થોડીવાર પછી ફરી પ્રયાસ કરો, અથવા અંગ્રેજીમાં લખો.',
            default => 'I could not read that message just now. Please try again, or type it in English.',
        };
    }

    // ------------------------------------------------------------------ input

    /**
     * The sentence in English, or null if it could not be translated.
     */
    public function toEnglish(string $text, string $from, ?McpRequestContext $scope = null, ?string $productModule = null): ?string
    {
        $model = $this->model($scope, $productModule);

        if ($model === null || $this->down()) {
            return null;
        }

        $language = self::LANGUAGES[$from]['name'] ?? 'Hindi or Gujarati';

        try {
            $out = $model->chat(
                [
                    ['role' => 'system', 'content' => 'You translate one chat message typed or spoken to a school ERP assistant into plain English. '
                        .'The message may be '.$language.' written in its own script or in Latin letters (romanised), or a mixture with English. '
                        .'Reply with the English sentence ONLY: no quotes, no notes, no answer to it. '
                        .'Keep names of people, class names, numbers, amounts, dates and IDs exactly as written. '
                        .'Keep the meaning of a request exactly: do not soften, extend or answer it.'],
                    ['role' => 'user', 'content' => $text],
                ],
                model: null,
                maxTokens: 400,
                temperature: 0.0,
                timeout: self::READ_TIMEOUT,
            );
        } catch (Throwable) {
            $this->markDown();

            return null;
        }

        if ($out === null) {
            $this->markDown();
        }

        $english = trim((string) $out, " \t\n\r\0\x0B\"'`");
        $english = preg_replace('/^(?:english|translation)\s*:\s*/i', '', $english) ?? $english;

        if ($english === '' || mb_strlen($english) > 1000) {
            return null;
        }

        // A reply that still contains the other scripts was not a translation.
        return self::scriptOf($english) === 'en' ? $english : null;
    }

    // ----------------------------------------------------------------- output

    /**
     * The answer's words in the person's language.
     *
     * @param  array<string, mixed>  $answer  headline, sections, actions, follow_ups as the composer makes them
     * @return array{answer: array<string, mixed>, delivered: string, translated: int, kept: int}
     */
    public function localize(array $answer, string $code, ?McpRequestContext $scope = null, ?string $productModule = null): array
    {
        if ($code === 'en' || ! isset(self::LANGUAGES[$code])) {
            return ['answer' => $answer, 'delivered' => 'en', 'translated' => 0, 'kept' => 0];
        }

        $unique = [];
        $this->collect($answer, $unique, false);
        $sources = array_keys($unique);

        if ($sources === []) {
            return ['answer' => $answer, 'delivered' => $code, 'translated' => 0, 'kept' => 0];
        }

        $model = $this->model($scope, $productModule);

        if ($model === null || $this->down()) {
            $this->note('answer left in English', ['why' => $model === null ? 'no model configured' : 'provider marked down', 'language' => $code, 'strings' => count($sources)]);

            return ['answer' => $answer, 'delivered' => 'en', 'translated' => 0, 'kept' => count($sources)];
        }

        $map = [];
        $failedBatches = 0;
        $batches = array_chunk($sources, self::BATCH);

        foreach ($batches as $batch) {
            $translated = $this->translateBatch($model, $batch, $code);

            if ($translated === null) {
                $failedBatches++;

                // The provider just failed this request; the remaining batches would only wait too.
                if ($this->down()) {
                    break;
                }

                continue;
            }

            foreach ($batch as $i => $source) {
                if ($this->preservesFigures($source, $translated[$i])) {
                    $map[$source] = $translated[$i];
                }
            }
        }

        // Nothing came back: the screen is English, and says so.
        if ($map === []) {
            $this->note('answer left in English', ['why' => 'no usable translation came back', 'language' => $code, 'strings' => count($sources), 'failed_batches' => $failedBatches, 'batches' => count($batches)]);

            return ['answer' => $answer, 'delivered' => 'en', 'translated' => 0, 'kept' => count($sources)];
        }

        $result = $answer;
        $this->collect($result, $map, true);

        return [
            'answer' => $result,
            'delivered' => $code,
            'translated' => count($map),
            'kept' => count($sources) - count($map),
        ];
    }

    /**
     * A few strings the page builds itself (a "open this page" card), in a language.
     *
     * Same rules as an answer: a line that would lose or change a figure stays as it was.
     *
     * @param  array<int, string>  $strings
     * @return array<string, string>  original => translated, only for the lines that were translated
     */
    public function localizeStrings(array $strings, string $code, ?McpRequestContext $scope = null, ?string $productModule = null): array
    {
        if ($code === 'en' || ! isset(self::LANGUAGES[$code])) {
            return [];
        }

        $sources = array_values(array_filter(
            array_unique(array_map('strval', $strings)),
            static fn (string $s): bool => self::worthTranslating($s)
        ));

        $model = $this->model($scope, $productModule);

        if ($sources === [] || $model === null || $this->down()) {
            return [];
        }

        $map = [];

        foreach (array_chunk($sources, self::BATCH) as $batch) {
            $translated = $this->translateBatch($model, $batch, $code);

            if ($translated === null) {
                break;
            }

            foreach ($batch as $i => $source) {
                if ($this->preservesFigures($source, $translated[$i])) {
                    $map[$source] = $translated[$i];
                }
            }
        }

        return $map;
    }

    /**
     * Walk the answer. Collecting gathers each distinct translatable string; applying replaces
     * them from the map.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $store
     */
    private function collect(array &$node, array &$store, bool $apply, ?string $parentKey = null): void
    {
        foreach ($node as $key => &$value) {
            if (is_string($key) && in_array($key, self::KEEP_KEYS, true)) {
                continue;
            }

            if (is_string($value)) {
                $isText = is_string($key) && in_array($key, self::TEXT_KEYS, true);
                $inList = is_int($key) && $parentKey !== null && in_array($parentKey, self::LIST_KEYS, true);

                if (($isText || $inList) && self::worthTranslating($value)) {
                    if ($apply) {
                        $value = $store[$value] ?? $value;
                    } else {
                        $store[$value] = true;
                    }
                }

                continue;
            }

            if (is_array($value)) {
                $this->collect($value, $store, $apply, is_string($key) ? $key : $parentKey);
            }
        }
    }

    /** Whether a string has words in it: letters, and not an all-capitals name or code. */
    private static function worthTranslating(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/\p{L}/u', $value)) {
            return false;
        }

        // "DHRUV JITEN MAHYAVANSHI", "MARYA FIROZ KHAN PATHAN": names as the student records hold them.
        if (preg_match('/^[A-Z][A-Z .\'\-]+$/', $value)) {
            return false;
        }

        // Already not English.
        return self::scriptOf($value) === 'en';
    }

    /**
     * @param  array<int, string>  $strings
     * @return array<int, string>|null
     */
    private function translateBatch(ModelClient $model, array $strings, string $code): ?array
    {
        $language = self::LANGUAGES[$code];

        try {
            $raw = $model->chat(
                [
                    ['role' => 'system', 'content' => 'You translate the wording of a school ERP assistant\'s answer into '.$language['name'].' ('.$language['script'].' script). '
                        .'You receive {"strings":[...]} and reply with {"strings":[...]}: the same number of strings, in the same order, each translated. '
                        .'RULES: keep every number, amount, currency symbol, percentage, date, ID, masked phone number and email EXACTLY as written, using Western digits (0-9). '
                        .'Translate the ordinary words around identifiers: "Student ID 1116490" becomes the words for "Student ID" in that language followed by 1116490, and "Class 6" becomes the word for "Class" followed by 6; "Owes ₹34,814" and "SMS to ******2940" are translated the same way, with the figure or masked number kept as is. '
                        .'Never translate or transliterate the names of people or product/module names that are proper nouns. '
                        .'Keep punctuation such as bullets and the order of any "label: value" pair. Do not add, remove, explain or answer anything. '
                        .'Write natural, polite, simple '.$language['name'].' that a school administrator would use.'],
                    ['role' => 'user', 'content' => json_encode(['strings' => array_values($strings)], JSON_UNESCAPED_UNICODE)],
                ],
                model: null,
                maxTokens: 6000,
                temperature: 0.0,
                expectJson: true,
                timeout: self::WRITE_TIMEOUT,
            );
        } catch (Throwable) {
            $this->markDown();

            return null;
        }

        if ($raw === null) {
            $this->markDown();

            return null;
        }

        $reply = self::decode((string) $raw);
        $out = is_array($reply['strings'] ?? null) ? array_values($reply['strings']) : null;

        if ($out === null || count($out) !== count($strings)) {
            $this->note('translation reply unusable', ['why' => $out === null ? 'not JSON with a strings list' : 'wrong number of strings', 'sent' => count($strings), 'got' => $out === null ? null : count($out), 'reply_chars' => strlen((string) $raw)]);

            return null;
        }

        foreach ($out as $i => $line) {
            if (! is_string($line) || trim($line) === '') {
                $out[$i] = $strings[$i];
            }
        }

        return $out;
    }

    /**
     * Every number in the English must still be in the translation, and nothing the translation
     * adds may look like a figure that was not there.
     */
    private function preservesFigures(string $source, string $translated): bool
    {
        preg_match_all('/\d[\d,.]*/', $source, $from);
        preg_match_all('/\d[\d,.]*/', $translated, $to);

        $have = array_map(static fn (string $n): string => rtrim($n, ',.'), $to[0]);

        foreach ($from[0] as $figure) {
            if (! in_array(rtrim($figure, ',.'), $have, true)) {
                return false;
            }
        }

        $known = array_map(static fn (string $n): string => rtrim($n, ',.'), $from[0]);

        foreach ($have as $figure) {
            if (! in_array($figure, $known, true)) {
                return false;
            }
        }

        // Masked contacts and IDs written with letters, such as "j***@yahoo.com", must survive whole.
        preg_match_all('/\S*[*@]\S*/u', $source, $special);

        foreach ($special[0] as $token) {
            if (! str_contains($translated, rtrim($token, '.,;:)')) ) {
                return false;
            }
        }

        return true;
    }

    /** A model's JSON reply, tolerating the code fence some models wrap it in. */
    private static function decode(string $raw): ?array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw) ?? $raw;
        $decoded = json_decode($raw, true);

        if (! is_array($decoded) && preg_match('/\{.*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
        }

        return is_array($decoded) ? $decoded : null;
    }

    /** A line in the application log: what happened and how much, never what was said. */
    private function note(string $message, array $context): void
    {
        try {
            Log::warning('ResponseLanguage: '.$message, $context);
        } catch (Throwable) {
            // Logging must never be the reason a chat turn fails.
        }
    }

    private function down(): bool
    {
        try {
            return (bool) Cache::get(self::DOWN_KEY, false);
        } catch (Throwable) {
            return false;
        }
    }

    private function markDown(): void
    {
        try {
            Cache::put(self::DOWN_KEY, true, self::DOWN_SECONDS);
        } catch (Throwable) {
            // Without a cache the next call simply tries the model again.
        }
    }

    private function model(?McpRequestContext $scope, ?string $productModule): ?ModelClient
    {
        $client = $this->clients?->for(self::MODULE, $scope?->selectedInstituteId, $productModule) ?? $this->client;

        if (! $client->isConfigured()) {
            return null;
        }

        // When the configured Gemini model is out of quota or overloaded, a sibling model of the same
        // provider and key reads the sentence instead of the person being told to try again later.
        return $this->withFallbacks($client, $scope, $productModule);
    }

    private function withFallbacks(ModelClient $client, ?McpRequestContext $scope, ?string $productModule): ModelClient
    {
        if ($this->clients === null) {
            return $client;
        }

        try {
            $provider = $this->clients->configurationFor(self::MODULE, $scope?->selectedInstituteId, $productModule)->provider ?? null;
            $names = (array) config('ai.language.fallback_models', self::FALLBACK_MODELS);
        } catch (Throwable) {
            return $client;
        }

        $names = array_values(array_filter(array_map('strval', $names)));

        return $provider === 'gemini' && $names !== [] ? new FallbackModelClient($client, $names) : $client;
    }
}
