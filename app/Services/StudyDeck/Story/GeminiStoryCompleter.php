<?php

namespace App\Services\StudyDeck\Story;

use App\Domain\AI\Support\AiProviderException;
use App\Domain\AI\Support\GeminiClient;
use App\Services\StudyDeck\Contracts\Completer;
use Closure;
use RuntimeException;

/**
 * The model behind story generation: the estate's own Gemini client, nothing else.
 *
 * It owns no provider code. Key resolution (the `ai_api_keys` pool, school key first, then GEMINI_API_KEY), the model
 * (`config('ai.provider.gemini.model')`), HTTP retries on 5xx and the daily-quota fallback to sibling models all stay in
 * `App\Domain\AI\Support\GeminiClient`. This class only turns "one completion" into a client call and decides what to do
 * with the answers the client cannot settle for itself:
 *
 *  - an empty or blocked answer (the client returns null) is asked for again, a couple of times;
 *  - a per-minute rate limit waits as long as the provider asked (capped) and tries again;
 *  - a spent quota, a bad key or a retired model is never retried: the error says what to change.
 *
 * It does not look inside the text. A reply that is not valid JSON, or breaks a story rule, is the planner's and
 * writer's business: they send the exact errors back (their repair rounds) and nothing bypasses the validators.
 */
class GeminiStoryCompleter implements Completer
{
    /** Extra output budget for the model's own reasoning, which Gemini counts against the same limit. */
    private const REASONING_HEADROOM = 8000;

    private const OUTPUT_CEILING = 65000;

    private const EMPTY_ATTEMPTS = 3;

    private const RATE_LIMIT_ATTEMPTS = 4;

    private const LONGEST_WAIT = 65;

    private const BUSY_ATTEMPTS = 5;

    private const BUSY_WAIT = 20;

    /** @var Closure(int):void */
    private Closure $sleep;

    public int $calls = 0;

    public function __construct(private readonly GeminiClient $client, ?Closure $sleep = null)
    {
        $this->sleep = $sleep ?? static function (int $seconds): void {
            sleep($seconds);
        };
    }

    /** Whether the platform can reach Gemini at all (a key resolves). Never reads the key into a response. */
    public static function available(?int $tenant = null): bool
    {
        try {
            return app(GeminiClient::class)->forInstitute($tenant)->isConfigured();
        } catch (\Throwable) {
            return false;
        }
    }

    /** The model the client will ask, for reports. */
    public function model(): string
    {
        return $this->client->defaultModel();
    }

    public function complete(string $system, string $prompt, int $maxTokens = 16000): string
    {
        $messages = [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]];
        $budget = min(self::OUTPUT_CEILING, $maxTokens + self::REASONING_HEADROOM);
        $timeout = max((int) config('ai.provider.gemini.document_timeout', 90), 300);
        $empty = 0;
        $limited = 0;
        $busy = 0;

        while (true) {
            $this->calls++;
            try {
                $text = $this->client->chat($messages, null, $budget, 0.6, true, $timeout);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // A timeout or dropped connection: the long answers of a story can outlast one request, so ask again.
                if (++$busy < self::BUSY_ATTEMPTS) {
                    ($this->sleep)(self::BUSY_WAIT);

                    continue;
                }
                throw new RuntimeException('Gemini did not answer in time: ' . $e->getMessage(), 0, $e);
            } catch (AiProviderException $e) {
                if ($e->status === 429 && $this->clearsByItself($e) && ++$limited < self::RATE_LIMIT_ATTEMPTS) {
                    ($this->sleep)(max(1, min($e->retryAfter ?? 60, self::LONGEST_WAIT)));

                    continue;
                }
                // The client already retried a busy model a few times within seconds; a spike can last longer, so wait and ask again.
                if (in_array($e->status, [500, 502, 503, 504, 529], true) && ++$busy < self::BUSY_ATTEMPTS) {
                    ($this->sleep)(self::BUSY_WAIT * $busy);

                    continue;
                }
                throw new RuntimeException('Gemini could not answer: ' . $e->getMessage(), 0, $e);
            }

            if (is_string($text) && trim($text) !== '') {
                return $text;
            }
            if (++$empty >= self::EMPTY_ATTEMPTS) {
                throw new RuntimeException('Gemini returned no usable text (empty, blocked or cut off before any output) after ' . self::EMPTY_ATTEMPTS . ' attempts.');
            }
        }
    }

    /**
     * A 429 clears on its own unless Google names a per-DAY quota (the client has already tried sibling models for that one). Token-per-minute
     * quotas carry other ids, and a story's prompts are large enough to hit them, so "not per-minute" is not the test.
     */
    private function clearsByItself(AiProviderException $e): bool
    {
        return $e->quotaMetric === null || !str_contains(strtolower($e->quotaMetric), 'perday');
    }
}
