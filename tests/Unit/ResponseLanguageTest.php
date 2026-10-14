<?php

namespace Tests\Unit;

use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use App\Domain\AI\Conversation\ResponseLanguage;
use App\Domain\AI\Support\ModelClient;
use PHPUnit\Framework\TestCase;

/**
 * English, Hindi and Gujarati in, and the same language out.
 *
 * The model is a stand-in that answers from a table the test sets up, so what is under test is
 * the layer: which language is chosen, what is handed to the model, what is kept in English and
 * what is refused. No real translation quality is claimed here.
 */
class ResponseLanguageTest extends TestCase
{
    private function model(bool $configured = true, ?\Closure $chat = null, ?\Closure $json = null): ModelClient
    {
        return new class($configured, $chat, $json) implements ModelClient {
            /** @var array<int, array<string, mixed>> */
            public array $calls = [];

            public function __construct(private bool $configured, private ?\Closure $chat, private ?\Closure $json)
            {
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function forInstitute(int|string|null $subInstituteId): static
            {
                return $this;
            }

            public function withConfiguration(ResolvedAiConfiguration $configuration): static
            {
                return $this;
            }

            public function defaultModel(): string
            {
                return 'fake';
            }

            public function chat(array $messages, ?string $model = null, ?int $maxTokens = null, ?float $temperature = null, bool $expectJson = false, ?int $timeout = null): ?string
            {
                $this->calls[] = [$expectJson ? 'json' : 'chat', $messages, $timeout];

                if ($expectJson) {
                    $reply = $this->json === null ? null : ($this->json)($messages);

                    return $reply === null ? null : json_encode($reply, JSON_UNESCAPED_UNICODE);
                }

                return $this->chat === null ? null : ($this->chat)($messages);
            }

            public function stream(array $messages, callable $onDelta, ?string $model = null, ?int $maxTokens = null, ?float $temperature = null): ?string
            {
                return null;
            }

            public function json(array $messages, ?string $model = null, int $maxTokens = 900, float $temperature = 0.0): ?array
            {
                throw new \LogicException('The language layer asks for JSON through chat(), which can be given a timeout.');
            }
        };
    }

    private function layer(?ModelClient $model = null): ResponseLanguage
    {
        return new ResponseLanguage($model ?? $this->model());
    }

    // ------------------------------------------------------------- choosing

    public function test_the_script_of_the_sentence_decides_the_language(): void
    {
        $layer = $this->layer();

        $this->assertSame('en', $layer->resolve('Show students with pending fees')['code']);
        $this->assertSame('hi', $layer->resolve('बकाया फीस वाले छात्र दिखाओ')['code']);
        $this->assertSame('gu', $layer->resolve('બાકી ફી ધરાવતા વિદ્યાર્થીઓ બતાવો')['code']);
        $this->assertSame('hi', ResponseLanguage::scriptOf('मुझे 5 students दिखाओ'));
    }

    public function test_a_plain_english_sentence_needs_no_translation(): void
    {
        $r = $this->layer()->resolve('Show students with pending fees');

        $this->assertFalse($r['translate']);
        $this->assertFalse($r['explicit']);
        $this->assertSame('Show students with pending fees', $r['question']);
    }

    public function test_a_hindi_or_gujarati_sentence_is_translated_before_the_pipeline_reads_it(): void
    {
        $this->assertTrue($this->layer()->resolve('बकाया फीस दिखाओ')['translate']);
        $this->assertTrue($this->layer()->resolve('બાકી ફી બતાવો')['translate']);
    }

    public function test_a_voice_turn_recognised_as_hindi_but_romanised_is_still_translated(): void
    {
        $r = $this->layer()->resolve('pending fees wale students dikhao', ['input_mode' => 'voice', 'language' => 'hi-IN']);

        $this->assertSame('hi', $r['code']);
        $this->assertTrue($r['translate']);
    }

    public function test_the_voice_hint_does_not_apply_to_typed_text(): void
    {
        $r = $this->layer()->resolve('Show students with pending fees', ['language' => 'hi-IN']);

        $this->assertSame('en', $r['code']);
        $this->assertFalse($r['translate']);
    }

    public function test_english_spoken_while_the_hint_is_english_stays_english(): void
    {
        $r = $this->layer()->resolve('Show students with pending fees', ['input_mode' => 'voice', 'language' => 'en-IN']);

        $this->assertSame('en', $r['code']);
        $this->assertFalse($r['translate']);
    }

    public function test_asking_for_a_language_switches_the_reply_and_leaves_the_question_clean(): void
    {
        $layer = $this->layer();

        $hi = $layer->resolve('Show students with pending fees, reply in Hindi');
        $this->assertSame('hi', $hi['code']);
        $this->assertTrue($hi['explicit']);
        $this->assertStringNotContainsStringIgnoringCase('hindi', $hi['question']);
        $this->assertStringContainsString('pending fees', $hi['question']);
        $this->assertFalse($hi['translate'], 'the question itself is English');

        $this->assertSame('gu', $layer->resolve('Answer in Gujarati please')['code']);
        $this->assertSame('en', $layer->resolve('switch to English')['code']);
        $this->assertSame('hi', $layer->resolve('बकाया फीस हिंदी में बताओ')['code']);
        $this->assertSame('gu', $layer->resolve('બાકી ફી ગુજરાતીમાં કહો')['code']);
        $this->assertSame('en', $layer->resolve('बकाया फीस अंग्रेजी में बताओ')['code'], 'asked in Hindi for an English reply');
    }

    public function test_a_sentence_that_is_only_the_request_is_kept_so_the_pipeline_has_something_to_answer(): void
    {
        $r = $this->layer()->resolve('Reply in Hindi');

        $this->assertSame('hi', $r['code']);
        $this->assertSame('Reply in Hindi', $r['question']);
    }

    public function test_every_message_is_judged_on_its_own_and_the_previous_language_never_carries_over(): void
    {
        $layer = $this->layer();

        // The sequence of a real conversation: English, Gujarati, Hindi, English.
        $sequence = [
            'Show students with pending fees.' => 'en',
            'હવે આ વિદ્યાર્થીઓની વિગત બતાવો.' => 'gu',
            'इन छात्रों की फीस का विवरण दिखाओ।' => 'hi',
            'Show the reminder status.' => 'en',
            'જે વિદ્યાર્થીઓની બાકી હોય તેની યાદી બતાવો' => 'gu',
            'Which students have not paid?' => 'en',
        ];

        foreach ($sequence as $sentence => $expected) {
            $this->assertSame($expected, $layer->resolve($sentence)['code'], $sentence);
        }

        // An old build of the page may still send a remembered language; it has no say.
        $this->assertSame('en', $layer->resolve('Show the reminder status.', ['preferred_language' => 'gu'])['code']);
    }

    public function test_a_voice_hint_applies_to_that_turn_only(): void
    {
        $layer = $this->layer();

        $this->assertSame('gu', $layer->resolve('બાકી ફી બતાવો', ['input_mode' => 'voice', 'language' => 'gu-IN'])['code']);
        // The same hint on a transcript that is plainly English and typed: English.
        $this->assertSame('en', $layer->resolve('Show the reminder status.', ['input_mode' => 'text', 'language' => 'gu-IN'])['code']);
        // And spoken English with the English recogniser: English.
        $this->assertSame('en', $layer->resolve('Show the reminder status.', ['input_mode' => 'voice', 'language' => 'en-IN'])['code']);
    }

    public function test_fixed_screen_wording_is_translated_without_touching_figures(): void
    {
        $model = $this->model(true, null, fn (array $m) => ['strings' => array_map(
            fn ($s) => match ($s) {
                'Fee collection ready' => 'ફી વસૂલાત તૈયાર છે',
                'Open the collection page with 12 students selected.' => 'ફી વસૂલાતનું પેજ 99 વિદ્યાર્થીઓ સાથે ખોલો.',
                default => $s,
            },
            json_decode($m[1]['content'], true)['strings']
        )]);

        $map = $this->layer($model)->localizeStrings(
            ['Fee collection ready', 'Open the collection page with 12 students selected.', '₹4,500', 'FEES'],
            'gu'
        );

        $this->assertSame('ફી વસૂલાત તૈયાર છે', $map['Fee collection ready']);
        // The translation changed 12 into 99: rejected, so the caller keeps its own English.
        $this->assertArrayNotHasKey('Open the collection page with 12 students selected.', $map);
        // Nothing to translate in a bare figure or an all-capitals label.
        $this->assertArrayNotHasKey('₹4,500', $map);
        $this->assertArrayNotHasKey('FEES', $map);
        $this->assertSame([], $this->layer($model)->localizeStrings(['Fee collection ready'], 'en'));
        $this->assertSame([], $this->layer($this->model(false))->localizeStrings(['Fee collection ready'], 'gu'));
    }
    public function test_codes_and_speech_tags(): void
    {
        $this->assertSame('hi', ResponseLanguage::codeOf('hi-IN'));
        $this->assertSame('gu', ResponseLanguage::codeOf('Gujarati'));
        $this->assertNull(ResponseLanguage::codeOf('fr-FR'));
        $this->assertSame('gu-IN', ResponseLanguage::ttsTag('gu'));
        $this->assertSame('en-IN', ResponseLanguage::ttsTag('anything'));
    }

    // ---------------------------------------------------------------- input

    public function test_the_model_is_asked_for_plain_english_and_its_answer_is_used(): void
    {
        $model = $this->model(true, fn (array $m) => 'Show students with pending fees');
        $english = $this->layer($model)->toEnglish('बकाया फीस वाले छात्र दिखाओ', 'hi');

        $this->assertSame('Show students with pending fees', $english);
        $this->assertStringContainsString('English', $model->calls[0][1][0]['content']);
        $this->assertSame('बकाया फीस वाले छात्र दिखाओ', $model->calls[0][1][1]['content']);
    }

    public function test_a_translation_that_is_still_in_the_other_script_is_not_used(): void
    {
        $model = $this->model(true, fn () => 'बकाया फीस');

        $this->assertNull($this->layer($model)->toEnglish('बकाया फीस', 'hi'));
    }

    public function test_no_model_means_no_translation_and_no_exception(): void
    {
        $this->assertNull($this->layer($this->model(false))->toEnglish('बकाया फीस', 'hi'));
        $this->assertNull($this->layer($this->model(true, fn () => throw new \RuntimeException('quota')))->toEnglish('बकाया फीस', 'hi'));
    }

    // --------------------------------------------------------------- output

    /** @return array<string, mixed> */
    private function answer(): array
    {
        return [
            'headline' => 'MARYA FIROZ KHAN PATHAN owes ₹34,814, unpaid for 1 month.',
            'sections' => [
                ['type' => 'key_values', 'title' => 'Student', 'items' => [
                    ['label' => 'Class', 'value' => 'Class 6'],
                    ['label' => 'Name', 'value' => 'MARYA FIROZ KHAN PATHAN'],
                ]],
                ['type' => 'records', 'title' => 'Unpaid fee months', 'items' => [
                    ['title' => 'Apr/2022', 'lines' => ['Owes ₹34,814'], 'meta' => []],
                ]],
            ],
            'actions' => [['key' => 'a', 'label' => 'Collect fees', 'intent' => 'fees_collect', 'utterance' => 'Open fee collection', 'payload' => ['student_id' => 1116501]]],
            'follow_ups' => ['Send an SMS fee reminder to this student.'],
        ];
    }

    private function translating(): ModelClient
    {
        // A tiny table is the "model": it translates the words and leaves figures and names alone.
        $table = [
            'Student' => 'विद्यार्थी', 'Class' => 'कक्षा', 'Unpaid fee months' => 'बकाया फीस के महीने',
            'Owes ₹34,814' => '₹34,814 बकाया', 'Collect fees' => 'फीस जमा करें',
            'Send an SMS fee reminder to this student.' => 'इस विद्यार्थी को एसएमएस फीस रिमाइंडर भेजें।',
            'Apr/2022' => 'अप्रैल/2022', 'Class 6' => 'कक्षा 6',
            'MARYA FIROZ KHAN PATHAN owes ₹34,814, unpaid for 1 month.' => 'MARYA FIROZ KHAN PATHAN पर ₹34,814 बकाया है, 1 महीने से अवैतनिक।',
        ];

        return $this->model(true, null, function (array $messages) use ($table): array {
            $in = json_decode($messages[1]['content'], true)['strings'];

            return ['strings' => array_map(fn ($s) => $table[$s] ?? $s, $in)];
        });
    }

    public function test_the_words_of_an_answer_are_translated_and_everything_else_is_left_alone(): void
    {
        $out = $this->layer($this->translating())->localize($this->answer(), 'hi');
        $a = $out['answer'];

        $this->assertSame('hi', $out['delivered']);
        $this->assertStringContainsString('₹34,814', $a['headline']);
        $this->assertStringContainsString('बकाया', $a['headline']);
        $this->assertStringContainsString('MARYA FIROZ KHAN PATHAN', $a['headline'], 'a name is not translated');
        $this->assertSame('विद्यार्थी', $a['sections'][0]['title']);
        $this->assertSame('कक्षा', $a['sections'][0]['items'][0]['label']);
        $this->assertSame('MARYA FIROZ KHAN PATHAN', $a['sections'][0]['items'][1]['value'], 'an all-capitals name is kept');
        $this->assertSame('₹34,814 बकाया', $a['sections'][1]['items'][0]['lines'][0]);
        // What is sent back to the server is as typed: the utterance, the payload, the keys.
        $this->assertSame('Open fee collection', $a['actions'][0]['utterance']);
        $this->assertSame(['student_id' => 1116501], $a['actions'][0]['payload']);
        $this->assertSame('fees_collect', $a['actions'][0]['intent']);
        $this->assertSame('फीस जमा करें', $a['actions'][0]['label']);
        $this->assertSame('इस विद्यार्थी को एसएमएस फीस रिमाइंडर भेजें।', $a['follow_ups'][0]);
    }

    public function test_an_all_capitals_name_is_never_sent_to_the_model(): void
    {
        $model = $this->translating();
        $this->layer($model)->localize($this->answer(), 'hi');

        $sent = json_decode($model->calls[0][1][1]['content'], true)['strings'];

        $this->assertNotContains('MARYA FIROZ KHAN PATHAN', $sent);
        $this->assertSame(count($sent), count(array_unique($sent)), 'each distinct string is sent once');
    }

    public function test_a_translation_that_changes_a_figure_is_rejected_and_that_line_stays_english(): void
    {
        $model = $this->model(true, null, fn (array $m) => ['strings' => array_map(
            fn ($s) => str_contains($s, '₹34,814') ? str_replace('34,814', '43,814', $s) : 'अनुवादित',
            json_decode($m[1]['content'], true)['strings']
        )]);

        $a = $this->layer($model)->localize($this->answer(), 'hi')['answer'];

        $this->assertStringContainsString('₹34,814', $a['headline']);
        $this->assertStringNotContainsString('43,814', json_encode($a, JSON_UNESCAPED_UNICODE));
        $this->assertSame('अनुवादित', $a['sections'][0]['title'], 'lines without figures are translated');
    }

    public function test_masked_contacts_must_survive_translation_whole(): void
    {
        $answer = ['headline' => 'Reminder sent to ******2940 and j***@yahoo.com.', 'sections' => [], 'actions' => [], 'follow_ups' => []];
        $model = $this->model(true, null, fn (array $m) => ['strings' => ['रिमाइंडर ****2940 और j***@yahoo.com पर भेजा गया।']]);

        $this->assertSame($answer['headline'], $this->layer($model)->localize($answer, 'hi')['answer']['headline']);
    }

    public function test_without_a_model_the_answer_stays_english_and_says_so(): void
    {
        $out = $this->layer($this->model(false))->localize($this->answer(), 'gu');

        $this->assertSame('en', $out['delivered']);
        $this->assertSame($this->answer(), $out['answer']);
    }

    public function test_a_model_that_fails_or_miscounts_leaves_the_answer_english(): void
    {
        foreach ([fn () => null, fn () => ['strings' => ['only one']], fn () => throw new \RuntimeException('quota')] as $json) {
            $out = $this->layer($this->model(true, null, $json))->localize($this->answer(), 'hi');

            $this->assertSame('en', $out['delivered']);
            $this->assertSame($this->answer(), $out['answer']);
        }
    }

    public function test_english_is_returned_untouched_without_calling_the_model(): void
    {
        $model = $this->translating();
        $out = $this->layer($model)->localize($this->answer(), 'en');

        $this->assertSame($this->answer(), $out['answer']);
        $this->assertSame([], $model->calls);
    }

    public function test_the_unreadable_notice_exists_in_each_language_and_carries_no_data(): void
    {
        foreach (['en', 'hi', 'gu'] as $code) {
            $notice = ResponseLanguage::unreadableNotice($code);

            $this->assertNotSame('', $notice);
            $this->assertSame($code, ResponseLanguage::scriptOf($notice));
        }
    }
}
