<?php

namespace App\Domain\AI\Support;

/**
 * The bytes of one generated image, plus whatever caption the same call returned.
 *
 * Requesting both TEXT and IMAGE modalities from one Gemini call (see
 * GeminiClient::generateImage()) gets a caption that is genuinely grounded in the
 * image it was generated alongside, rather than a second, separately-generated
 * description that could drift from what the image actually shows.
 */
final class ImageGenerationResult
{
    public function __construct(
        public readonly string $mimeType,
        public readonly string $base64Data,
        public readonly ?string $caption = null,
    ) {
    }

    public function toBinary(): string
    {
        return base64_decode($this->base64Data);
    }

    public function extension(): string
    {
        return match ($this->mimeType) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }
}
