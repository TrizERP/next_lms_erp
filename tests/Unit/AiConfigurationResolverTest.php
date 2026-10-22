<?php

namespace Tests\Unit;

use App\Domain\AI\Configuration\AiConfigurationResolver;
use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Domain\AI\Configuration\AiModuleRegistry;
use App\Domain\AI\Configuration\ProviderCatalog;
use App\Domain\AI\Support\GeminiClient;
use App\Domain\AI\Support\ModelClient;
use App\Domain\AI\Support\OpenAiCompatibleClient;
use App\Domain\AI\Support\OpenRouterClient;
use Tests\TestCase;

/**
 * The centralised configuration must not change what an unconfigured module calls.
 *
 * THE INVARIANT THESE TESTS EXIST FOR
 *
 * Routing a live module through `AiModelClientFactory` is only safe if, with nothing
 * saved, the factory hands back exactly what the container binding always did — same
 * driver class, same model, same credential. Conversational AI is in production; a
 * refactor that quietly moved it to a different provider would look like a model
 * regression, not like a configuration bug, and would be hunted for in the prompt.
 *
 * The first version of the resolver failed this. It picked the newest active
 * `ai_api_keys` row and inferred the provider from it, which on an estate whose newest
 * key is Gemini and whose `AI_PROVIDER` is openrouter silently moved every module.
 * That is the regression `it_matches_the_container_binding_when_nothing_is_configured`
 * catches, and why it asserts on the class rather than on "some client".
 *
 * These tests read configuration and the key pool; they do not write. There is no
 * fixture setup because the point is to compare two code paths against whatever this
 * estate actually holds — a seeded fixture would prove the two agree about the fixture,
 * which is not the question.
 */
class AiConfigurationResolverTest extends TestCase
{
    public function test_the_container_binding_is_the_shared_pool_chain_not_an_environment_driver(): void
    {
        $this->assertInstanceOf(
            \App\Domain\AI\Support\FallbackChainModelClient::class,
            app(ModelClient::class),
            'Injecting ModelClient must give the configured chain, not a client built from AI_PROVIDER.'
        );
    }

    public function test_every_module_resolves_only_to_capable_callable_providers(): void
    {
        $resolver = app(AiConfigurationResolver::class);
        $providers = app(ProviderCatalog::class);
        $capabilities = app(\App\Domain\AI\Configuration\ProviderCapabilities::class);
        $modules = app(AiModuleRegistry::class);

        foreach ($modules->keys() as $module) {
            foreach ($resolver->resolveChain($module, null) as $configuration) {
                $this->assertTrue($providers->isDriveable($configuration->provider), "{$module}: {$configuration->provider} has no client.");
                $this->assertSame(
                    [],
                    $capabilities->missing($configuration->provider, $modules->requires($module)),
                    "{$module} resolved to {$configuration->provider}, which cannot do what it needs."
                );
                $this->assertTrue($configuration->hasKey(), "{$module}: a chain entry without a key cannot be called.");
                $this->assertNotSame('env', $configuration->source, "{$module} must never resolve to an environment credential.");
            }
        }
    }
    public function test_every_catalogued_provider_maps_to_a_real_client_class(): void
    {
        $providers = app(ProviderCatalog::class);

        $expected = [
            'gemini' => GeminiClient::class,
            'openrouter' => OpenRouterClient::class,
            'openai' => OpenAiCompatibleClient::class,
            'deepseek' => OpenAiCompatibleClient::class,
            'groq' => OpenAiCompatibleClient::class,
            'mistral' => OpenAiCompatibleClient::class,
            // Anthropic's Messages API is not OpenAI-compatible, so it is listed
            // without a client on purpose. If someone gives it one, this line is the
            // reminder to check that the client is actually an Anthropic client.
            'anthropic' => null,
        ];

        foreach ($expected as $provider => $class) {
            $this->assertSame($class, $providers->driver($provider), "Provider {$provider}.");
        }

        // Every declared provider is accounted for above, so adding one without
        // deciding how it is called fails here rather than at a user's request.
        $this->assertEqualsCanonicalizing(array_keys($expected), $providers->keys());
    }

    public function test_a_resolved_configuration_never_serialises_its_credential(): void
    {
        $configuration = app(AiConfigurationResolver::class)->resolve('conversational_ai', null);

        $encoded = json_encode($configuration->toArray());

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('api_key', $encoded);

        if ($configuration->hasKey()) {
            $this->assertStringNotContainsString($configuration->apiKey, $encoded);
        }
    }

    public function test_module_keys_are_stable_identifiers(): void
    {
        $keys = app(AiModuleRegistry::class)->keys();

        $this->assertSame(array_unique($keys), $keys, 'Module keys must be unique.');

        foreach ($keys as $key) {
            // These are written into `ai_api_keys.ai_module`. A key with a space or a
            // capital would still store, and would then fail to match on read.
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]{1,63}$/', $key);
        }
    }
}
