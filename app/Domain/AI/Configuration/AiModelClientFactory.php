<?php

namespace App\Domain\AI\Configuration;

use App\Domain\AI\Support\FallbackChainModelClient;
use App\Domain\AI\Support\ModelClient;
use App\Domain\AI\Support\OpenAiCompatibleClient;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * A ready-to-call client for one AI module.
 *
 * This is the single seam between "what an administrator saved" and "what the code
 * calls". A module asks for its client by name; everything behind that — which
 * provider, which model, whose key, which client class, and which providers to fall
 * back to — is resolved here and nowhere else. That is the whole point of the screen: a
 * module that built its own client would be a module the settings page cannot govern.
 *
 * USAGE
 *
 *     $client = $factory->for('assessment_ai', $scope->selectedInstituteId);
 *     $answer = $client->chat($messages);
 *
 * The returned client is a chain: the module's providers in the order its administrator
 * ranked them. The first answers; if it fails or its quota is spent the next is tried.
 * Each carries the resolved model as its `defaultModel()`, so a caller that passes no
 * model gets the configured one. A caller that passes a model explicitly still wins on
 * the first provider — per-call overrides exist for prompt templates that pin a model,
 * and a settings screen should not silently overrule a prompt written against one.
 *
 * NOTHING CONFIGURED IS NOT A SILENT FALLBACK
 *
 * With no usable configuration `for()` still returns a client — `isConfigured()` is false,
 * so a caller that degrades gracefully keeps doing so — but any attempt to USE it throws an
 * `AiConfigurationException` saying what the administrator has to set up. It never reaches
 * for a provider named in the environment file.
 */
final class AiModelClientFactory
{
    public function __construct(
        private readonly Container $container,
        private readonly AiConfigurationResolver $resolver,
        private readonly ProviderCatalog $providers,
    ) {
    }

    /**
     * The client one module should use, already scoped to the school and its keys.
     *
     * @param  string|null  $moduleKey  A key from `AiModuleRegistry` — the CAPABILITY. Null gives
     *                                  the shared-pool chain.
     * @param  string|null  $productModuleKey  An `ai_modules` key — the PRODUCT module on whose
     *                                  behalf the call is made, so a module that chose its own
     *                                  model on its own AI Stack gets it.
     */
    public function for(
        ?string $moduleKey,
        int|string|null $subInstituteId = null,
        ?string $productModuleKey = null
    ): FallbackChainModelClient {
        $chain = $this->resolver->resolveChain($moduleKey, $subInstituteId, $productModuleKey);

        // An unusable configuration is dropped from the chain rather than failing it: a leg with
        // no key cannot answer, and the legs after it still can.
        $chain = array_values(array_filter($chain, fn (ResolvedAiConfiguration $c) => $c->hasKey()));

        if ($chain === []) {
            return new FallbackChainModelClient(
                [],
                $moduleKey,
                fn (ResolvedAiConfiguration $c) => $this->leg($c, $subInstituteId),
                $subInstituteId,
                $this->resolver->explainEmpty($moduleKey, $subInstituteId),
            );
        }

        $legs = array_map(fn (ResolvedAiConfiguration $c) => [
            'config' => $c,
            'client' => $this->leg($c, $subInstituteId),
        ], $chain);

        return new FallbackChainModelClient(
            $legs,
            $moduleKey,
            fn (ResolvedAiConfiguration $c) => $this->leg($c, $subInstituteId),
            $subInstituteId,
        );
    }

    /** The configuration a module resolves to, without building a client for it. */
    public function configurationFor(
        ?string $moduleKey,
        int|string|null $subInstituteId = null,
        ?string $productModuleKey = null
    ): ResolvedAiConfiguration {
        return $this->resolver->resolve($moduleKey, $subInstituteId, $productModuleKey);
    }

    /**
     * Every provider a module may call, in order — for screens and tests that report the chain.
     *
     * @return array<int, ResolvedAiConfiguration>
     */
    public function chainFor(
        ?string $moduleKey,
        int|string|null $subInstituteId = null,
        ?string $productModuleKey = null
    ): array {
        return $this->resolver->resolveChain($moduleKey, $subInstituteId, $productModuleKey);
    }

    /**
     * Build the client for an already-resolved configuration — one provider, no fallbacks.
     *
     * Public so a caller that has resolved once — to log what it is about to do, say —
     * does not resolve a second time and risk a different answer between the two.
     */
    public function fromConfiguration(
        ResolvedAiConfiguration $configuration,
        int|string|null $subInstituteId = null
    ): FallbackChainModelClient {
        return new FallbackChainModelClient(
            [['config' => $configuration, 'client' => $this->leg($configuration, $subInstituteId)]],
            $configuration->module,
            fn (ResolvedAiConfiguration $c) => $this->leg($c, $subInstituteId),
            $subInstituteId,
        );
    }

    /** One raw provider client carrying its configuration. Usage is recorded by the chain, not here. */
    private function leg(ResolvedAiConfiguration $configuration, int|string|null $subInstituteId): ModelClient
    {
        $driver = $this->providers->driver($configuration->provider);

        if ($driver === null) {
            throw new RuntimeException(sprintf(
                '%s is configured but this platform has no client that can call it.',
                $this->providers->label($configuration->provider)
            ));
        }

        $client = $this->container->make($driver);

        // The generic client serves several vendors, so it has to be told which.
        if ($client instanceof OpenAiCompatibleClient) {
            $client = $client->forProvider($configuration->provider);
        }

        return $client
            ->forInstitute($subInstituteId)
            ->withConfiguration($configuration);
    }
}
