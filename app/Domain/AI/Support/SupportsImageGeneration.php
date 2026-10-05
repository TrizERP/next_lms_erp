<?php

namespace App\Domain\AI\Support;

use RuntimeException;

/**
 * An optional capability, not part of `ModelClient`.
 *
 * Only Gemini can generate images today. Adding `generateImage()` to `ModelClient`
 * itself would force `OpenRouterClient`/`OpenAiCompatibleClient` to grow a stub method
 * for a capability they don't have, for every future provider that never gets one
 * either. `AiModelClientFactory::fromConfiguration()` already special-cases a client by
 * concrete type for the same reason (`instanceof OpenAiCompatibleClient`); callers that
 * need to generate an image check `instanceof SupportsImageGeneration` the same way.
 */
interface SupportsImageGeneration
{
    /**
     * @throws RuntimeException when no key is configured, the provider refuses, or the
     *                          response carries no image.
     */
    public function generateImage(string $prompt, ?string $model = null, ?int $timeout = null): ImageGenerationResult;
}
