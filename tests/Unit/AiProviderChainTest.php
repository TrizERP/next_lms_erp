<?php

namespace Tests\Unit;

use App\Domain\AI\Configuration\AiConfigurationException;
use App\Domain\AI\Configuration\AiConfigurationResolver;
use App\Domain\AI\Configuration\AiModuleRegistry;
use App\Domain\AI\Configuration\ProviderCapabilities;
use App\Domain\AI\Configuration\ProviderCompatibility;
use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use App\Domain\AI\Support\AiProviderException;
use App\Domain\AI\Support\FallbackChainModelClient;
use App\Domain\AI\Support\ModelClient;
use App\Domain\AI\Support\SchemaCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Provider chains: ranking, capability filtering, tenant isolation, no environment fallback, and
 * fallback between providers.
 *
 * Runs against a throw-away in-memory SQLite database built here, never the shared MySQL one, and
 * calls no provider: the legs of the fallback chain are in-test fakes, because what is under test is
 * the chain's own decisions (when to move on, what to record), not a vendor's behaviour.
 */
class AiProviderChainTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.chain_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.default' => 'chain_test',
            'cache.default' => 'array',
            'ai.provider.driver' => 'gemini',
            'ai.provider.gemini.api_key' => 'ENV-GEMINI-KEY',
            'ai.provider.openrouter.api_key' => 'ENV-OPENROUTER-KEY',
            'ai.allow_env_credentials' => false,
        ]);

        DB::purge('chain_test');

        Schema::create('ai_api_keys', function ($t) {
            $t->bigIncrements('id');
            $t->string('account_email')->nullable();
            $t->string('api_type')->nullable();
            $t->string('ai_module')->nullable();
            $t->string('model')->nullable();
            $t->unsignedSmallInteger('priority')->nullable();
            $t->text('api_key');
            $t->string('api_limit')->nullable();
            $t->integer('status')->default(1);
            $t->unsignedBigInteger('sub_institute_id')->nullable();
            $t->timestamps();
        });

        Schema::create('ai_models', function ($t) {
            $t->bigIncrements('id');
            $t->string('provider');
            $t->string('model_id');
            $t->string('label');
            $t->unsignedInteger('max_output_tokens')->nullable();
            $t->decimal('input_cost_per_1k', 12, 6)->nullable();
            $t->decimal('output_cost_per_1k', 12, 6)->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->integer('status')->default(1);
            $t->unsignedBigInteger('sub_institute_id')->nullable();
            $t->timestamps();
        });

        Schema::create('ai_usage_events', function ($t) {
            $t->id();
            $t->string('product')->nullable();
            $t->string('ai_module')->nullable();
            $t->string('provider')->nullable();
            $t->string('model')->nullable();
            $t->string('source')->nullable();
            $t->integer('input_tokens')->nullable();
            $t->integer('output_tokens')->nullable();
            $t->integer('latency_ms')->nullable();
            $t->decimal('estimated_cost_usd', 10, 6)->nullable();
            $t->string('outcome')->nullable();
            $t->string('finish_reason')->nullable();
            $t->text('error')->nullable();
            $t->string('related_type')->nullable();
            $t->unsignedBigInteger('related_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('sub_institute_id')->nullable();
            $t->timestamps();
        });

        $this->forgetSingletons();
    }

    /** The resolver holds per-request memos; a test that writes rows needs a fresh one. */
    private function forgetSingletons(): void
    {
        app(SchemaCache::class)->flush();
        app()->forgetInstance(AiConfigurationResolver::class);
        app()->forgetInstance(ProviderCompatibility::class);
    }

    private function row(array $attrs): int
    {
        $id = DB::table('ai_api_keys')->insertGetId($attrs + [
            'api_key' => 'k-' . bin2hex(random_bytes(6)),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->forgetSingletons();

        return $id;
    }

    private function providers(?string $module, $institute = null): array
    {
        return array_map(
            fn (ResolvedAiConfiguration $c) => $c->provider,
            app(AiConfigurationResolver::class)->resolveChain($module, $institute)
        );
    }

    // ---- ranking ---------------------------------------------------------

    public function test_pool_orders_by_priority_and_uses_the_driver_only_as_a_tiebreak(): void
    {
        $this->row(['api_type' => 'OPENROUTER_API_KEY']);
        $this->row(['api_type' => 'gemini']);

        // Nobody ranked anything: the configured driver (gemini) is listed first.
        $this->assertSame(['gemini', 'openrouter'], $this->providers('agent_reasoning'));

        // Once a priority exists it decides, whatever the environment says.
        DB::table('ai_api_keys')->where('api_type', 'OPENROUTER_API_KEY')->update(['priority' => 1]);
        $this->forgetSingletons();

        $this->assertSame(['openrouter', 'gemini'], $this->providers('agent_reasoning'));
    }

    public function test_module_rows_are_ranked_and_a_schools_row_beats_the_platforms(): void
    {
        $this->row(['api_type' => 'gemini', 'ai_module' => 'agent_reasoning', 'priority' => 2]);
        $this->row(['api_type' => 'OPENROUTER_API_KEY', 'ai_module' => 'agent_reasoning', 'priority' => 1]);

        $this->assertSame(['openrouter', 'gemini'], $this->providers('agent_reasoning'));

        // A school's own gemini row shadows the platform's for that provider and goes first.
        $this->row(['api_type' => 'gemini', 'ai_module' => 'agent_reasoning', 'sub_institute_id' => 7, 'api_key' => 'SCHOOL-7']);

        $chain = app(AiConfigurationResolver::class)->resolveChain('agent_reasoning', 7);

        $this->assertSame(['gemini', 'openrouter'], array_map(fn ($c) => $c->provider, $chain));
        $this->assertSame('SCHOOL-7', $chain[0]->apiKey);
        $this->assertSame('module', $chain[0]->source);
    }

    // ---- capability ------------------------------------------------------

    public function test_a_provider_that_cannot_do_the_job_is_skipped(): void
    {
        $this->row(['api_type' => 'OPENROUTER_API_KEY']);
        $this->row(['api_type' => 'gemini']);

        // Evaluation needs vision; only the Gemini client implements it.
        $this->assertSame(['gemini'], $this->providers('evaluation_ai'));
        $this->assertSame(['gemini'], $this->providers('image_generation'));
        $this->assertSame(['gemini', 'openrouter'], $this->providers('agent_reasoning'));
    }

    public function test_capabilities_come_from_the_client_class(): void
    {
        $caps = app(ProviderCapabilities::class);

        $this->assertContains('vision', $caps->forProvider('gemini'));
        $this->assertNotContains('vision', $caps->forProvider('openrouter'));
        $this->assertSame([], $caps->forProvider('anthropic'));
    }

    public function test_saving_an_incapable_provider_is_refused_with_a_reason(): void
    {
        $compat = app(ProviderCompatibility::class);

        $refusals = $compat->refusals('evaluation_ai', 'openai', null);

        $this->assertCount(1, $refusals);
        $this->assertSame('provider', $refusals[0]['field']);
        $this->assertStringContainsString('not capable', $refusals[0]['message']);

        $this->assertSame('provider', $compat->refusals('agent_reasoning', 'anthropic', null)[0]['field']);
        $this->assertSame([], $compat->refusals('agent_reasoning', 'gemini', null));
    }

    public function test_a_model_whose_output_limit_is_too_small_is_refused(): void
    {
        DB::table('ai_models')->insert([
            'provider' => 'gemini', 'model_id' => 'tiny', 'label' => 'Tiny', 'max_output_tokens' => 512,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->forgetSingletons();

        $refusals = app(ProviderCompatibility::class)->refusals('agent_reasoning', 'gemini', 'tiny');

        $this->assertSame('model', $refusals[0]['field']);
        $this->assertStringContainsString('below', $refusals[0]['message']);
    }

    // ---- no environment fallback, tenant isolation -----------------------

    public function test_the_environment_never_supplies_a_provider_or_key(): void
    {
        // Empty key table; the environment holds keys for both providers.
        $resolver = app(AiConfigurationResolver::class);

        $this->assertSame([], $resolver->resolveChain('agent_reasoning', 1));
        $this->assertFalse($resolver->resolve('agent_reasoning', 1)->hasKey());

        $e = $resolver->explainEmpty('agent_reasoning', 1);
        $this->assertSame('not_configured', $e->reason);
        $this->assertStringContainsString('AI Providers', $e->getMessage());
    }

    public function test_a_module_row_with_no_key_is_reported_not_papered_over_by_the_pool(): void
    {
        $this->row(['api_type' => 'gemini']); // a perfectly good pool key
        $this->row(['api_type' => 'OPENROUTER_API_KEY', 'ai_module' => 'agent_reasoning', 'api_key' => '-']);

        $resolver = app(AiConfigurationResolver::class);

        // The module chose a provider and its key is unusable: nothing is called, not the pool.
        $this->assertSame([], $resolver->resolveChain('agent_reasoning'));
        $this->assertSame('missing_credential', $resolver->explainEmpty('agent_reasoning')->reason);
    }

    public function test_one_schools_keys_never_resolve_for_another(): void
    {
        $this->row(['api_type' => 'gemini', 'ai_module' => 'agent_reasoning', 'sub_institute_id' => 5, 'api_key' => 'SCHOOL-5']);

        $this->assertSame(['gemini'], $this->providers('agent_reasoning', 5));
        $this->assertSame([], $this->providers('agent_reasoning', 6));
        $this->assertSame([], $this->providers('agent_reasoning', null));
    }

    public function test_a_module_without_rows_inherits_its_parents_and_says_so(): void
    {
        $this->row(['api_type' => 'DEEPSEEK_API_KEY', 'ai_module' => 'generative_ai']);
        $this->row(['api_type' => 'gemini']);

        $chain = app(AiConfigurationResolver::class)->resolveChain('analytics_ai');

        $this->assertSame('deepseek', $chain[0]->provider);
        $this->assertSame('generative_ai', $chain[0]->inheritedFrom);

        // Its own row ends the inheritance.
        $this->row(['api_type' => 'gemini', 'ai_module' => 'analytics_ai']);
        $own = app(AiConfigurationResolver::class)->resolveChain('analytics_ai');

        $this->assertSame('gemini', $own[0]->provider);
        $this->assertNull($own[0]->inheritedFrom);
    }

    public function test_every_registered_module_declares_what_it_needs(): void
    {
        $modules = app(AiModuleRegistry::class);

        foreach ($modules->all() as $module) {
            $this->assertNotEmpty($module['requires'], $module['key']);
            $this->assertContains($module['runtime'], ['model', 'none']);

            if ($module['runtime'] === 'none') {
                $this->assertNotEmpty($module['gap'], $module['key'] . ' makes no call and must say why.');
            }
        }
    }

    // ---- fallback --------------------------------------------------------

    private function leg(string $provider, callable $chat): array
    {
        $client = new class($chat) implements ModelClient {
            public array $seen = [];

            public function __construct(private $chat)
            {
            }

            public function isConfigured(): bool { return true; }
            public function forInstitute(int|string|null $subInstituteId): static { return $this; }
            public function withConfiguration(ResolvedAiConfiguration $configuration): static { return $this; }
            public function defaultModel(): string { return 'm'; }

            public function chat(array $messages, ?string $model = null, ?int $maxTokens = null, ?float $temperature = null, bool $expectJson = false, ?int $timeout = null): ?string
            {
                $this->seen[] = $model;

                return ($this->chat)($model);
            }

            public function stream(array $messages, callable $onDelta, ?string $model = null, ?int $maxTokens = null, ?float $temperature = null): ?string
            {
                $onDelta('partial');
                throw new AiProviderException('boom', 503);
            }

            public function json(array $messages, ?string $model = null, int $maxTokens = 900, float $temperature = 0.0): ?array
            {
                return null;
            }
        };

        return ['config' => new ResolvedAiConfiguration($provider, $provider . '-model', 'k', 'pool_platform', module: 'assessment_ai'), 'client' => $client];
    }

    public function test_a_quota_failure_moves_to_the_next_provider_and_is_recorded(): void
    {
        $a = $this->leg('gemini', fn () => throw new AiProviderException('quota', 429));
        $b = $this->leg('openrouter', fn () => 'from-b');

        $chain = new FallbackChainModelClient([$a, $b], 'assessment_ai', null, 9);

        $this->assertSame('from-b', $chain->chat([['role' => 'user', 'content' => 'hi']], 'gemini-pinned'));
        $this->assertSame('openrouter', $chain->lastUsed()->provider);
        $this->assertSame('gemini', $chain->lastFailures()[0]['provider']);

        // The pinned model went to the first provider only.
        $this->assertSame(['gemini-pinned'], $a['client']->seen);
        $this->assertSame([null], $b['client']->seen);

        $rows = DB::table('ai_usage_events')->orderBy('id')->get();
        $this->assertSame(['error', 'ok'], $rows->pluck('outcome')->all());
        $this->assertSame(['gemini', 'openrouter'], $rows->pluck('provider')->all());
        $this->assertSame('assessment_ai', $rows[0]->ai_module);
        $this->assertSame(9, (int) $rows[0]->sub_institute_id);
    }

    public function test_a_malformed_request_is_not_sent_to_another_provider(): void
    {
        $a = $this->leg('gemini', fn () => throw new AiProviderException('bad request', 400));
        $b = $this->leg('openrouter', fn () => 'from-b');

        $this->expectException(AiProviderException::class);

        try {
            (new FallbackChainModelClient([$a, $b], 'assessment_ai'))->chat([['role' => 'user', 'content' => 'x']]);
        } finally {
            $this->assertSame([], $b['client']->seen, 'The second provider must not have been called.');
        }
    }

    public function test_an_invalid_google_key_counts_as_a_credential_failure(): void
    {
        $a = $this->leg('gemini', fn () => throw new AiProviderException('API_KEY_INVALID', 400));
        $b = $this->leg('openrouter', fn () => 'from-b');

        $this->assertSame('from-b', (new FallbackChainModelClient([$a, $b], 'assessment_ai'))->chat([['role' => 'user', 'content' => 'x']]));
    }

    public function test_an_empty_answer_passes_to_the_next_provider(): void
    {
        $a = $this->leg('gemini', fn () => '   ');
        $b = $this->leg('openrouter', fn () => 'real');

        $this->assertSame('real', (new FallbackChainModelClient([$a, $b], 'assessment_ai'))->chat([['role' => 'user', 'content' => 'x']]));
    }

    public function test_when_every_provider_fails_the_last_error_surfaces(): void
    {
        $a = $this->leg('gemini', fn () => throw new AiProviderException('quota', 429));
        $b = $this->leg('openrouter', fn () => throw new AiProviderException('down', 503));

        $this->expectExceptionMessage('down');

        (new FallbackChainModelClient([$a, $b], 'assessment_ai'))->chat([['role' => 'user', 'content' => 'x']]);
    }

    public function test_streaming_does_not_switch_provider_after_text_was_sent(): void
    {
        $a = $this->leg('gemini', fn () => 'unused');
        $b = $this->leg('openrouter', fn () => 'unused');
        $received = [];

        try {
            (new FallbackChainModelClient([$a, $b], 'assessment_ai'))->stream([['role' => 'user', 'content' => 'x']], function ($d) use (&$received) {
                $received[] = $d;
            });
            $this->fail('Expected the stream to fail.');
        } catch (AiProviderException) {
            $this->assertSame(['partial'], $received, 'A second provider must not have re-sent the answer.');
        }
    }

    public function test_a_module_with_nothing_configured_fails_with_a_message_when_used(): void
    {
        $chain = new FallbackChainModelClient(
            [],
            'assessment_ai',
            null,
            null,
            AiConfigurationException::notConfigured('assessment_ai', 'Assessment AI')
        );

        $this->assertFalse($chain->isConfigured());

        $this->expectException(AiConfigurationException::class);
        $chain->chat([['role' => 'user', 'content' => 'x']]);
    }

    // ---- module-specific ranking ------------------------------------------

    private function seedCatalogue(): void
    {
        foreach (['gemini', 'openrouter', 'deepseek', 'openai', 'groq', 'mistral'] as $i => $provider) {
            DB::table('ai_models')->insert([
                'provider' => $provider, 'model_id' => $provider . '-m', 'label' => $provider, 'max_output_tokens' => 8192,
                'sort_order' => $i, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->forgetSingletons();
    }

    private function dropdown(string $module, $institute = null): array
    {
        $out = app(ProviderCompatibility::class)->forModule($module, $institute, live: false);

        return array_values(array_map(
            fn ($p) => $p['provider'],
            array_filter($out['providers'], fn ($p) => $p['selectable'])
        ));
    }

    public function test_each_module_gets_its_own_provider_order(): void
    {
        $this->seedCatalogue();

        $content = $this->dropdown('content_generation');
        $agent = $this->dropdown('agent_reasoning');

        $this->assertSame('deepseek', $content[0], 'Content Generation was built on DeepSeek, so it leads there.');
        $this->assertSame('gemini', $content[1]);
        $this->assertSame(['openai', 'gemini', 'openrouter'], $agent, 'Agent Reasoning has its own list.');
        $this->assertSame(['deepseek', 'gemini', 'openai'], $content, 'Only the modules own list is shown.');
        $this->assertNotSame($content, $agent);
    }

    public function test_the_dropdown_lists_incapable_providers_as_not_selectable_with_a_reason(): void
    {
        $this->seedCatalogue();

        $all = app(ProviderCompatibility::class)->forModule('evaluation_ai', null, live: false)['providers'];
        $byKey = array_column($all, null, 'provider');

        $this->assertTrue($byKey['gemini']['selectable']);
        $this->assertFalse($byKey['openai']['selectable']);
        $this->assertNotEmpty($byKey['openai']['reasons']);
        $this->assertNull($byKey['openai']['rank']);
        $this->assertSame(1, $byKey['gemini']['rank']);
    }

    public function test_a_stored_key_does_not_move_a_provider_up_and_a_missing_one_is_not_ready(): void
    {
        $this->seedCatalogue();
        // Only Gemini has a key. DeepSeek is still Content Generation's first choice, but is not ready.
        $this->row(['api_type' => 'gemini']);

        $providers = app(ProviderCompatibility::class)->forModule('content_generation', null, live: false)['providers'];
        $byKey = array_column($providers, null, 'provider');

        $this->assertSame(1, $byKey['deepseek']['rank']);
        $this->assertFalse($byKey['deepseek']['ready']);
        $this->assertSame('absent', $byKey['deepseek']['credential']['status']);
        $this->assertSame(2, $byKey['gemini']['rank']);
        $this->assertNotSame('absent', $byKey['gemini']['credential']['status']);
    }

    public function test_the_runtime_order_for_an_unconfigured_module_matches_the_dropdown(): void
    {
        $this->seedCatalogue();
        $deepseekType = app(\App\Domain\AI\Configuration\ProviderCatalog::class)->apiType('deepseek');
        $this->row(['api_type' => 'gemini']);
        $this->row(['api_type' => $deepseekType]);

        $this->assertSame(['deepseek', 'gemini'], $this->providers('content_generation'));
        $this->assertSame(['gemini'], $this->providers('agent_reasoning'));

        // What the form shows, restricted to providers that hold a key, is what runs.
        $shown = array_values(array_intersect($this->dropdown('content_generation'), ['deepseek', 'gemini']));
        $this->assertSame($shown, $this->providers('content_generation'));
    }

    public function test_saved_priorities_for_one_module_do_not_change_another(): void
    {
        $this->seedCatalogue();
        $deepseekType = app(\App\Domain\AI\Configuration\ProviderCatalog::class)->apiType('deepseek');
        $this->row(['api_type' => 'gemini']);
        $this->row(['api_type' => $deepseekType]);

        $this->row(['api_type' => 'gemini', 'ai_module' => 'content_generation', 'priority' => 1]);
        $this->row(['api_type' => $deepseekType, 'ai_module' => 'content_generation', 'priority' => 2]);

        $this->assertSame(['gemini', 'deepseek'], $this->providers('content_generation'), 'The administrator\'s order wins.');
        $this->assertSame(['gemini'], $this->providers('agent_reasoning'));
        $this->assertSame(['gemini', 'deepseek'], $this->providers('assessment_ai'), 'Assessment AI is untouched.');

        // The dropdown agrees with what runs.
        $this->assertSame(['gemini', 'deepseek'], array_slice($this->dropdown('content_generation'), 0, 2));
    }

    public function test_dropdown_ranking_is_tenant_isolated(): void
    {
        $this->seedCatalogue();
        $this->row(['api_type' => 'gemini', 'ai_module' => 'content_generation', 'priority' => 1, 'sub_institute_id' => 4]);

        $mine = array_column(app(ProviderCompatibility::class)->forModule('content_generation', 4, live: false)['providers'], null, 'provider');
        $other = array_column(app(ProviderCompatibility::class)->forModule('content_generation', 5, live: false)['providers'], null, 'provider');

        $this->assertSame('configured', $mine['gemini']['status']);
        $this->assertNotSame('configured', $other['gemini']['status']);
        $this->assertSame('deepseek', array_values(array_filter($other, fn ($p) => $p['rank'] === 1))[0]['provider']);
    }
}
