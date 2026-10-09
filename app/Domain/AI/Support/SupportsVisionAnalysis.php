<?php

namespace App\Domain\AI\Support;

use RuntimeException;

/**
 * An optional capability, not part of `ModelClient`, for the same reason
 * `SupportsImageGeneration` is not: only some providers read images, and a stub
 * method on every other client would claim a capability they do not have.
 *
 * Callers check `instanceof SupportsVisionAnalysis` and treat its absence as "this
 * provider cannot do the job", never as a reason to guess.
 */
interface SupportsVisionAnalysis
{
    /**
     * Send one image and an instruction; get the model's JSON answer back.
     *
     * @param  string  $imageBytes  the raw image file, not base64
     * @param  string  $mimeType    image/jpeg, image/png or image/webp
     * @return string|null the model's text (JSON), or null when it returned nothing usable
     *
     * @throws RuntimeException when no key is configured or the provider refuses.
     */
    public function analyzeImage(
        string $prompt,
        string $imageBytes,
        string $mimeType,
        ?string $model = null,
        ?int $timeout = null,
    ): ?string;
}
