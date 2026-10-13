<?php

namespace App\Services\Documents\Extraction;

use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiVisionOcrProvider implements OcrProviderInterface
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('idms.gemini_api_key') ?: env('GEMINI_API_KEY');
        $this->model = config('idms.ai_model', 'gemini-2.5-flash');
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function performOcr(string $filePath, array $languages = ['eng']): string
    {
        if (!$this->isAvailable() || !file_exists($filePath)) {
            return '';
        }

        try {
            $data = file_get_contents($filePath);
            $mime = mime_content_type($filePath) ?: 'image/jpeg';
            $base64 = base64_encode($data);

            $langsStr = implode(', ', $languages);
            $prompt = "You are a high precision OCR tool. Extract all visible text exactly as written from this document or image. Languages may include {$langsStr}. Return ONLY the extracted text with no conversational filler.";

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(45)->post($url, [
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
