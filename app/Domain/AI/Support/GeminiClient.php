<?php

namespace App\Domain\AI\Support;

use Illuminate\Support\Facades\DB;
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
class GeminiClient implements ModelClient
{
    private const DEFAULT_MAX_TOKENS = 1466;

    private const DEFAULT_TIMEOUT = 45;

    public function isConfigured(): bool
    {
        return $this->resolveApiKey() !== null;
    }

    public function defaultModel(): string
    {
        return (string) config('ai.provider.gemini.model', 'gemini-2.5-flash');
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
            ->retry(3, 750, function ($exception, $request): bool {
                $status = method_exists($exception, 'response') && $exception->response !== null
                    ? $exception->response->status()
                    : null;

                return in_array($status, [500, 502, 503, 504], true);
            }, throw: false)
            ->post($this->endpoint($model ?? $this->defaultModel()), $body);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'The AI provider returned %d: %s',
                $response->status(),
                mb_substr($response->body(), 0, 300)
            ));
        }

        return $this->textFrom($response->json());
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
                throw new RuntimeException(sprintf(
                    'The AI provider returned %d: %s',
                    $response->status(),
                    mb_substr($response->body(), 0, 300)
                ));
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
        $apiType = (string) config('ai.provider.gemini.api_type', 'gemini');

        try {
            $row = DB::table('ai_api_keys')
                ->where('api_type', $apiType)
                ->where('status', 1)
                ->orderByDesc('id')
                ->first();

            if ($row !== null && ! empty($row->api_key)) {
                return ['api_key' => trim((string) $row->api_key), 'id' => $row->id ?? null];
            }
        } catch (Throwable) {
            // Fall through to env — a key-table outage should not stop generation.
        }

        $envKey = config('ai.provider.gemini.api_key');

        if (empty($envKey)) {
            return null;
        }

        return [
            'api_key' => trim((string) $envKey, " \t\n\r\0\x0B'\""),
            'id' => null,
        ];
    }
}
