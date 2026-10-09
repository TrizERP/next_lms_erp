<?php

namespace App\Domain\AI\Support;

use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use JsonException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

/**
 * The estate's model client, speaking Google's REST API directly.
 *
 * Direct HTTP rather than a wrapper package: the surface used here is one endpoint and
 * four request fields, the estate already talks to OpenRouter this way, and a dependency
 * whose only job is to build a JSON body is a dependency to keep patched for no gain.
 *
 * Key resolution matches what the platform already does — the `ai_api_keys` pool through
 * `getAIKey()`, with an env fallback so a key-table outage does not take the feature down
 * with it.
 *
 * The shape conversion is the whole of the work here, and it is not cosmetic. Gemini has
 * no `system` role and no flat `messages` array: instructions go in
 * `system_instruction`, turns go in `contents` with `parts`, and the assistant role is
 * called `model`. Passing an OpenAI-shaped body through gets a 400 that reads like a
 * schema complaint rather than "wrong provider", which is why this lives behind the
 * ModelClient interface instead of at each call site.
 */
class GeminiClient implements ModelClient, SupportsImageGeneration, SupportsVisionAnalysis
{
    /** The school this client resolves credentials for. Null means platform keys only. */
    private int|string|null $subInstituteId = null;

    public function forInstitute(int|string|null $subInstituteId): static
    {
        $clone = clone $this;
        $clone->subInstituteId = $subInstituteId;

        return $clone;
    }

    /**
     * The configuration a module resolved to, when one was resolved.
     *
     * Null for every call site that predates centralised configuration, and both
     * methods that read it fall back to exactly what they did before — so an
     * unconfigured caller cannot tell this field exists.
     */
    private ?ResolvedAiConfiguration $configuration = null;

    public function withConfiguration(ResolvedAiConfiguration $configuration): static
    {
        $clone = clone $this;
        $clone->configuration = $configuration;

        return $clone;
    }

    private const DEFAULT_MAX_TOKENS = 1466;

    private const DEFAULT_TIMEOUT = 45;

    /**
     * Statuses worth trying again, and only these.
     *
     * All five mean "not now" rather than "not ever": the request was well formed and
     * the credential was accepted, and the same bytes sent a moment later usually
     * succeed. 429 is deliberately absent — see the note at the retry itself.
     */
    private const RETRY_STATUSES = [500, 502, 503, 504, 529];

    /**
     * How many times a "not now" response lets this attempt the call, in total —
     * `Http::retry($times, ...)`'s own parameter name is `$times`, not "retries after
     * the first", and it is easy to misread as the latter.
     *
     * Was 4 (five attempts total, four waits). Measured live: with `DEFAULT_TIMEOUT`
     * at 45s per attempt and `backoff()` widening geometrically, a run where every
     * attempt failed took roughly a minute end to end — and something in front of
     * this call (observed on a Windows box serving Laravel through `php artisan
     * serve`, with no Apache or PHP-FPM in front of it — most likely Node's own
     * `fetch()`, since PHP's own execution limit was confirmed unlimited here) was
     * cutting the connection at almost exactly that mark, turning a slow-but-honest
     * 422 into a bare 500 the caller could not read anything useful from. Three
     * attempts (two waits) keeps the behaviour that actually mattered in practice —
     * the second attempt is the one that has been observed to succeed after a first
     * 503 — while finishing with enough margin that a caller's own timeout, whatever
     * and wherever it is, is not a race this loses.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * How long to wait before attempt N, widening each time.
     *
     * A flat 750ms three times rode out a hiccup and nothing more: a capacity spike on
     * a popular model lasts seconds, not milliseconds, so three attempts inside 1.5s
     * were really one attempt with extra steps — which is how a 503 reached a user who
     * had done nothing wrong. Widening to roughly 0.7s, 2s and 4.5s covers about seven
     * seconds of provider overload, which is the shape these spikes actually have.
     *
     * The jitter matters more than it looks. Without it, every request that met the
     * same spike retries at the same instant and re-creates it — the thundering herd
     * that turns a brief overload into a sustained one.
     */
    private static function backoff(int $attempt): int
    {
        $base = (int) (700 * (2.5 ** ($attempt - 1)));

        return $base + random_int(0, (int) ($base * 0.25));
    }

    /**
     * What a failed provider response means, said in words a reader can act on.
     *
     * This used to be the status code and 300 characters of the provider's own JSON,
     * which is how `The AI provider returned 503: {"error":{"code":503,"message":"This
     * model is currently experiencing high demand...` ended up in front of somebody
     * pressing a button in the Fees module. The status is the one thing a caller cannot
     * interpret and this class can, so it is interpreted here — once, for every caller —
     * and the provider's own text is kept on the end for whoever is diagnosing rather
     * than using.
     */
    private function failure(\Illuminate\Http\Client\Response $response): RuntimeException
    {
        $status = $response->status();
        $detail = trim(mb_substr($response->body(), 0, 300));

        $explanation = match (true) {
            in_array($status, self::RETRY_STATUSES, true) => 'The AI model is busy at the provider and did not '
                . 'answer after several attempts. Nothing is wrong with the request or the configuration — '
                . 'try again in a moment.',
            $status === 429 => 'The AI provider\'s rate limit has been reached for this key. Wait a minute '
                . 'before trying again, or raise the quota on the account the key belongs to.',
            in_array($status, [401, 403], true) => 'The AI provider rejected the configured credential. The key '
                . 'for this module is missing, disabled or no longer valid — an administrator can fix it in '
                . 'AI & Intelligence → AI Providers.',
            $status === 404 => 'The AI provider does not recognise the configured model. It may have been '
                . 'retired — check the model name in AI & Intelligence → AI Providers.',
            $status === 400 => 'The AI provider rejected the request as malformed, which is a fault in this '
                . 'platform rather than in what was asked.',
            default => sprintf('The AI provider returned %d.', $status),
        };

        return new RuntimeException($detail === ''
            ? $explanation
            : sprintf('%s (provider said: %s)', $explanation, $detail));
    }

    public function isConfigured(): bool
    {
        return $this->resolveApiKey() !== null;
    }

    public function defaultModel(): string
    {
        return $this->configuration?->model
            ?? (string) config('ai.provider.gemini.model', 'gemini-2.5-flash');
    }

    public function chat(
        array $messages,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        bool $expectJson = false,
        ?int $timeout = null,
    ): ?string {
        $key = $this->resolveApiKey();

        if ($key === null) {
            throw new RuntimeException('No usable AI API key is configured.');
        }

        [$system, $contents] = $this->split($messages);

        $generation = array_filter([
            // Deliberately NOT $key['api_limit'], which OpenRouterClient uses as its
            // ceiling. The `gemini` rows in ai_api_keys carry api_limit = 26 — whatever
            // that column means for them, it is not an output-token budget: verified
            // live, gemini-2.5-flash spends output tokens on reasoning before emitting
            // any text, so a ceiling that low returns HTTP 200 with an empty candidate.
            // The brain would go quiet rather than error.
            'maxOutputTokens' => $maxTokens ?? (int) config(
                'ai.provider.gemini.max_output_tokens',
                self::DEFAULT_MAX_TOKENS
            ),
            'temperature' => $temperature,
            // Gemini enforces JSON structurally rather than by instruction, which
            // removes the "prose wrapped around JSON" failure a validator would
            // otherwise have to recover from.
            'responseMimeType' => $expectJson ? 'application/json' : null,
        ], static fn ($value) => $value !== null);

        $body = array_filter([
            'contents' => $contents,
            'systemInstruction' => $system === null
                ? null
                : ['parts' => [['text' => $system]]],
            'generationConfig' => $generation === [] ? null : $generation,
        ], static fn ($value) => $value !== null);

        $response = Http::withHeaders([
            // Header rather than ?key= so the credential stays out of access logs
            // and any URL the exception message might carry.
            'x-goog-api-key' => $key['api_key'],
            'Content-Type' => 'application/json',
        ])
            ->timeout($timeout ?? (int) config('ai.provider.gemini.timeout', self::DEFAULT_TIMEOUT))
            // gemini-2.5-flash answers 503 "high demand" intermittently — observed
            // twice while wiring this up, on a key that answers 200 on the next
            // attempt. Without a retry that lands as a null plan, and the lifecycle
            // silently drops to its deterministic planner: the turn still answers, just
            // less well, which is the kind of degradation nobody reports as a bug.
            //
            // 429 is deliberately NOT retried. It is a rate limit, not a fault, and
            // retrying it on a short delay is what *causes* the next one: an earlier
            // version of this retried 429 after 500ms and turned a six-call loop into
            // twenty-four requests, which tripped the per-minute limit that had not
            // been tripped before. A 429 surfaces immediately so the caller degrades
            // once rather than hammering. A 400 or 401 is a real fault and also surfaces.
            ->retry(self::MAX_ATTEMPTS, self::backoff(...), function ($exception, $request): bool {
                // `response` is a public property on RequestException, not a method.
                // This read `method_exists($exception, 'response')`, which is false for
                // every exception Laravel hands this callback — so the status was always
                // null, nothing ever matched, and the retry that this whole block exists
                // for never once fired. A 503 went straight to whoever pressed the
                // button, which is exactly what the comment below says it must not.
                $status = $exception instanceof RequestException && $exception->response !== null
                    ? $exception->response->status()
                    : null;

                return in_array($status, self::RETRY_STATUSES, true);
            }, throw: false)
            ->post($this->endpoint($model ?? $this->defaultModel()), $body);

        if (! $response->successful()) {
            throw $this->failure($response);
        }

        return $this->textFrom($response->json());
    }

    /**
     * Read one image and answer in JSON.
     *
     * Same endpoint, key resolution and error handling as chat(); the only difference is
     * a `inlineData` part carrying the image next to the text. Temperature is pinned low
     * because the caller is asking where things ARE, which has one answer, not for
     * variety. A 429 surfaces immediately, as in chat().
     */
    public function analyzeImage(
        string $prompt,
        string $imageBytes,
        string $mimeType,
        ?string $model = null,
        ?int $timeout = null,
    ): ?string {
        $key = $this->resolveApiKey();

        if ($key === null) {
            throw new RuntimeException('No usable AI API key is configured.');
        }

        $body = [
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt],
                    ['inlineData' => ['mimeType' => $mimeType, 'data' => base64_encode($imageBytes)]],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
                // Higher than chat()'s default: gemini-2.5-flash spends output tokens on
                // reasoning before any text, and a box list cut off mid-array is unusable.
                'maxOutputTokens' => (int) config('ai.provider.gemini.vision_max_output_tokens', 4096),
            ],
        ];

        $response = Http::withHeaders([
            'x-goog-api-key' => $key['api_key'],
            'Content-Type' => 'application/json',
        ])
            ->timeout($timeout ?? (int) config('ai.provider.gemini.timeout', self::DEFAULT_TIMEOUT))
            ->retry(self::MAX_ATTEMPTS, self::backoff(...), function ($exception, $request): bool {
                $status = $exception instanceof RequestException && $exception->response !== null
                    ? $exception->response->status()
                    : null;

                return in_array($status, self::RETRY_STATUSES, true);
            }, throw: false)
            ->post($this->endpoint($model ?? $this->defaultModel()), $body);

        if (! $response->successful()) {
            throw $this->failure($response);
        }

        return $this->textFrom($response->json());
    }

    /**
     * Ask for an image back, from the same REST endpoint `chat()` already calls.
     *
     * Gemini's image-output models take no shape this class doesn't already build:
     * same `contents`/`generationConfig` body, same endpoint, same error handling. The
     * only difference is `responseModalities`, and the response carries an `inlineData`
     * part (base64 image bytes) alongside an ordinary `text` part — requesting both
     * TEXT and IMAGE gets a caption that is grounded in the same generation as the
     * image, not a second, separately-generated description that could drift from it.
     */
    public function generateImage(string $prompt, ?string $model = null, ?int $timeout = null): ImageGenerationResult
    {
        $key = $this->resolveApiKey();

        if ($key === null) {
            throw new RuntimeException('No usable AI API key is configured.');
        }

        $body = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        $response = Http::withHeaders([
            'x-goog-api-key' => $key['api_key'],
            'Content-Type' => 'application/json',
        ])
            ->timeout($timeout ?? (int) config('ai.provider.gemini.timeout', self::DEFAULT_TIMEOUT))
            ->retry(4, self::backoff(...), function ($exception, $request): bool {
                $status = $exception instanceof RequestException && $exception->response !== null
                    ? $exception->response->status()
                    : null;

                return in_array($status, self::RETRY_STATUSES, true);
            }, throw: false)
            ->post($this->endpoint($model ?? $this->defaultImageModel()), $body);

        if (! $response->successful()) {
            throw $this->failure($response);
        }

        return $this->imageFrom($response->json());
    }

    /**
     * The image-capable model to use when a caller (template, module configuration)
     * names none. Deliberately a separate config key from `defaultModel()` — a school
     * that configured a text model for the `image_generation` module's provider row
     * still needs an image-capable one here, and the two are never interchangeable.
     *
     * `gemini-2.5-flash-image` is Google's deprecated image model (shutdown scheduled
     * 2026-10-02) — `gemini-3.1-flash-image` is its current replacement, verified live
     * against this estate's own key (`GET /v1beta/models`) to exist and accept the same
     * request shape. Both are billing-tier-only on Google's side; neither is free-tier
     * quota, so this choice is unrelated to the "quota exceeded" failures a school sees
     * before enabling billing on its Google Cloud project.
     */
    private function defaultImageModel(): string
    {
        return $this->configuration?->model
            ?? (string) config('ai.provider.gemini.image_model', 'gemini-3.1-flash-image');
    }

    /**
     * The first inline image and any accompanying caption text from a response.
     *
     * @param  array<string, mixed>|null  $payload
     */
    private function imageFrom(?array $payload): ImageGenerationResult
    {
        $parts = $payload['candidates'][0]['content']['parts'] ?? null;

        if (! is_array($parts)) {
            throw new RuntimeException('The AI provider returned no image.');
        }

        $mimeType = null;
        $base64Data = null;
        $caption = null;

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }

            if (isset($part['inlineData']['data']) && $base64Data === null) {
                $mimeType = (string) ($part['inlineData']['mimeType'] ?? 'image/png');
                $base64Data = (string) $part['inlineData']['data'];

                continue;
            }

            if (isset($part['text'])) {
                $caption = trim(($caption ?? '') . ' ' . (string) $part['text']);
            }
        }

        if ($base64Data === null) {
            // A safety block or a text-only reply both land here as "no usable image" —
            // the caller (GenerationService) treats this the same as any other failed
            // generation, which is what lets the frontend fall back cleanly.
            throw new RuntimeException('The AI provider did not return an image.');
        }

        return new ImageGenerationResult($mimeType ?? 'image/png', $base64Data, $caption !== null && $caption !== '' ? $caption : null);
    }

    public function json(
        array $messages,
        ?string $model = null,
        int $maxTokens = 900,
        float $temperature = 0.0,
    ): ?array {
        try {
            $content = $this->chat(
                $messages,
                $model,
                maxTokens: $maxTokens,
                temperature: $temperature,
                expectJson: true,
            );
        } catch (Throwable) {
            return null;
        }

        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        try {
            $decoded = json_decode($this->unfence($content), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function stream(
        array $messages,
        callable $onDelta,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
    ): ?string {
        $key = $this->resolveApiKey();

        if ($key === null) {
            throw new RuntimeException('No usable AI API key is configured.');
        }

        [$system, $contents] = $this->split($messages);

        $body = array_filter([
            'contents' => $contents,
            'systemInstruction' => $system === null ? null : ['parts' => [['text' => $system]]],
            'generationConfig' => array_filter([
                'maxOutputTokens' => $maxTokens ?? (int) config(
                    'ai.provider.gemini.max_output_tokens',
                    self::DEFAULT_MAX_TOKENS
                ),
                'temperature' => $temperature,
            ], static fn ($value) => $value !== null),
        ], static fn ($value) => $value !== null);

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $key['api_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'text/event-stream',
                // Without this the response is gzipped and cURL buffers the whole body
                // to decompress it: measured, every SSE event then arrived in a single
                // batch at the end, which is a stream in name only. Asking for identity
                // encoding restored progressive delivery.
                'Accept-Encoding' => 'identity',
            ])
                ->timeout((int) config('ai.provider.gemini.timeout', self::DEFAULT_TIMEOUT))
                // Hand back the PSR-7 stream instead of buffering the whole body, which
                // is the entire point: Guzzle would otherwise wait for the last byte and
                // the "stream" would arrive in one piece at the end.
                ->withOptions(['stream' => true, 'decode_content' => false])
                ->post($this->endpoint($model ?? $this->defaultModel(), stream: true), $body);

            if (! $response->successful()) {
                throw $this->failure($response);
            }

            return $this->consume($response->toPsrResponse()->getBody(), $onDelta);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            // Streaming is a delivery optimisation, not a capability. A transport that
            // cannot hold a long-lived body — a proxy, a buffering SAPI — should still
            // produce an answer, arriving in one piece.
            $text = $this->chat($messages, $model, $maxTokens, $temperature);

            if (is_string($text) && $text !== '') {
                $onDelta($text);
            }

            return $text;
        }
    }

    /**
     * Read Gemini's SSE body, emitting each text fragment as it lands.
     *
     * The framing is `data: {json}` per event, with blank lines between. Chunks arrive
     * on arbitrary boundaries — a single read can hold half an event — so the buffer is
     * only consumed up to the last complete newline and the remainder carries forward.
     * Splitting on chunk boundaries instead is the classic way this produces valid-looking
     * JSON that is silently truncated.
     *
     * @param  callable(string): void  $onDelta
     */
    private function consume(StreamInterface $body, callable $onDelta): string
    {
        $buffer = '';
        $full = '';

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newline));
                $buffer = substr($buffer, $newline + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $payload = trim(substr($line, 5));

                if ($payload === '' || $payload === '[DONE]') {
                    continue;
                }

                $decoded = json_decode($payload, true);

                if (! is_array($decoded)) {
                    continue;
                }

                $text = $this->textFrom($decoded);

                if ($text !== null && $text !== '') {
                    $full .= $text;
                    $onDelta($text);
                }
            }
        }

        return $full;
    }

    private function endpoint(string $model, bool $stream = false): string
    {
        $base = rtrim((string) config(
            'ai.provider.gemini.base_url',
            'https://generativelanguage.googleapis.com/v1beta'
        ), '/');

        // `?alt=sse` matters: without it streamGenerateContent returns a JSON *array*
        // that only closes at the end, which reads as a stream and behaves as a
        // single blocking response.
        return $stream
            ? sprintf('%s/models/%s:streamGenerateContent?alt=sse', $base, $model)
            : sprintf('%s/models/%s:generateContent', $base, $model);
    }

    /**
     * Turn OpenAI-shaped messages into Gemini's system instruction plus contents.
     *
     * System messages are concatenated rather than only the first being kept: a caller
     * that sets a persona and then appends a constraint would otherwise silently lose
     * the constraint, which is the kind of failure that shows up as a model "ignoring"
     * an instruction it was never given.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @return array{0:string|null, 1:array<int, array<string, mixed>>}
     */
    private function split(array $messages): array
    {
        $system = [];
        $contents = [];

        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $text = (string) ($message['content'] ?? '');

            if ($text === '') {
                continue;
            }

            if ($role === 'system') {
                $system[] = $text;

                continue;
            }

            $contents[] = [
                // Gemini calls the assistant "model"; anything else is a user turn.
                'role' => $role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $text]],
            ];
        }

        return [
            $system === [] ? null : implode("\n\n", $system),
            $contents,
        ];
    }

    /**
     * The text of the first candidate, or null.
     *
     * A response can come back well-formed and empty — a safety block, or a candidate
     * truncated at maxOutputTokens before any text was emitted. Both are "no usable
     * answer" rather than an error, and both callers already handle null.
     *
     * @param  array<string, mixed>|null  $payload
     */
    private function textFrom(?array $payload): ?string
    {
        $parts = $payload['candidates'][0]['content']['parts'] ?? null;

        if (! is_array($parts)) {
            return null;
        }

        $text = '';

        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text'])) {
                $text .= (string) $part['text'];
            }
        }

        return $text === '' ? null : $text;
    }

    /**
     * Strip a ```json fence if the model wrapped its object in one.
     *
     * responseMimeType makes this rare rather than impossible, and a fenced body is a
     * decode failure that looks like a model fault when it is a formatting one.
     */
    private function unfence(string $content): string
    {
        $trimmed = trim($content);

        if (! str_starts_with($trimmed, '```')) {
            return $trimmed;
        }

        $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;

        return trim(preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed);
    }

    /**
     * The key to use, from the pool if it has one and the environment otherwise.
     *
     * Deliberately not `getAIKey()`, which the OpenRouter client uses. That helper is
     * `where(api_type)->where(status)->first()` with **no ordering**, which is a coin
     * flip whenever a type has more than one active row — and this pool holds exactly
     * that: two active `gemini` keys, of which id=1 answers 400 API_KEY_INVALID and
     * id=2 answers 200. An unordered first() would pick the dead one on most engines
     * and take the brain down while a working key sat beside it.
     *
     * Newest active row wins, which is the convention a rotated key expects.
     *
     * @return array{api_key:string, id:mixed}|null
     */
    private function resolveApiKey(): ?array
    {
        // A module's saved configuration has already been through the full precedence
        // chain — including this pool — so it is taken as decided rather than looked up
        // a second time and possibly disagreed with.
        if ($this->configuration?->hasKey()) {
            return [
                'api_key' => $this->configuration->apiKey,
                'id' => $this->configuration->keyId,
            ];
        }

        // The ordering and env fallback described above now live in
        // ProviderKeyResolver, which adds the tenant branch this lookup was missing
        // and is shared with the OpenRouter client and question generation — three
        // copies of one rule was how they came to disagree.
        return app(ProviderKeyResolver::class)->resolve(
            (string) config('ai.provider.gemini.api_type', 'gemini'),
            $this->subInstituteId,
            config('ai.provider.gemini.api_key'),
        );
    }
}
