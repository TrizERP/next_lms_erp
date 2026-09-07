<?php

namespace App\Services\G2gLms;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Small DeepSeek chat-completions client for `AiAssessmentController::generate()`.
 *
 * hp_erp's source (`App\Services\DeepSeekService`) exposes `isConfigured()`,
 * `model()` and `chatJson(array $messages): array` - this class implements
 * the same three-method surface against THIS codebase's own DeepSeek
 * config/key-resolution conventions (`config/deepseek.php` +
 * `App\Services\QuestionGenerationService::resolveApiKey()`'s
 * ai_api_keys-table-then-env pattern), rather than inventing a new one or
 * assuming hp_erp's `DeepSeekService` class exists here (it does not - only
 * `config/deepseek.php` and ad-hoc per-feature callers of it do).
 */
class DeepSeekAssessmentService
{
    protected string $model;
    protected int $timeout;
    protected int $maxTokens;

    public function __construct()
    {
        $this->model     = (string) config('deepseek.model', 'deepseek-chat');
        $this->timeout   = (int) config('deepseek.timeout_seconds', 120);
        $this->maxTokens = (int) config('deepseek.max_output_tokens', 0);
    }

    public function model(): string
    {
        return $this->model;
    }

    public function isConfigured(): bool
    {
        return $this->resolveApiKey() !== null;
    }

    protected function resolveApiKey(): ?string
    {
        if (function_exists('getAIKey')) {
            try {
                $row = getAIKey(config('deepseek.api_type', 'DEEPSEEK_API_KEY'), 1);
                if (!empty($row) && !empty($row->api_key) && $row->api_key !== '-') {
                    return $row->api_key;
                }
            } catch (Throwable $e) {
                // fall through to the table/env lookups below
            }
        }

        if (Schema::hasTable('ai_api_keys')) {
            $row = DB::table('ai_api_keys')
                ->where('api_type', config('deepseek.api_type', 'DEEPSEEK_API_KEY'))
                ->where('status', 1)
                ->first();
            if (!empty($row) && !empty($row->api_key) && $row->api_key !== '-') {
                return $row->api_key;
            }
        }

        $envKey = config('deepseek.api_key');

        return !empty($envKey) ? $envKey : null;
    }

    /**
     * @param array<int, array{role:string, content:string}> $messages
     * @return array Decoded JSON object from the model's reply.
     * @throws Throwable when the call fails or the reply is not valid JSON.
     */
    public function chatJson(array $messages): array
    {
        $apiKey = $this->resolveApiKey();
        if (!$apiKey) {
            throw new \RuntimeException('DeepSeek API key not configured (set DEEPSEEK_API_KEY or an ai_api_keys row).');
        }

        $body = [
            'model'    => $this->model,
            'messages' => $messages,
            'stream'   => false,
            'response_format' => ['type' => 'json_object'],
        ];
        if ($this->maxTokens > 0) {
            $body['max_tokens'] = $this->maxTokens;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout($this->timeout)->connectTimeout(20)
          ->post(rtrim((string) config('deepseek.base_url', 'https://api.deepseek.com'), '/') . '/chat/completions', $body);

        if (!$response->successful()) {
            Log::error('DeepSeek assessment generation failed: ' . $response->status() . ' ' . $response->body());
            throw new \RuntimeException('DeepSeek request failed: ' . $response->status());
        }

        $content = $response->json('choices.0.message.content');
        if ($content === null) {
            throw new \RuntimeException('DeepSeek returned an empty message.');
        }

        $decoded = json_decode((string) $content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('DeepSeek did not return valid JSON.');
        }

        return $decoded;
    }
}
