<?php

namespace Tests\Unit;

use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use App\Domain\AI\Conversation\AskAuditor;
use App\Domain\AI\Conversation\AskPipeline;
use App\Domain\AI\Conversation\AskService;
use App\Domain\AI\Conversation\ResponseLanguage;
use App\Domain\AI\Lifecycle\LifecycleAskService;
use App\Domain\AI\Support\ModelClient;
use App\Services\Mcp\McpRequestContext;
use Mockery;
use Tests\TestCase;

/**
 * A sentence in Hindi or Gujarati reaches the pipeline as English, and the answer comes back in
 * the language it was asked in, with the language reported so a client can speak it.
 *
 * The pipeline itself is replaced by a stub that records what it was asked and returns an English
 * answer; the model is a stand-in. What is under test is the wiring around the pipeline.
 */
class AskPipelineLanguageTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function scope(): McpRequestContext
    {
        return new McpRequestContext(1, 'admin', 61, [61], null, null, 2026, null, true, false);
    }

    /** @return array<string, mixed> */
    private function english(): array
    {
        return [
            'conversation' => ['id' => 7],
            'question' => 'x',
            'answer' => ['headline' => '10 students owe fees.', 'sections' => [], 'actions' => [], 'follow_ups' => ['Show reminder status.']],
            'answer_source' => 'stages',
        ];
    }

    /**
     * @param  array<string, string>  $translate  English => target-language text, for both directions
     * @return array{0: AskPipeline, 1: object}
     */
    private function pipeline(array $translate, bool $configured = true): array
    {
        $seen = new class {
            /** @var array<int, string> */
            public array $asked = [];

            public bool $tokens = true;
        };

        $lifecycle = Mockery::mock(LifecycleAskService::class);
        $lifecycle->shouldReceive('ask')->andReturnUsing(function (string $q, $scope, $cid, $options, $onStage, $onToken) use ($seen) {
            $seen->asked[] = $q;
            $seen->tokens = $onToken !== null;

            return $this->english();
        });

        $audit = Mockery::mock(AskAuditor::class);
        $audit->shouldReceive('turn')->andReturnNull();

        $model = new class($translate, $configured) implements ModelClient {
            public function __construct(private array $table, private bool $configured)
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
                if ($expectJson) {
                    $in = json_decode($messages[1]['content'], true)['strings'];

                    return json_encode(['strings' => array_map(fn ($s) => $this->table[$s] ?? $s, $in)], JSON_UNESCAPED_UNICODE);
                }

                return $this->table[$messages[1]['content']] ?? null;
            }

            public function stream(array $messages, callable $onDelta, ?string $model = null, ?int $maxTokens = null, ?float $temperature = null): ?string
            {
                return null;
            }

            public function json(array $messages, ?string $model = null, int $maxTokens = 900, float $temperature = 0.0): ?array
            {
                throw new \LogicException('not used');
            }
        };

        return [new AskPipeline($lifecycle, Mockery::mock(AskService::class), $audit, new ResponseLanguage($model)), $seen];
    }

    public function test_hindi_in_english_to_the_pipeline_hindi_back(): void
    {
        [$pipeline, $seen] = $this->pipeline([
            'बकाया फीस वाले छात्र दिखाओ' => 'Show students with pending fees',
            '10 students owe fees.' => '10 विद्यार्थियों पर फीस बकाया है।',
            'Show reminder status.' => 'रिमाइंडर की स्थिति दिखाओ।',
        ]);

        $result = $pipeline->ask('बकाया फीस वाले छात्र दिखाओ', $this->scope(), null, ['input_mode' => 'voice', 'language' => 'hi-IN'], null, fn () => null);

        $this->assertSame(['Show students with pending fees'], $seen->asked);
        $this->assertFalse($seen->tokens, 'English tokens are not streamed into a Hindi answer');
        $this->assertSame('10 विद्यार्थियों पर फीस बकाया है।', $result['answer']['headline']);
        $this->assertSame(['रिमाइंडर की स्थिति दिखाओ।'], $result['answer']['follow_ups']);
        $this->assertSame('hi', $result['language']['code']);
        $this->assertSame('hi', $result['language']['delivered']);
        $this->assertSame('hi-IN', $result['language']['tts']);
        $this->assertTrue($result['language']['translated_input']);
        $this->assertSame('बकाया फीस वाले छात्र दिखाओ', $result['language']['question_original']);
        $this->assertSame('Show students with pending fees', $result['language']['question_english']);
    }

    public function test_gujarati_uses_the_gujarati_voice_tag(): void
    {
        [$pipeline] = $this->pipeline([
            'બાકી ફી બતાવો' => 'Show pending fees',
            '10 students owe fees.' => '10 વિદ્યાર્થીઓની ફી બાકી છે.',
        ]);

        $result = $pipeline->ask('બાકી ફી બતાવો', $this->scope(), null, ['input_mode' => 'voice', 'language' => 'gu-IN']);

        $this->assertSame('gu', $result['language']['delivered']);
        $this->assertSame('gu-IN', $result['language']['tts']);
        $this->assertSame('10 વિદ્યાર્થીઓની ફી બાકી છે.', $result['answer']['headline']);
    }

    public function test_english_is_untouched_and_still_streams(): void
    {
        [$pipeline, $seen] = $this->pipeline([]);

        $result = $pipeline->ask('Show students with pending fees', $this->scope(), null, [], null, fn () => null);

        $this->assertSame(['Show students with pending fees'], $seen->asked);
        $this->assertTrue($seen->tokens);
        $this->assertSame('10 students owe fees.', $result['answer']['headline']);
        $this->assertSame('en', $result['language']['delivered']);
        $this->assertFalse($result['language']['translated_input']);
    }

    public function test_switching_language_mid_conversation_follows_each_sentence(): void
    {
        [$pipeline] = $this->pipeline([
            'बकाया फीस दिखाओ' => 'Show pending fees',
            '10 students owe fees.' => '10 विद्यार्थियों पर फीस बकाया है।',
        ]);

        $first = $pipeline->ask('बकाया फीस दिखाओ', $this->scope());
        $second = $pipeline->ask('Show pending fees', $this->scope());
        $third = $pipeline->ask('Show pending fees in Hindi', $this->scope());

        $this->assertSame('hi', $first['language']['delivered']);
        $this->assertSame('en', $second['language']['delivered']);
        $this->assertSame('10 students owe fees.', $second['answer']['headline']);
        $this->assertSame('hi', $third['language']['delivered']);
        $this->assertTrue($third['language']['explicit']);
    }

    public function test_without_a_model_a_hindi_question_gets_one_honest_line_in_hindi(): void
    {
        [$pipeline] = $this->pipeline([], configured: false);

        // The stub pipeline reports the refusal the real one gives when it cannot read a sentence.
        $lifecycle = Mockery::mock(LifecycleAskService::class);
        $lifecycle->shouldReceive('ask')->andReturn(['answer' => ['headline' => 'Nothing to report for that question.', 'sections' => [['type' => 'text']], 'actions' => [], 'follow_ups' => ['x']], 'answer_source' => 'fallback']);
        $audit = Mockery::mock(AskAuditor::class);
        $audit->shouldReceive('turn')->andReturnNull();
        $model = Mockery::mock(ModelClient::class);
        $model->shouldReceive('isConfigured')->andReturn(false);
        $pipeline = new AskPipeline($lifecycle, Mockery::mock(AskService::class), $audit, new ResponseLanguage($model));

        $result = $pipeline->ask('बकाया फीस दिखाओ', $this->scope());

        $this->assertSame(ResponseLanguage::unreadableNotice('hi'), $result['answer']['headline']);
        $this->assertSame([], $result['answer']['sections']);
        $this->assertSame('hi', $result['language']['delivered']);
        $this->assertFalse($result['language']['translated_input']);
    }

    public function test_without_a_model_an_english_question_is_unaffected(): void
    {
        [$pipeline] = $this->pipeline([], configured: false);

        $result = $pipeline->ask('Show students with pending fees', $this->scope());

        $this->assertSame('10 students owe fees.', $result['answer']['headline']);
    }

    public function test_the_pipeline_the_application_actually_serves_has_the_language_layer(): void
    {
        // The service provider builds AskPipeline by hand, so a constructor argument added to the
        // class is not filled in by autowiring. This is the check that it was passed on.
        $pipeline = $this->app->make(AskPipeline::class);

        $layer = (new \ReflectionProperty($pipeline, 'language'))->getValue($pipeline);

        $this->assertInstanceOf(ResponseLanguage::class, $layer);
    }

    public function test_a_pipeline_built_without_the_language_layer_behaves_as_before(): void
    {
        $lifecycle = Mockery::mock(LifecycleAskService::class);
        $lifecycle->shouldReceive('ask')->andReturn($this->english());
        $audit = Mockery::mock(AskAuditor::class);
        $audit->shouldReceive('turn')->andReturnNull();

        $result = (new AskPipeline($lifecycle, Mockery::mock(AskService::class), $audit))->ask('बकाया फीस दिखाओ', $this->scope());

        $this->assertArrayNotHasKey('language', $result);
    }
}
