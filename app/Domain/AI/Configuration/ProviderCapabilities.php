<?php

namespace App\Domain\AI\Configuration;

use App\Domain\AI\Support\ModelClient;
use App\Domain\AI\Support\SupportsEmbedding;
use App\Domain\AI\Support\SupportsImageGeneration;
use App\Domain\AI\Support\SupportsVisionAnalysis;

/**
 * What a provider can actually do on this platform — read off the client that drives it.
 *
 * NOTHING HERE NAMES A PROVIDER
 *
 * A provider's capabilities are the interfaces its client class implements. A provider with no
 * client implements nothing, so it can do nothing here however good the vendor is. Add an
 * `SupportsVisionAnalysis` to a client and every module that needs vision starts accepting
 * that provider without a second edit; remove one and the provider stops being offered.
 *
 * `tool_calling` is listed so it can be required, but no client here exposes native function
 * calling — agents plan through the JSON path — so no provider has it, and a module that
 * required it would correctly find no capable provider.
 *
 * Model-level limits (output ceiling, context window, modality) are a separate question and
 * come from the provider's live model list, see `ProviderModelDiscovery`.
 */
final class ProviderCapabilities
{
    public const CHAT = 'chat';

    public const JSON = 'json';

    public const STREAM = 'stream';

    public const VISION = 'vision';

    public const EMBEDDING = 'embedding';

    public const IMAGE_GENERATION = 'image_generation';

    public const TOOL_CALLING = 'tool_calling';

    /** Words an administrator can read, for "needs X" messages. */
    private const LABELS = [
        self::CHAT => 'text chat',
        self::JSON => 'structured (JSON) output',
        self::STREAM => 'streaming',
        self::VISION => 'image and document understanding',
        self::EMBEDDING => 'embeddings',
        self::IMAGE_GENERATION => 'image generation',
        self::TOOL_CALLING => 'native tool calling',
    ];

    public function __construct(private readonly ProviderCatalog $providers)
    {
    }

    /**
     * Capabilities this provider's client has.
     *
     * @return array<int, string>
     */
    public function forProvider(string $provider): array
    {
        $driver = $this->providers->driver($provider);

        if ($driver === null || ! class_exists($driver)) {
            return [];
        }

        $interfaces = class_implements($driver) ?: [];
        $found = [];

        if (isset($interfaces[ModelClient::class])) {
            // chat(), json() and stream() are all on the interface: a ModelClient has all three.
            array_push($found, self::CHAT, self::JSON, self::STREAM);
        }

        if (isset($interfaces[SupportsVisionAnalysis::class])) {
            $found[] = self::VISION;
        }

        if (isset($interfaces[SupportsEmbedding::class])) {
            $found[] = self::EMBEDDING;
        }

        if (isset($interfaces[SupportsImageGeneration::class])) {
            $found[] = self::IMAGE_GENERATION;
        }

        return $found;
    }

    /**
     * What is missing for a provider to serve a module.
     *
     * @param  array<int, string>  $required
     * @return array<int, string>  Capability keys the provider lacks; empty means capable.
     */
    public function missing(string $provider, array $required): array
    {
        return array_values(array_diff($required, $this->forProvider($provider)));
    }

    public function label(string $capability): string
    {
        return self::LABELS[$capability] ?? $capability;
    }

    /**
     * @param  array<int, string>  $capabilities
     */
    public function describe(array $capabilities): string
    {
        return implode(', ', array_map(fn (string $c) => $this->label($c), $capabilities));
    }
}
