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
    public function test_it_matches_the_container_binding_when_nothing_is_configured(): void
    {
        $factory = app(AiModelClientFactory::class);
        $container = app(ModelClient::class);

        foreach (app(AiModuleRegistry::class)->keys() as $module) {
            $resolved = $factory->for($module, null);

            $this->assertSame(
                $container::class,
                $resolved::class,
                "Module {$module} resolved to a different client than the container binding."
            );

            $this->assertSame(
                $container->defaultModel(),
                $resolved->defaultModel(),
                "Module {$module} resolved to a different model than the container binding."
            );

            $this->assertSame(
                $container->isConfigured(),
                $resolved->isConfigured(),
                "Module {$module} disagrees with the container binding about being configured."
            );
        }
    }

    public function test_the_unbound_provider_is_the_configured_driver_not_the_newest_key(): void
    {
        $driver = (string) config('ai.provider.driver');
        $resolver = app(AiConfigurationResolver::class);

        // Both the "no module" case and a module with nothing saved.
        foreach ([null, 'conversational_ai', 'generative_ai'] as $module) {
            $this->assertSame(
                $driver,
                $resolver->resolve($module, null)->provider,
                'An unconfigured module must resolve to the configured driver.'
            );
        }
    }

    public function test_every_module_resolves_to_something_callable(): void
    {
        $resolver = app(AiConfigurationResolver::class);
        $providers = app(ProviderCatalog::class);

        foreach (app(AiModuleRegistry::class)->keys() as $module) {
            $configuration = $resolver->resolve($module, null);

            $this->assertTrue(
                $providers->exists($configuration->provider),
                "Module {$module} resolved to unknown provider {$configuration->provider}."
            );

            // A resolved provider with no client is a configuration the runtime cannot
            // honour. The controller refuses to save one; this proves the fallback
            // cannot produce one either.
            $this->assertTrue(
                $providers->isDriveable($configuration->provider),
                "Module {$module} resolved to {$configuration->provider}, which has no client."
            );
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
