<?php

namespace App\Domain\AI\Configuration;

use App\Domain\AI\Support\ModelClient;
use App\Domain\AI\Support\OpenAiCompatibleClient;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * A ready-to-call client for one AI module.
 *
 * This is the single seam between "what an administrator saved" and "what the code
 * calls". A module asks for its client by name; everything behind that — which
 * provider, which model, whose key, which client class — is resolved here and nowhere
 * else. That is the whole point of the screen: a module that built its own client
 * would be a module the settings page cannot govern.
 *
 * USAGE
 *
 *     $client = $factory->for('conversational_ai', $scope->selectedInstituteId);
 *     $answer = $client->chat($messages);
 *
 * The returned client already carries the resolved model as its `defaultModel()`, so a
 * caller that passes no model gets the configured one. A caller that passes a model
 * explicitly still wins — per-call overrides exist for prompt templates that pin a
 * model, and a settings screen should not silently overrule a prompt that was written
 * against a specific one.
 *
 * FALLING BACK IS NOT FAILING
 *
 * A module with nothing saved resolves to the platform pool and the configured driver —
 * the behaviour it had before this class existed. `for()` therefore never throws for
 * "unconfigured"; it throws only when the resolved provider has no client that can
 * call it, which is a configuration mistake worth surfacing loudly.
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
     * The client one module should use, already scoped to the school and its key.
     *
     * @param  string|null  $moduleKey  A key from `AiModuleRegistry`. Null gives the
     *                                  unbound pool behaviour legacy callers have.
     */
    public function for(?string $moduleKey, int|string|null $subInstituteId = null): ModelClient
    {
        return $this->fromConfiguration(
            $this->resolver->resolve($moduleKey, $subInstituteId),
            $subInstituteId
        );
    }

    /** The configuration a module resolves to, without building a client for it. */
    public function configurationFor(?string $moduleKey, int|string|null $subInstituteId = null): ResolvedAiConfiguration
    {
        return $this->resolver->resolve($moduleKey, $subInstituteId);
    }

    /**
     * Build the client for an already-resolved configuration.
     *
     * Public so a caller that has resolved once — to log what it is about to do, say —
     * does not resolve a second time and risk a different answer between the two.
     */
    public function fromConfiguration(
        ResolvedAiConfiguration $configuration,
        int|string|null $subInstituteId = null
    ): ModelClient {
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
