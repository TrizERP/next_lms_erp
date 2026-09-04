<?php

namespace App\Services\Homework;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Gemini `generateContent` REST endpoint, shared by
 * the OCR/extraction step (multimodal input) and the evaluation step
 * (text-only input). Mirrors the raw-REST calling convention already used
 * by App\Http\Controllers\api\AiSopGenerationController rather than pulling
 * in a Gemini SDK.
 */
class GeminiClient
{
    private ?string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key') ?: env('GEMINI_API_KEY');
        $this->model = env('GEMINI_MODEL', 'gemini-2.5-flash');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * @param array $parts Gemini "parts" array for a single user turn, e.g.
     *                      [['text' => '...']] or [['text' => '...'], ['inline_data' => [...]]]
     * @param array $generationConfig Overrides merged onto sane evaluation/OCR defaults.
     *
     * @return array{text: string, raw: array}
     *
     * @throws \RuntimeException on HTTP failure, non-2xx response, or an empty completion.
     */
    public function generateContent(array $parts, array $generationConfig = []): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Gemini API key is not configured. Please set GEMINI_API_KEY in the backend .env file.');
        }

        $response = Http::timeout((int) env('GEMINI_REQUEST_TIMEOUT', 90))
            ->retry(1, 500)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $this->apiKey,
            ])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent", [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => $parts,
                    ],
                ],
                'generationConfig' => array_merge([
                    'temperature' => 0.2,
                    'topP' => 0.9,
                    'maxOutputTokens' => 8000,
                ], $generationConfig),
            ]);

        if (!$response->successful()) {
            Log::warning('Gemini homework call failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException($this->errorMessage($response->json(), $response->status()));
        }

        $payload = $response->json() ?? [];
        $text = $this->extractText($payload);

        if ($text === '') {
            throw new \RuntimeException('Gemini returned an empty response.');
        }

        return ['text' => $text, 'raw' => $payload];
    }

    private function extractText(array $payload): string
    {
        $parts = $payload['candidates'][0]['content']['parts'] ?? [];
        $text = '';

        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        return trim($text);
    }

    private function errorMessage(?array $payload, int $status): string
    {
        $message = $payload['error']['message'] ?? '';

        if (is_string($message) && trim($message) !== '') {
            return 'Gemini API error: ' . trim($message);
        }

        return "Gemini request failed. HTTP status: {$status}.";
    }
}
