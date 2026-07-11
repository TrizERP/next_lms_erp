<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GammaService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('GAMMA_API_KEY');
        $this->baseUrl = env('GAMMA_BASE_URL', 'https://public-api.gamma.app/v1.0/');
    }

    public function generatePresentation(array $params)
    {
        $requestJson = [
            'inputText' => $params['inputText'] ?? '',
            'textMode' => 'generate',
            'format' => $params['format'] ?? 'presentation',
            'numCards' => (int) ($params['numCards'] ?? 10),
            'cardSplit' => 'auto',
            'additionalInstructions' => $params['additionalInstructions'] ?? '',
            'exportAs' => $params['exportAs'] ?? 'pdf',
            'textOptions' => [
                'amount' => 'detailed',
                'tone' => 'professional, inspiring',
                'audience' => 'students',
                'language' => 'en'
            ],
            'imageOptions' => [
                'source' => 'aiGenerated',
                'model' => 'imagen-4-pro',
                'style' => 'photorealistic'
            ],
            'cardOptions' => [
                'dimensions' => 'fluid'
            ]
        ];

        if (!empty($params['themeId'])) {
            $requestJson['themeId'] = $params['themeId'];
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-API-KEY' => $this->apiKey,
        ])->timeout(60)->post($this->baseUrl . 'generations', $requestJson);

        if (!$response->successful()) {
            throw new \Exception('Failed to create generation: ' . $response->body());
        }

        return $response->json();
    }

    public function pollStatus($generationId)
    {
        $maxAttempts = 30;
        $attempts = 0;
        $status = 'processing';

        while ($attempts < $maxAttempts && $status !== 'completed' && $status !== 'failed') {
            $attempts++;

            if ($attempts > 1) {
                sleep(5);
            }

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
            ])->timeout(30)->get($this->baseUrl . 'generations/' . $generationId);

            if (!$response->successful()) {
                Log::warning('Poll attempt failed', [
                    'attempt' => $attempts,
                    'response' => $response->body()
                ]);
                continue;
            }

            $result = $response->json();
            $status = $result['status'] ?? 'processing';

            Log::info('Polling generation status', [
                'generation_id' => $generationId,
                'status' => $status,
                'attempt' => $attempts
            ]);
        }

        return [
            'status' => $status,
            'data' => $result ?? []
        ];
    }

    public function extractResult(array $responseData)
    {
        $url = null;
        $pdfUrl = null;

        if (isset($responseData['presentation'])) {
            $presentation = $responseData['presentation'];
            if (isset($presentation['url'])) $url = $presentation['url'];
            if (isset($presentation['shareUrl'])) $url = $presentation['shareUrl'];
            if (isset($presentation['exportUrl'])) $pdfUrl = $presentation['exportUrl'];
            if (isset($presentation['id'])) $url = "https://gamma.app/docs/" . $presentation['id'];
        }

        if (isset($responseData['url'])) $url = $responseData['url'];
        if (isset($responseData['pdf_url'])) $pdfUrl = $responseData['pdf_url'];
        if (isset($responseData['shareUrl'])) $url = $responseData['shareUrl'];
        if (isset($responseData['exportUrl'])) $pdfUrl = $responseData['exportUrl'];
        if (isset($responseData['presentation_url'])) $url = $responseData['presentation_url'];

        if (isset($responseData['data']) && is_array($responseData['data'])) {
            $nestedData = $responseData['data'];
            if (isset($nestedData['url'])) $url = $nestedData['url'];
            if (isset($nestedData['pdf_url'])) $pdfUrl = $nestedData['pdf_url'];
            if (isset($nestedData['shareUrl'])) $url = $nestedData['shareUrl'];
            if (isset($nestedData['exportUrl'])) $pdfUrl = $nestedData['exportUrl'];
            if (isset($nestedData['presentation_url'])) $url = $nestedData['presentation_url'];

            if (isset($nestedData['presentation'])) {
                $presentation = $nestedData['presentation'];
                if (isset($presentation['url'])) $url = $presentation['url'];
                if (isset($presentation['shareUrl'])) $url = $presentation['shareUrl'];
                if (isset($presentation['id'])) $url = "https://gamma.app/docs/" . $presentation['id'];
            }

            if (isset($nestedData['exports']) && is_array($nestedData['exports'])) {
                foreach ($nestedData['exports'] as $export) {
                    if (isset($export['url'])) {
                        if (isset($export['type']) && $export['type'] === 'pdf') {
                            $pdfUrl = $export['url'];
                        } else if (!$url) {
                            $url = $export['url'];
                        }
                    }
                }
            }
        }

        if (isset($responseData['exports']) && is_array($responseData['exports'])) {
            foreach ($responseData['exports'] as $export) {
                if (isset($export['url'])) {
                    if (isset($export['type']) && $export['type'] === 'pdf') {
                        $pdfUrl = $export['url'];
                    } else if (!$url) {
                        $url = $export['url'];
                    }
                }
            }
        }

        if (isset($responseData['cards']) && is_array($responseData['cards']) && count($responseData['cards']) > 0) {
            if (isset($responseData['shareUrl'])) $url = $responseData['shareUrl'];
            if (isset($responseData['id'])) $url = "https://gamma.app/docs/" . $responseData['id'];
            if (!$url && $responseData['status'] === 'pending') {
                $responseData['status'] = 'completed';
            }
        }

        if (($responseData['status'] === 'completed' || $responseData['status'] === 'succeeded' || $responseData['status'] === 'done') && !$url) {
            if (isset($responseData['presentationId'])) $url = "https://gamma.app/docs/" . $responseData['presentationId'];
            elseif (isset($responseData['id'])) $url = "https://gamma.app/docs/" . $responseData['id'];
            elseif (isset($responseData['generationId'])) $url = "https://gamma.app/docs/" . $responseData['generationId'];
        }

        return [
            'url' => $url,
            'pdf_url' => $pdfUrl,
            'status' => $responseData['status'] ?? 'unknown',
            'credits_used' => $responseData['credits']['deducted'] ?? null,
            'credits_remaining' => $responseData['credits']['remaining'] ?? null,
        ];
    }
}
