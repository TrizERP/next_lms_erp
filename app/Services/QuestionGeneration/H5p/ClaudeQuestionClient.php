<?php

namespace App\Services\QuestionGeneration\H5p;

use App\Domain\AI\Support\ProviderKeyResolver;
use Closure;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One Claude Messages API call for question generation.
 *
 * Returns the same envelope QuestionGenerationService::callDeepSeek() returns
 * (ok / content / model / finish_reason / usage with prompt_tokens and
 * completion_tokens, or ok=false with an error), so the code after the call -- JSON
 * extraction, validation, persistence, token accounting -- is shared and unaware of
 * which provider answered.
 *
 * CREDENTIALS. Resolved server-side, never from a request and never in the frontend:
 * the school's own ai_api_keys row for config('claude.api_type'), then the platform
 * row, then ANTHROPIC_API_KEY -- the lookup the content generator and every other
 * provider already use (ProviderKeyResolver).
 *
 * REQUEST. model, max_tokens, system and one user message. No temperature, top_p or
 * top_k: Claude Opus 5 rejects all three with HTTP 400 (see config/claude.php), unlike
 * the DeepSeek call. Plain HTTP rather than the SDK because this is a single short
 * request, and so it runs wherever the app does without the SDK being installed.
 */
class ClaudeQuestionClient
{
    /** @param Closure(int|string|null): ?string|null $keyResolver override for tests */
    public function __construct(private ?Closure $keyResolver = null)
    {
    }

    /**
     * @return array{ok: bool, error?: string, content?: string, model?: string, finish_reason?: ?string, usage?: array<string, int>}
     */
    public function complete(string $system, string $user, int|string|null $subInstituteId = null): array
    {
        $apiKey = $this->resolveKey($subInstituteId);
        if ($apiKey === null || $apiKey === '') {
            return ['ok' => false, 'error' => 'Claude API key is not configured. Set ANTHROPIC_API_KEY in the backend .env, or add an ai_api_keys row with api_type='
                . config('claude.api_type', 'ANTHROPIC_API_KEY') . ' and status=1.'];
        }

        $cfg = (array) config('claude.question_generation', []);
        $model = (string) ($cfg['model'] ?? config('claude.model', 'claude-opus-5'));
        $timeout = max(10, (int) ($cfg['timeout_seconds'] ?? 180));

        try {
            @set_time_limit($timeout + 60);

            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => (string) ($cfg['api_version'] ?? '2023-06-01'),
                'content-type' => 'application/json',
            ])
                ->timeout($timeout)
                ->connectTimeout(20)
                ->post(rtrim((string) ($cfg['base_url'] ?? 'https://api.anthropic.com'), '/') . '/v1/messages', [
                    'model' => $model,
                    'max_tokens' => (int) ($cfg['max_output_tokens'] ?? 8000),
                    'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $user]],
                ]);

            if (!$response->successful()) {
                return ['ok' => false, 'error' => $this->httpError($response->status(), (string) $response->json('error.message', ''), $model)];
            }

            $json = (array) $response->json();
        } catch (Throwable $e) {
            Log::error('Claude question generation call failed: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Claude call exception: ' . $e->getMessage()];
        }

        $stop = $json['stop_reason'] ?? null;

        // A policy decline is HTTP 200 with stop_reason "refusal"; its content is not a
        // question set, so it has to be recognised before the content is read.
        if ($stop === 'refusal') {
            return ['ok' => false, 'error' => 'Claude declined to write these questions.'];
        }

        // With thinking on, content[0] can be a thinking block: join every text block.
        $text = '';
        foreach ((array) ($json['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }
        if (trim($text) === '') {
            return ['ok' => false, 'error' => 'Claude returned an empty message.'];
        }

        return [
            'ok' => true,
            'content' => $text,
            'model' => (string) ($json['model'] ?? $model),
            // Mapped onto the vocabulary the caller already checks.
            'finish_reason' => $stop === 'max_tokens' ? 'length' : $stop,
            'usage' => [
                'prompt_tokens' => (int) ($json['usage']['input_tokens'] ?? 0),
                'completion_tokens' => (int) ($json['usage']['output_tokens'] ?? 0),
            ],
        ];
    }

    private function resolveKey(int|string|null $subInstituteId): ?string
    {
        if ($this->keyResolver !== null) {
            return ($this->keyResolver)($subInstituteId);
        }

        $key = app(ProviderKeyResolver::class)->resolve(
            (string) config('claude.api_type', 'ANTHROPIC_API_KEY'),
            $subInstituteId,
            config('claude.api_key'),
        );

        return $key['api_key'] ?? null;
    }

    private function httpError(int $status, string $detail, string $model): string
    {
        $base = match (true) {
            $status === 401 => 'The Claude API key was rejected. Check ANTHROPIC_API_KEY.',
            $status === 403 => "The Claude API key is not permitted to use {$model}.",
            $status === 404 => "Claude model \"{$model}\" was not found. Check ANTHROPIC_MODEL.",
            $status === 429 => 'Claude is rate limiting this account. Try again shortly.',
            $status >= 500 => "Claude is temporarily unavailable (HTTP {$status}). Try again shortly.",
            default => "Claude rejected the request (HTTP {$status}).",
        };

        return $detail !== '' && !in_array($status, [401, 403, 404, 429], true) ? "{$base} {$detail}" : $base;
    }
}
