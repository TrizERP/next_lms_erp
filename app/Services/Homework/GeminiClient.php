<?php

namespace App\Services\Homework;

use App\Domain\AI\Configuration\AiConfigurationException;
use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use App\Services\Homework\Exceptions\ModelQuotaExhaustedException;
use App\Services\Homework\Exceptions\ProviderBusyException;
use App\Services\Homework\Exceptions\ProviderConfigurationException;
use App\Services\Homework\Exceptions\RateLimitedException;
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

    /** The school this client works for; null means platform keys only. */
    private int|string|null $subInstituteId = null;

    /** Resolved lazily, once per client, from the `evaluation_ai` module's saved configuration. */
    private ?ResolvedAiConfiguration $resolved = null;

    private ?string $configurationProblem = null;

    /** True when a configuration was handed in rather than resolved for a school. */
    private bool $pinned = false;

    private ?string $apiKey = null;
    private string $model = '';

    /** The configuration that answered the last call, for audit rows that record provider/model. */
    private ?ResolvedAiConfiguration $used = null;

    /**
     * A copy of this client that resolves its provider, model and key for one school.
     *
     * Provider, model and key are never read from the environment: what the administrator
     * saved for `evaluation_ai` is what is called.
     */
    public function forInstitute(int|string|null $subInstituteId): static
    {
        $copy = clone $this;
        $copy->subInstituteId = $subInstituteId;

        // A configuration handed in with forConfiguration() was already decided; keep it.
        if (! $this->pinned) {
            $copy->resolved = null;
            $copy->configurationProblem = null;
            $copy->apiKey = null;
            $copy->model = '';
        }

        return $copy;
    }

    /**
     * Resolve the module's configuration. This client sends whole documents in Gemini's own
     * wire format, so it can only serve a Gemini configuration; anything else is refused with a
     * reason an administrator can act on.
     */
    private function resolveConfiguration(): void
    {
        if ($this->resolved !== null || $this->configurationProblem !== null) {
            return;
        }

        try {
            $configuration = app(AiModelClientFactory::class)
                ->configurationFor('evaluation_ai', $this->subInstituteId);
        } catch (\Throwable $e) {
            // A diagnostic or an evaluation must say what is wrong, not fail on the way to saying it.
            $this->configurationProblem = $e instanceof AiConfigurationException
                ? $e->getMessage()
                : 'The AI configuration for Homework & Assignment Evaluation could not be read. Check AI Providers.';

            return;
        }

        $this->accept($configuration);
    }

    /**
     * A copy that uses an already-resolved configuration instead of looking one up.
     *
     * For callers that have resolved once and for tests, which must not need a database. The
     * same Gemini-only and key checks apply, and the fallback model comes from the same
     * configuration key, not from the module's saved chain.
     */
    public function forConfiguration(ResolvedAiConfiguration $configuration): static
    {
        $copy = clone $this;
        $copy->resolved = null;
        $copy->configurationProblem = null;
        $copy->apiKey = null;
        $copy->model = '';
        $copy->pinned = true;
        $copy->accept($configuration);

        return $copy;
    }

    private function accept(ResolvedAiConfiguration $configuration): void
    {
        if (! $configuration->hasKey()) {
            $this->configurationProblem = AiConfigurationException::notConfigured(
                'evaluation_ai',
                'Homework & Assignment Evaluation'
            )->getMessage();

            return;
        }

        if ($configuration->provider !== 'gemini') {
            $this->configurationProblem = 'Homework & Assignment Evaluation reads whole scanned documents and '
                . 'can only do that with Gemini, but it is configured to use "' . $configuration->provider . '". '
                . 'An administrator needs to set its provider to Gemini in AI & Intelligence → AI Providers.';

            return;
        }

        $this->resolved = $configuration->forModule('evaluation_ai');
        $this->apiKey = (string) $configuration->apiKey;
        $this->model = (string) ($configuration->model ?? '');

        if ($this->model === '') {
            $this->resolved = null;
            $this->apiKey = null;
            $this->configurationProblem = 'Homework & Assignment Evaluation has a Gemini key but no model chosen. '
                . 'An administrator needs to pick a model in AI & Intelligence → AI Providers.';
        }
    }

    /** What provider/model answered the last call, or the one that would answer next. */
    public function resolvedConfiguration(): ?ResolvedAiConfiguration
    {
        if ($this->used !== null) {
            return $this->used;
        }

        $this->resolveConfiguration();

        return $this->resolved;
    }

    /**
     * Seconds to wait on one request.
     *
     * Read from config so a cached config still honours the timeout setting.
     * The 90 here is deliberate and is NOT config('gemini.request_timeout')'s 45:
     * this client sends whole scanned documents, which take longer than the
     * brain's short text calls, and it has always defaulted to 90.
     */
    private function requestTimeout(): int
    {
        return (int) config('ai.provider.gemini.document_timeout', 90);
    }

    public function isConfigured(): bool
    {
        $this->resolveConfiguration();

        return !empty($this->apiKey);
    }

    /**
     * What is wrong with the configured key that can be told from its FORMAT alone, or null.
     *
     * This calls the Gemini Developer API (generativelanguage.googleapis.com), which accepts
     * only API keys made in Google AI Studio: "AIza", 39 characters. A Vertex AI key ("AQ.",
     * 53 characters) or an OAuth access token ("ya29.") is a different credential for a
     * different service and is refused with a 401 that says nothing about which is wrong.
     * Saying so up front saves an administrator the guesswork.
     *
     * Made without any request, and without ever repeating the key: only what KIND it looks
     * like. An unfamiliar format returns null rather than a guess -- Google may well add
     * formats, and a false alarm is worse than none; the 401 itself still reports a real rejection.
     */
    public function credentialProblem(): ?string
    {
        $this->resolveConfiguration();

        if ($this->configurationProblem !== null) {
            return $this->configurationProblem;
        }

        $key = trim((string) $this->apiKey);

        if ($key === '') {
            return 'The AI service is not set up: no Gemini API key is saved. An administrator needs to add a Gemini API key from Google AI Studio in AI & Intelligence → AI Providers.';
        }

        if (str_starts_with($key, 'AQ.')) {
            return 'The key in use looks like a Vertex AI key, which this service cannot use. It needs a Gemini API key from Google AI Studio (starts with “AIza”).';
        }

        if (str_starts_with($key, 'ya29.')) {
            return 'The key in use looks like an OAuth access token, not an API key. It needs a Gemini API key from Google AI Studio (starts with “AIza”).';
        }

        return null;
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
            throw new ProviderConfigurationException(
                $this->configurationProblem ?? 'Gemini API key is not configured. An administrator needs to add one in AI & Intelligence → AI Providers.'
            );
        }

        $this->used = $this->resolved;

        try {
            return $this->send($this->model, $parts, $generationConfig, (string) $this->apiKey);
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

            [$fallback, $fallbackKey, $fallbackConfiguration] = $fallback;

            Log::warning('Gemini homework call falling back to another model', [
                'from' => $this->model,
                'to' => $fallback,
                'reason' => $unavailable->getMessage(),
            ]);

            $result = $this->send($fallback, $parts, $generationConfig, $fallbackKey);
            $this->used = $fallbackConfiguration;

            return $result;
        }
    }

    /**
     * The model to try when the configured one is busy.
     *
     * Empty, or the same id as the configured model, means no fallback: the
     * caller then sees the original ProviderBusyException rather than the same
     * refusal twice.
     */
    private function fallbackModel(): ?array
    {
        // The next Gemini entry the administrator ranked for this module, with its own key.
        try {
            $chain = $this->pinned
                ? []
                : app(AiModelClientFactory::class)->chainFor('evaluation_ai', $this->subInstituteId);
        } catch (AiConfigurationException) {
            $chain = [];
        }

        foreach ($chain as $candidate) {
            if ($candidate->provider === 'gemini' && $candidate->hasKey()
                && trim((string) $candidate->model) !== '' && $candidate->model !== $this->model) {
                return [(string) $candidate->model, (string) $candidate->apiKey, $candidate->forModule('evaluation_ai')];
            }
        }

        // Otherwise a same-key alternative model, a model name rather than a credential.
        // No default id: the old default, gemini-2.5-flash, is one config/ai.php remaps as
        // retired, so "falling back" to it was a second refusal.
        $fallback = trim((string) config('ai.provider.gemini.fallback_model', ''));

        return ($fallback === '' || $fallback === $this->model)
            ? null
            : [$fallback, (string) $this->apiKey, $this->resolved];
    }

    /**
     * @return array{text: string, raw: array, model: string}
     */
    private function send(string $model, array $parts, array $generationConfig, string $apiKey): array
    {

        // `retry(1, 500)` meant one attempt: Laravel counts total tries, not extra
        // ones, so nothing was ever retried and gemini-2.5-flash's intermittent
        // 503 "this model is currently experiencing high demand" landed straight
        // in a student's `ai_failure_reason` as a terminal "Evaluation Failed".
        // Four attempts over roughly seven seconds ride out a capacity spike,
        // which is the shape these actually have.
        $response = Http::timeout($this->requestTimeout())
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
                'x-goog-api-key' => $apiKey,
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
                // Enough to diagnose, not the whole body.
                'body' => mb_substr($response->body(), 0, 1000),
            ]);

            throw $this->failure($response, $model);
        }

        $payload = $response->json() ?? [];
        $text = $this->extractText($payload);

        if ($text === '') {
            $blocked = trim((string) ($payload['promptFeedback']['blockReason'] ?? ''));

            throw new \RuntimeException(
                $blocked !== ''
                    ? 'The AI provider declined to read this file (' . $blocked . ').'
                    : 'Gemini returned an empty response.'
            );
        }

        return [
            'text' => $text,
            'raw' => $payload,
            'model' => $model,
            // Additive: callers that care whether the answer was cut short
            // (the exam reader and grader, whose JSON is useless half-written)
            // can check it. Homework callers ignore it and behave as before.
            'finish_reason' => (string) ($payload['candidates'][0]['finishReason'] ?? ''),
        ];
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
            // When the key's format already says why (a Vertex key on the Developer API), say that
            // instead of the generic line: `failure_reason` keeps only 250 characters, and both
            // together ran past it, cutting the sentence that names the fix mid-word.
            in_array($status, [401, 403], true) => ($hint = $this->credentialProblem()) !== null
                ? 'The AI provider rejected the saved Gemini key. ' . $hint
                : 'The AI provider rejected the configured credential. '
                    . 'The saved Gemini key is missing, disabled or no longer valid - an administrator can fix it in AI Providers.',
            $status === 404 => 'The AI provider does not recognise the model it was asked for ('
                . $model . '). It may have been retired - check the model saved for Homework & Assignment Evaluation '
                . 'in AI Providers, and the retired-id remap in config/ai.php that may be rewriting it.',
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

        // Still a RuntimeException to every existing caller; the type only
        // lets a queued job tell "wait a minute" from "will never work".
        if ($status === 429) {
            return new RateLimitedException($message);
        }

        // A key the provider refuses will be refused for every sheet until it is replaced.
        if (in_array($status, [401, 403], true)) {
            return new ProviderConfigurationException($message);
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
