<?php

namespace App\Services\StudyDeck\Story;

use App\Domain\AI\Support\AiProviderException;
use App\Domain\AI\Support\GeminiClient;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\Contracts\DeadlineAware;
use App\Services\StudyDeck\Contracts\ParallelCompleter;
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
class GeminiStoryCompleter implements Completer, ParallelCompleter, DeadlineAware
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

    private ?float $deadline = null;

    /** Attempts (the first included) at a model that is overloaded or failing: five without a deadline, four while the run has one. */
    private function busyAttempts(): int
    {
        return $this->deadline === null ? self::BUSY_ATTEMPTS : self::OVERLOAD_ATTEMPTS;
    }

    /** After this many overloaded answers in a row, a fallback model the platform already has configured is tried instead. */
    private const FALLBACK_AFTER = 2;

    /** Attempts, the first included, at an overloaded model while the run has a deadline. */
    private const OVERLOAD_ATTEMPTS = 4;

    /** Longest single pause the provider's own Retry-After is allowed to impose. */
    private const LONGEST_PAUSE = 30;

    /** The model asked instead of the configured one after repeated overload; null while the configured model is in use. */
    private ?string $fallbackInUse = null;

    /** @var Closure(float,float):float picks a number between the two (tests replace it, so the jitter is exact) */
    private ?Closure $random = null;

    /** For tests: the source of the jitter. */
    public function useRandom(Closure $random): static
    {
        $this->random = $random;

        return $this;
    }

    /**
     * What an answer (or a failure to get one) was, from the evidence the provider gave, never from guessing:
     * 'overloaded' (503 / UNAVAILABLE / 529: the model is busy at the provider and says so), 'server_error' (other 5xx), 'rate_limit', 'quota'
     * or 'unclassified' (a 429, see classify429()), 'timeout' (no answer in time) or 'other'.
     */
    public static function classifyFailure(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return 'timeout';
        }
        if (!$e instanceof AiProviderException) {
            return 'other';
        }
        if ($e->status === 429) {
            return self::classify429($e);
        }
        if (in_array($e->status, [503, 529], true) || strtoupper((string) $e->providerStatus) === 'UNAVAILABLE') {
            return 'overloaded';
        }

        return in_array($e->status, [500, 502, 504], true) ? 'server_error' : 'other';
    }

    /**
     * Seconds to wait before asking again. The provider's own Retry-After wins (capped); otherwise 2, 4, 8, 16 s with up to a quarter more or
     * less as jitter, so parallel callers do not come back at the same instant. Without a deadline the long-standing 20 s steps are kept.
     */
    public function backoffSeconds(int $attempt, ?int $retryAfter = null): int
    {
        if ($retryAfter !== null && $retryAfter > 0) {
            return min($retryAfter, self::LONGEST_PAUSE);
        }
        if ($this->deadline === null) {
            return self::BUSY_WAIT * $attempt;
        }
        $base = min(16, 2 ** max(0, $attempt));
        $factor = $this->random !== null ? ($this->random)(0.75, 1.25) : mt_rand(750, 1250) / 1000;

        return max(1, (int) round($base * $factor));
    }

    /**
     * The teacher-facing words for a model that stayed overloaded. It says what happened (the provider, not this platform), what was stored (nothing)
     * and what to do; it names the model but never a key, a header or a prompt.
     */
    public function overloadMessage(string $model, bool $ranOutOfTime): string
    {
        return sprintf(
            'The AI model (%s) is overloaded at the provider (HTTP 503, high demand) and %s. This is temporary and on the provider\'s side, not a problem with your chapter or settings. Nothing was stored. Try again in a few minutes.',
            $model,
            $ranOutOfTime ? 'did not recover within the time allowed for this generation' : 'did not recover after several attempts'
        );
    }

    /** The model being asked: the configured one unless a configured fallback has taken over after repeated overload. */
    private function modelInUse(): string
    {
        return $this->fallbackInUse ?? $this->client->defaultModel();
    }

    /**
     * After repeated overload, switches to the FIRST fallback model the platform already has configured (never a new one, never another key).
     * The switch is logged with both model names, so it is never silent. True when a fallback is now in use.
     */
    private function considerFallback(int $overloaded): bool
    {
        if ($this->fallbackInUse !== null) {
            return true;
        }
        if ($overloaded < self::FALLBACK_AFTER || !(bool) config('ai.provider.gemini.overload_fallback', true)) {
            return false;
        }
        $from = $this->client->defaultModel();
        $to = $this->client->fallbackModels()[0] ?? null;
        if ($to === null || $to === $from) {
            return false;
        }
        $this->fallbackInUse = $to;
        try {
            \Illuminate\Support\Facades\Log::warning('[gemini] model overloaded, using the configured fallback model', ['from' => $from, 'to' => $to, 'after_overloaded_answers' => $overloaded]);
        } catch (\Throwable) {
        }

        return true;
    }

    /**
     * Waits `$seconds` before the next attempt, but never longer than the run's deadline allows (a 3 s margin is kept for the rest of the run).
     * A wait that is clipped is made shorter; when there is no room left at all, the run stops with the reason it was waiting out.
     *
     * @throws RuntimeException when no time is left to wait
     */
    private function pause(int $seconds, string $kind, ?string $overloadModel = null): int
    {
        $left = $this->left();
        if ($left !== null) {
            $room = (int) floor($left - 3);
            if ($room < 1) {
                throw new RuntimeException($overloadModel !== null
                    ? $this->overloadMessage($overloadModel, true)
                    : 'The time allowed for this generation ran out while waiting to ask the model again.' . ($this->waitingFor ?? ''));
            }
            $seconds = min($seconds, $room);
        }
        ($this->sleep)($seconds);

        return $seconds;
    }

    /**
     * One line per failed attempt, so a slow generation can be traced to the provider answer that caused it: what kind of answer, its status,
     * the model, which attempt, how long the wait is, and how much of the run's time is left. Never a key, a header or a prompt.
     */
    private function note(string $kind, int $attempt, ?string $quota, ?int $status = null, ?string $providerStatus = null, ?int $retryAfter = null, ?int $delay = null): void
    {
        try {
            \Illuminate\Support\Facades\Log::info('[gemini] retrying', [
                'why' => $kind . ($status !== null ? ' ' . $status : ''),
                'kind' => $kind,
                'status' => $status,
                'provider_status' => $providerStatus,
                'model' => $this->modelInUse(),
                'attempt' => $attempt,
                'delay' => $delay,
                'retry_after' => $retryAfter,
                'quota' => $quota,
                'seconds_left' => $this->deadline === null ? null : (int) round($this->deadline - microtime(true)),
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * What a 429 is, from the evidence the provider gave: 'rate_limit' (a per-minute quota id), 'quota' (a per-day one) or
     * 'unclassified' (no quota id, or one that names neither). It never guesses from the status alone.
     */
    public static function classify429(AiProviderException $e): string
    {
        $metric = strtolower((string) $e->quotaMetric);
        if (str_contains($metric, 'perday')) {
            return 'quota';
        }
        if (str_contains($metric, 'perminute')) {
            return 'rate_limit';
        }

        return 'unclassified';
    }

    /** One line per 429 with what the provider said. Never holds a key, a header or a prompt. */
    private function note429(AiProviderException $e, string $kind): void
    {
        try {
            \Illuminate\Support\Facades\Log::warning('[gemini] 429', ['kind' => $kind, 'quota' => $e->quotaMetric, 'provider_status' => $e->providerStatus, 'retry_after' => $e->retryAfter, 'seconds_left' => $this->deadline === null ? null : (int) round($this->deadline - microtime(true))]);
        } catch (\Throwable) {
        }
    }

    /** What the run was waiting out, for the message when the deadline ends that wait. */
    private ?string $waitingFor = null;

    private static function waitReason(string $kind, int $seconds): string
    {
        return match ($kind) {
            'rate_limit' => " The model's per-minute rate limit was reached and its provider asked for a wait of {$seconds} s.",
            'quota' => ' The model quota is used up.',
            default => " The provider asked for a wait of {$seconds} s without saying which limit was reached (unclassified).",
        };
    }

    public function setDeadline(?float $unixTime): void
    {
        $this->deadline = $unixTime;
    }

    public function deadline(): ?float
    {
        return $this->deadline;
    }

    /** Seconds left before the deadline, or null when there is none. */
    private function left(): ?float
    {
        return $this->deadline === null ? null : $this->deadline - microtime(true);
    }

    /** The request timeout: the usual long one, never longer than the time left. */
    private function timeoutWithinDeadline(int $timeout): int
    {
        $left = $this->left();
        if ($left === null) {
            return $timeout;
        }
        if ($left < 3) {
            throw new RuntimeException('The time allowed for this generation ran out before the model could answer.');
        }

        return (int) max(5, min($timeout, ceil($left)));
    }

    /** A wait between attempts; one that would end after the deadline is not made, the run says the time ran out instead. */
    private function wait(int $seconds): void
    {
        $left = $this->left();
        if ($left !== null && $left <= $seconds + 3) {
            throw new RuntimeException('The time allowed for this generation ran out while waiting to ask the model again.' . ($this->waitingFor ?? ''));
        }
        ($this->sleep)($seconds);
    }

    /**
     * Several prompts at once. Each is asked once in parallel; one that did not answer is asked again alone, with the retry, rate-limit
     * and deadline rules of complete(). Replies keep their keys.
     *
     * @param array<int,string> $prompts
     * @return array<int,string>
     */
    public function completeMany(string $system, array $prompts, int $maxTokens = 16000): array
    {
        if (count($prompts) < 2) {
            return array_map(fn ($p) => $this->complete($system, $p, $maxTokens), $prompts);
        }
        $budget = min(self::OUTPUT_CEILING, $maxTokens + self::REASONING_HEADROOM);
        $timeout = $this->timeoutWithinDeadline(max((int) config('ai.provider.gemini.document_timeout', 90), 300));
        $requests = array_map(fn ($p) => ['messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $p]], 'max_tokens' => $budget, 'temperature' => 0.6, 'expect_json' => true], $prompts);
        $this->calls += count($prompts);

        // Slots refused because the model is overloaded are asked again TOGETHER, after one shared pause (not one after another, which multiplies the
        // wait by the number of slots). Only under a deadline: without one the earlier behaviour (one round, then each failed slot alone) stands.
        $out = [];
        $pending = array_keys($requests);
        $errors = [];
        $overloaded = 0;
        $rounds = $this->deadline === null ? 1 : self::OVERLOAD_ATTEMPTS;
        for ($round = 1; $pending !== [] && $round <= $rounds; $round++) {
            if ($round > 1) {
                $retryAfter = null;
                foreach ($errors as $e) {
                    $retryAfter = max((int) $retryAfter, $e instanceof AiProviderException ? (int) $e->retryAfter : 0) ?: null;
                }
                $delay = $this->backoffSeconds($round - 1, $retryAfter);
                $this->note('overloaded', $round - 1, null, 503, 'UNAVAILABLE', $retryAfter, $delay);
                $this->pause($delay, 'overloaded', $this->modelInUse());
                $timeout = $this->timeoutWithinDeadline(max((int) config('ai.provider.gemini.document_timeout', 90), 300));
            }
            $answers = $this->client->chatMany(array_intersect_key($requests, array_flip($pending)), $timeout, $this->fallbackInUse);
            if ($round > 1) {
                $this->calls += count($pending);
            }
            $errors = [];
            foreach ($answers as $i => $answer) {
                if (is_string($answer)) {
                    $out[$i] = $answer;
                    continue;
                }
                $errors[$i] = $answer;
                if ($answer instanceof AiProviderException && $answer->status === 429) {
                    $this->note429($answer, self::classify429($answer));
                }
            }
            $pending = array_keys($errors);
            $kinds = array_map(fn ($e) => self::classifyFailure($e), $errors);
            $overloaded = in_array('overloaded', $kinds, true) ? $overloaded + 1 : 0;
            // Only a busy or briefly unavailable provider is worth another parallel round; anything else is settled below.
            $retryable = $kinds !== [] && array_diff($kinds, ['overloaded', 'server_error', 'timeout']) === [];
            if (!$retryable) {
                break;
            }
            $this->considerFallback($overloaded);
        }

        foreach ($pending as $i) {
            $e = $errors[$i] ?? null;
            if ($this->deadline !== null && $e !== null && self::classifyFailure($e) === 'overloaded') {
                throw new RuntimeException($this->overloadMessage($this->modelInUse(), false), 0, $e);
            }
            $out[$i] = $this->complete($system, $prompts[$i], $maxTokens);
        }
        ksort($out);

        return $out;
    }

    public function complete(string $system, string $prompt, int $maxTokens = 16000): string
    {
        $messages = [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]];
        $budget = min(self::OUTPUT_CEILING, $maxTokens + self::REASONING_HEADROOM);
        $baseTimeout = max((int) config('ai.provider.gemini.document_timeout', 90), 300);
        $empty = 0;
        $limited = 0;
        $busy = 0;
        $overloaded = 0;

        while (true) {
            $this->waitingFor = null;
            $timeout = $this->timeoutWithinDeadline($baseTimeout);
            $this->calls++;
            try {
                $text = $this->client->chat($messages, $this->fallbackInUse, $budget, 0.6, true, $timeout, $this->deadline === null ? null : 1);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // A timeout or dropped connection: the long answers of a story can outlast one request, so ask again.
                if (++$busy < $this->busyAttempts()) {
                    $delay = $this->deadline === null ? self::BUSY_WAIT : $this->backoffSeconds($busy);
                    $this->note('timeout', $busy, null, null, null, null, $delay);
                    $this->pause($delay, 'timeout');

                    continue;
                }
                throw new RuntimeException('Gemini did not answer in time: ' . $e->getMessage(), 0, $e);
            } catch (AiProviderException $e) {
                if ($e->status === 429) {
                    $this->note429($e, $kind = self::classify429($e));
                }
                if ($e->status === 429 && $this->clearsByItself($e) && ++$limited < self::RATE_LIMIT_ATTEMPTS) {
                    $seconds = max(1, min($e->retryAfter ?? 60, self::LONGEST_WAIT));
                    $this->waitingFor = self::waitReason($kind, $seconds);
                    $this->wait($seconds);

                    continue;
                }
                // The client already retried a busy model a few times within seconds; a spike can last longer, so wait and ask again.
                $kind = self::classifyFailure($e);
                if (in_array($kind, ['overloaded', 'server_error'], true)) {
                    $overloaded = $kind === 'overloaded' ? ++$overloaded : 0;
                    if (++$busy < $this->busyAttempts()) {
                        // Retry-After wins when the provider sent one; otherwise a bounded, jittered backoff, never longer than the deadline allows.
                        $delay = $this->backoffSeconds($busy, $e->retryAfter);
                        $this->note($kind, $busy, $e->quotaMetric, $e->status, $e->providerStatus, $e->retryAfter, $delay);
                        $this->considerFallback($overloaded);
                        $this->pause($delay, $kind, $kind === 'overloaded' ? $this->modelInUse() : null);

                        continue;
                    }
                    if ($kind === 'overloaded') {
                        throw new RuntimeException($this->overloadMessage($this->modelInUse(), false), 0, $e);
                    }
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
