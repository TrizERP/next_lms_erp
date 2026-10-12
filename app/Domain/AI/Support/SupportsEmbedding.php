<?php

namespace App\Domain\AI\Support;

use RuntimeException;

/**
 * An optional capability, not part of `ModelClient` — same reasoning as
 * `SupportsImageGeneration`/`SupportsVisionAnalysis`: embeddings are a
 * different endpoint and a different model family from chat completion
 * (Gemini's `:embedContent`, not `:generateContent`), and most providers this
 * app could add later would not implement it either. A caller that needs a
 * vector checks `instanceof SupportsEmbedding` and treats its absence as
 * "this provider cannot do the job", never as a reason to guess or to fall
 * back to a text-similarity heuristic silently.
 *
 * Added 2026-10-10 for the Content Authoring RAG row — the first caller is
 * `App\Services\PAL\ContentModel\ContentEmbeddingService`.
 */
interface SupportsEmbedding
{
    /**
     * Embed one piece of text.
     *
     * @return float[]|null the embedding vector, or null when the provider
     *                       returned nothing usable (empty input, a safety
     *                       block, or a response with no values).
     *
     * @throws RuntimeException when no key is configured or the provider refuses.
     */
    public function embed(string $text, ?string $model = null, ?int $timeout = null): ?array;
}
