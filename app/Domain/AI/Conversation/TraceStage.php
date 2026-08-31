<?php

namespace App\Domain\AI\Conversation;

/**
 * One stage of the architecture, as it actually executed for one question.
 *
 * The whole point of this object is that a stage can honestly report "I did not run,
 * and here is why". A pipeline view that only shows the stages that fired teaches
 * nobody how the pipeline works; a view that shows all fifteen, each with its status,
 * is a map you can read.
 */
final class TraceStage
{
    public const RAN = 'ran';
    public const SKIPPED = 'skipped';
    public const BLOCKED = 'blocked';
    public const PENDING = 'pending';
    public const NOT_REACHED = 'not_reached';

    public function __construct(
        public readonly string $key,
        public readonly int $order,
        public readonly string $layer,
        /** The class or table that is genuinely responsible for this stage. */
        public readonly string $component,
        /** Where in the product a user sees the result of this stage. */
        public readonly string $surface,
        public string $status = self::NOT_REACHED,
        /** One plain sentence: what this stage did for this question. */
        public string $summary = '',
        /** The stage's own payload — ids, numbers, rows. */
        public array $data = [],
        /** {table, ids} — the rows this stage wrote or read, so they can be opened. */
        public array $records = [],
        /** {api, sql} — how to confirm by hand that this stage really ran. */
        public array $verify = [],
        public ?int $durationMs = null,
        public ?string $note = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'order' => $this->order,
            'layer' => $this->layer,
            'status' => $this->status,
            'summary' => $this->summary,
            'component' => $this->component,
            'surface' => $this->surface,
            'data' => $this->data,
            'records' => $this->records,
            'verify' => $this->verify,
            'duration_ms' => $this->durationMs,
            'note' => $this->note,
        ];
    }
}
