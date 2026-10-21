<?php

namespace App\Services\Documents\Extraction;

use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads text out of images and scanned PDFs with the provider the administrator configured
 * for Document Intelligence (module `document_intelligence`).
 *
 * The request itself stays here: the shared vision call forces a JSON answer capped at 4096
 * output tokens, which is right for box lists and wrong for transcribing a full page as plain
 * text. So provider, model and key come from the central configuration, and a configuration
 * that is not Gemini is refused (this class speaks Gemini's wire format only) rather than
 * silently sent somewhere else.
 */
class GeminiVisionOcrProvider implements OcrProviderInterface
{
    /** The school whose configuration applies; null means platform keys only. */
    protected int|string|null $subInstituteId = null;

    protected ?ResolvedAiConfiguration $resolved = null;

    protected bool $resolvedAttempted = false;

    /** A copy scoped to one school, so that school's own configuration applies. */
    public function forInstitute(int|string|null $subInstituteId): static
    {
        $copy = clone $this;
        $copy->subInstituteId = $subInstituteId;
        $copy->resolved = null;
        $copy->resolvedAttempted = false;

        return $copy;
    }

    /** The usable Gemini configuration for Document Intelligence, or null. */
    protected function configuration(): ?ResolvedAiConfiguration
    {
        if ($this->resolvedAttempted) {
            return $this->resolved;
        }

        $this->resolvedAttempted = true;

        try {
            $configuration = app(AiModelClientFactory::class)
                ->configurationFor('document_intelligence', $this->subInstituteId);
        } catch (Throwable) {
            return null;
        }

        if ($configuration->provider !== 'gemini' || ! $configuration->hasKey() || trim((string) $configuration->model) === '') {
            return null;
        }

        return $this->resolved = $configuration;
    }

    public function isAvailable(): bool
    {
        return $this->configuration() !== null;
    }

    public function performOcr(string $filePath, array $languages = ['eng']): string
    {
        $configuration = $this->configuration();

        if ($configuration === null || !file_exists($filePath)) {
            return '';
        }

        try {
            $data = file_get_contents($filePath);
            $mime = mime_content_type($filePath) ?: 'image/jpeg';
            $base64 = base64_encode($data);

            $langsStr = implode(', ', $languages);
            $prompt = "You are a high precision OCR tool. Extract all visible text exactly as written from this document or image. Languages may include {$langsStr}. Return ONLY the extracted text with no conversational filler.";

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$configuration->model}:generateContent";

            $response = Http::withHeaders(['x-goog-api-key' => $configuration->apiKey])->timeout(45)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mime,
                                    'data' => $base64,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                $body = $response->json();
                return trim($body['candidates'][0]['content']['parts'][0]['text'] ?? '');
            }
        } catch (Throwable $e) {
            // OCR fallback
        }

        return '';
    }
}
