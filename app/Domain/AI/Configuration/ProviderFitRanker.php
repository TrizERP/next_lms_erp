<?php

namespace App\Domain\AI\Configuration;

/**
 * Orders providers for ONE module.
 *
 * THIS IS THE ONLY PLACE A MODULE'S PROVIDER ORDER IS DECIDED
 *
 * The admin dropdown and the runtime fallback chain both call this, so the order an administrator is
 * shown is the order requests are tried in. There is no second ranking anywhere.
 *
 * The order is the module's own `offered` list (`AiModuleRegistry`): Content Generation leads with
 * DeepSeek, Conversational AI with OpenAI, and so on — each module its own list. Providers the module
 * does not list are not ranked at all; callers drop them.
 *
 * What it deliberately does not use: whether a key is stored. A key says nothing about whether a
 * provider suits the module, so it never moves a provider up — it only decides whether the provider is
 * READY, reported beside it. Capability is not decided here either: callers hand in capable providers.
 */
final class ProviderFitRanker
{
    public function __construct(private readonly AiModuleRegistry $modules)
    {
    }

    /**
     * @param  array<int, string>  $providers  Capable providers, in any order.
     * @return array<int, string>  Those the module offers, in the module's order.
     */
    public function order(string $moduleKey, array $providers, int|string|null $subInstituteId = null): array
    {
        return array_values(array_filter(
            $this->modules->offered($moduleKey),
            fn (string $provider) => in_array($provider, $providers, true)
        ));
    }

    /** Whether the module lists this provider at all. */
    public function isProven(string $moduleKey, string $provider): bool
    {
        return $this->modules->isOffered($moduleKey, $provider);
    }
}