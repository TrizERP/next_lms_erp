<?php

namespace App\Domain\AI\Conversation;

/**
 * What the user was understood to be asking for.
 *
 * `slots` is what the sentence itself supplied; `resolvedFrom` records anything that
 * had to be carried over from the previous turn ("why is *she* at risk"), because a
 * user should be able to see when the system filled a gap on their behalf rather than
 * from what they typed.
 */
final class Intent
{
    public const UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly float $confidence,
        public readonly array $slots = [],
        /** Which matcher fired — shown in the trace so classification is not a black box. */
        public readonly array $matched = [],
        /** Slots inherited from conversation memory rather than from this sentence. */
        public readonly array $resolvedFrom = [],
        /** For unknown intents: what the user could ask instead. */
        public readonly array $suggestions = [],
    ) {
    }

    /**
     * The intent for a question nothing matched.
     *
     * Exists as a named constructor so the pipeline has something well-formed to record
     * when a turn halts before the classifier produced anything — a turn with no intent
     * at all would store a null key and become invisible to every later query.
     *
     * @param  array<int, string>  $suggestions
     */
    public static function unknown(array $suggestions = []): self
    {
        return new self(
            key: self::UNKNOWN,
            label: 'Not understood',
            confidence: 0.0,
            suggestions: $suggestions,
        );
    }

    public function isUnknown(): bool
    {
        return $this->key === self::UNKNOWN;
    }

    public function slot(string $name, mixed $default = null): mixed
    {
        return $this->slots[$name] ?? $default;
    }

    public function with(array $slots, array $resolvedFrom = []): self
    {
        return new self(
            $this->key,
            $this->label,
            $this->confidence,
            array_merge($this->slots, $slots),
            $this->matched,
            array_merge($this->resolvedFrom, $resolvedFrom),
            $this->suggestions,
        );
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'confidence' => round($this->confidence, 3),
            'slots' => $this->slots,
            'matched' => $this->matched,
            'resolved_from_conversation' => $this->resolvedFrom,
            'suggestions' => $this->suggestions,
        ];
    }
}
