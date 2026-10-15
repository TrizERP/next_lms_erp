<?php

namespace App\Services\lms\Prayogshala;

use App\Domain\AI\Support\AiProviderException;
use Throwable;

/**
 * What a failed AI call means, as a safe code plus a message a teacher can act on.
 *
 * The generator stores `code` and `message` on the activity row and the UI shows them. Neither
 * ever contains a key, a request body or the provider's raw payload: the message is chosen from
 * a fixed set by the code. The code is what the retry policy keys on:
 *
 *   rate_limited         too many requests per minute: retryable, honour retryAfter
 *   provider_unavailable the provider is overloaded / erroring (5xx): retryable
 *   timeout              the call timed out: retryable
 *   quota_exhausted      daily quota or billing used up: NOT retryable, needs a human
 *   invalid_key          the key is rejected or not permitted: NOT retryable
 *   no_provider          no usable key is configured at all: NOT retryable
 *   model_not_found      the configured model does not exist: NOT retryable
 *   bad_request          the provider called the request malformed: NOT retryable
 *   invalid_output       the model answered, but not with a valid activity: handled by the repair round
 *   unknown              anything else: NOT retried
 */
final class AiFailure
{
    public const RATE_LIMITED = 'rate_limited';
    public const PROVIDER_UNAVAILABLE = 'provider_unavailable';
    public const TIMEOUT = 'timeout';
    public const QUOTA_EXHAUSTED = 'quota_exhausted';
    public const INVALID_KEY = 'invalid_key';
    public const NO_PROVIDER = 'no_provider';
    public const MODEL_NOT_FOUND = 'model_not_found';
    public const BAD_REQUEST = 'bad_request';
    public const INVALID_OUTPUT = 'invalid_output';
    public const UNKNOWN = 'unknown';

    private const RETRYABLE = [self::RATE_LIMITED, self::PROVIDER_UNAVAILABLE, self::TIMEOUT];

    /** Failures that will repeat for every topic until someone changes the configuration. */
    private const BLOCKING = [self::QUOTA_EXHAUSTED, self::INVALID_KEY, self::NO_PROVIDER, self::MODEL_NOT_FOUND];

    private const MESSAGES = [
        self::RATE_LIMITED => 'The AI provider is limiting requests right now. It was retried with a pause; try again in a minute.',
        self::PROVIDER_UNAVAILABLE => 'The AI provider is busy or unavailable. It was retried; try again shortly.',
        self::TIMEOUT => 'The AI provider took too long to answer. Try again.',
        self::QUOTA_EXHAUSTED => 'The AI provider\'s quota or billing limit for this key is used up, so retrying will not help. Raise the quota or billing on the provider account, or configure another key in AI & Intelligence → AI Providers, then use Try again.',
        self::INVALID_KEY => 'The AI provider rejected the configured key. Fix it in AI & Intelligence → AI Providers, then use Try again.',
        self::NO_PROVIDER => 'No AI provider key is configured. Add one in AI & Intelligence → AI Providers, then use Try again.',
        self::MODEL_NOT_FOUND => 'The configured AI model was not found. Check the model name in AI & Intelligence → AI Providers.',
        self::BAD_REQUEST => 'The AI provider rejected the request as malformed. This is a platform fault, not a quota issue; contact support.',
        self::INVALID_OUTPUT => 'The AI answered, but its activity did not pass validation. Use Try again.',
        self::UNKNOWN => 'Generation failed for an unexpected reason. Use Try again; if it repeats, contact support.',
    ];

    public function __construct(
        public readonly string $code,
        public readonly ?int $retryAfter = null,
    ) {
    }

    public static function make(string $code, ?int $retryAfter = null): self
    {
        return new self($code, $retryAfter);
    }

    public function isRetryable(): bool
    {
        return in_array($this->code, self::RETRYABLE, true);
    }

    /** True when every other topic would fail the same way, so a batch should stop. */
    public function isBlocking(): bool
    {
        return in_array($this->code, self::BLOCKING, true);
    }

    /** The message to store and show. Fixed text per code; nothing from the provider. */
    public function message(): string
    {
        return self::MESSAGES[$this->code] ?? self::MESSAGES[self::UNKNOWN];
    }

    /** Classify an exception thrown by an AI client. */
    public static function fromThrowable(Throwable $e): self
    {
        if ($e instanceof AiProviderException) {
            return self::fromStatus($e->status, $e->retryAfter, $e->quotaMetric, $e->providerStatus);
        }

        return self::fromText($e->getMessage());
    }

    /**
     * Classify the `{ok:false,error:string}` envelope the Claude client returns, or any error text.
     * Matching is on the phrases those clients are known to use.
     */
    public static function fromText(string $text): self
    {
        $t = strtolower($text);

        return match (true) {
            str_contains($t, 'is not configured'), str_contains($t, 'no usable ai api key') => self::make(self::NO_PROVIDER),
            str_contains($t, 'rejected the configured credential'), str_contains($t, 'key was rejected'),
            str_contains($t, 'is not permitted') => self::make(self::INVALID_KEY),
            str_contains($t, 'not recognise the configured model'), str_contains($t, 'was not found. check anthropic_model') => self::make(self::MODEL_NOT_FOUND),
            str_contains($t, 'quota or billing limit'), str_contains($t, 'exceeded your current quota'),
            str_contains($t, 'credit balance is too low') => self::make(self::QUOTA_EXHAUSTED),
            str_contains($t, 'rate limit'), str_contains($t, 'rate limiting'), str_contains($t, 'too many requests') => self::make(self::RATE_LIMITED),
            str_contains($t, 'timed out'), str_contains($t, 'timeout'), str_contains($t, 'curl error 28') => self::make(self::TIMEOUT),
            str_contains($t, 'temporarily unavailable'), str_contains($t, 'busy at the provider'),
            str_contains($t, 'high demand'), str_contains($t, 'overloaded') => self::make(self::PROVIDER_UNAVAILABLE),
            str_contains($t, 'malformed') => self::make(self::BAD_REQUEST),
            default => self::make(self::UNKNOWN),
        };
    }

    private static function fromStatus(int $status, ?int $retryAfter, ?string $quotaMetric, ?string $providerStatus): self
    {
        if ($status === 429) {
            // A per-minute quota clears by itself. Anything else that names a quota (per day,
            // billing, a zero free-tier allowance) is spent until a human or the clock acts.
            $perMinute = $quotaMetric !== null && str_contains($quotaMetric, 'PerMinute');
            $spent = ($quotaMetric !== null && ! $perMinute)
                || ($providerStatus === 'RESOURCE_EXHAUSTED' && $quotaMetric === null && $retryAfter !== null && $retryAfter > 300);

            return $spent ? self::make(self::QUOTA_EXHAUSTED, $retryAfter) : self::make(self::RATE_LIMITED, $retryAfter);
        }

        return match (true) {
            in_array($status, [401, 403], true) => self::make(self::INVALID_KEY),
            $status === 404 => self::make(self::MODEL_NOT_FOUND),
            $status === 400 => self::make(self::BAD_REQUEST),
            $status === 408 || $status === 504 => self::make(self::TIMEOUT),
            $status >= 500 => self::make(self::PROVIDER_UNAVAILABLE, $retryAfter),
            default => self::make(self::UNKNOWN),
        };
    }
}
