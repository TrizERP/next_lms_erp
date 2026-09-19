<?php

namespace App\Services\Homework;

use App\Services\Homework\Exceptions\ModelQuotaExhaustedException;
use App\Services\Homework\Exceptions\ProviderBusyException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
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
    /**
     * Statuses worth trying again, and only these.
     *
     * All five mean "not now" rather than "not ever": the request was well
     * formed and the credential was accepted, and the same bytes sent a moment
     * later usually succeed. 429 is deliberately absent - it is a rate limit,
     * and retrying it on a short delay is what causes the next one.
     */
    private const RETRY_STATUSES = [500, 502, 503, 504, 529];

    private const MAX_ATTEMPTS = 4;

    private ?string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key') ?: env('GEMINI_API_KEY');
        $this->model = config('gemini.model');
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

        try {
            return $this->send($this->model, $parts, $generationConfig);
        } catch (ProviderBusyException|ModelQuotaExhaustedException $unavailable) {
            // Both mean "this model cannot answer, another one can": a capacity
            // spike, or that model's own daily allowance spent. Neither is a
            // reason to lose a student's submission. The configured model still
            // grades whenever it can; this is only the difference between a mark
            // and a failure.
            $fallback = $this->fallbackModel();

            if ($fallback === null) {
                throw $unavailable;
            }

            Log::warning('Gemini homework call falling back to another model', [
                'from' => $this->model,
                'to' => $fallback,
                'reason' => $unavailable->getMessage(),
            ]);

            return $this->send($fallback, $parts, $generationConfig);
        }
    }

    /**
     * The model to try when the configured one is busy.
     *
     * Empty, or the same id as the configured model, means no fallback: the
     * caller then sees the original ProviderBusyException rather than the same
     * refusal twice.
     */
    private function fallbackModel(): ?string
    {
        $fallback = trim((string) env('GEMINI_FALLBACK_MODEL', 'gemini-2.5-flash'));

        return ($fallback === '' || $fallback === $this->model) ? null : $fallback;
    }

    /**
     * @return array{text: string, raw: array, model: string}
     */
    private function send(string $model, array $parts, array $generationConfig): array
    {

        // `retry(1, 500)` meant one attempt: Laravel counts total tries, not extra
        // ones, so nothing was ever retried and gemini-2.5-flash's intermittent
        // 503 "this model is currently experiencing high demand" landed straight
        // in a student's `ai_failure_reason` as a terminal "Evaluation Failed".
        // Four attempts over roughly seven seconds ride out a capacity spike,
        // which is the shape these actually have.
        $response = Http::timeout((int) env('GEMINI_REQUEST_TIMEOUT', 90))
            ->retry(
                self::MAX_ATTEMPTS,
                self::backoff(...),
                // `response` is a public property on RequestException, not a
                // method - probing it with method_exists() leaves the status null
                // and the retry never fires.
                fn ($exception) => $exception instanceof RequestException
                    && $exception->response !== null
                    && in_array($exception->response->status(), self::RETRY_STATUSES, true),
                throw: false
            )
            ->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $this->apiKey,
            ])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
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
                'model' => $model,
                'status' => $response->status(),
                'attempts' => self::MAX_ATTEMPTS,
                'body' => $response->body(),
            ]);

            throw $this->failure($response, $model);
        }

        $payload = $response->json() ?? [];
        $text = $this->extractText($payload);

        if ($text === '') {
            throw new \RuntimeException('Gemini returned an empty response.');
        }

        return ['text' => $text, 'raw' => $payload, 'model' => $model];
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

    /**
     * How long to wait before attempt N, widening each time.
     *
     * A flat delay repeated three times rides out a hiccup and nothing more: a
     * capacity spike on a popular model lasts seconds. Roughly 0.7s, 2s and 4.5s
     * covers about seven seconds of it. The jitter stops every request that met
     * the same spike from retrying in the same instant and re-creating it.
     */
    private static function backoff(int $attempt): int
    {
        $base = (int) (700 * (2.5 ** ($attempt - 1)));

        return $base + random_int(0, (int) ($base * 0.25));
    }

    /**
     * What a failed provider response means, said in words a teacher can act on.
     *
     * This used to hand back the provider's own sentence - "This model is
     * currently experiencing high demand..." - which then became the whole of
     * `homework.ai_failure_reason` and was shown, verbatim, to whoever opened
     * the submission. The status is the one thing a caller cannot interpret and
     * this class can, so it is interpreted here, once, and the provider's text is
     * kept on the end for whoever is diagnosing rather than teaching.
     */
    private function failure(Response $response, string $model): \RuntimeException
    {
        $status = $response->status();
        $providerMessage = trim((string) ($response->json()['error']['message'] ?? ''));

        $explanation = match (true) {
            in_array($status, self::RETRY_STATUSES, true) => 'The AI model (' . $model . ') was busy at the '
                . 'provider and did not answer after ' . self::MAX_ATTEMPTS . ' attempts. Nothing is wrong '
                . 'with the submission or the configuration - evaluate it again in a few minutes.',
            $status === 429 && $this->isPerModelQuota($response->json()) => 'The daily free-tier allowance '
                . 'for ' . $model . ' on this key is spent. Each model has its own allowance, so another '
                . 'model can still grade - or add billing to the account the key belongs to.',
            $status === 429 => 'The rate limit for the configured AI key has been reached. Wait a minute '
                . 'before evaluating again, or raise the quota on the account the key belongs to.',
            in_array($status, [401, 403], true) => 'The AI provider rejected the configured credential. '
                . 'GEMINI_API_KEY is missing, disabled or no longer valid - an administrator can fix it.',
            $status === 404 => 'The AI provider does not recognise the model it was asked for ('
                . $model . '). It may have been retired - check GEMINI_MODEL, and the retired-id remap '
                . 'in config/ai.php that may be rewriting it.',
            $status === 400 => 'The AI provider rejected the request as malformed, which is a fault in this '
                . 'platform rather than in the submitted work.',
            default => sprintf('The AI provider returned %d.', $status),
        };

        $message = $providerMessage === ''
            ? $explanation
            : sprintf('%s (provider said: %s)', $explanation, mb_substr($providerMessage, 0, 200));

        if (in_array($status, self::RETRY_STATUSES, true)) {
            return new ProviderBusyException($message);
        }

        // A per-model quota is worth moving off; a per-project or per-minute one
        // is not, because the model this would move to shares it.
        if ($status === 429 && $this->isPerModelQuota($response->json())) {
            return new ModelQuotaExhaustedException($message);
        }

        return new \RuntimeException($message);
    }

    /**
     * Whether a 429 names one model rather than the whole key.
     *
     * Gemini reports this in `error.details[].violations[].quotaId`, e.g.
     * `GenerateRequestsPerDayPerProjectPerModel-FreeTier` - the "PerModel" in
     * that id is the whole distinction.
     */
    private function isPerModelQuota(?array $payload): bool
    {
        foreach ($payload['error']['details'] ?? [] as $detail) {
            foreach ($detail['violations'] ?? [] as $violation) {
                if (str_contains((string) ($violation['quotaId'] ?? ''), 'PerModel')) {
                    return true;
                }
            }
        }

        return false;
    }
}
