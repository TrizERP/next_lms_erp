<?php

namespace App\Domain\AI\Lifecycle;

/**
 * What one stage reports back. The only thing a stage is allowed to return.
 *
 * Every stage produces one of these, which is what makes the twelve uniform: the
 * pipeline never asks a stage what kind of stage it is, and no stage writes into the
 * trace directly. Timing is added by the runner, so a stage cannot forget to report it
 * and cannot lie about it.
 *
 * `halt` is the piece that removes the worst duplication in the old design. A stage
 * that stops the turn — a refused agent, a question nobody understood — says so once,
 * with a reason, and the runner marks every downstream stage `not_reached` carrying
 * that reason. Previously each handler wrote its own `foreach` over a hand-listed array
 * of stage names, and those lists had already drifted apart from each other.
 */
final class StageOutcome
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array{table?:string, ids?:array<int, int|string>}  $records
     * @param  array{api?:string, sql?:string}  $verify
     */
    private function __construct(
        public readonly StageStatus $status,
        public readonly string $summary,
        public readonly array $data = [],
        public readonly array $records = [],
        public readonly array $verify = [],
        public readonly ?string $note = null,
        public readonly ?string $component = null,
        public readonly ?int $durationMs = null,
        public readonly ?string $halt = null,
    ) {
    }

    /**
     * The stage did work.
     *
     * @param  array<string, mixed>  $data
     * @param  array{table?:string, ids?:array<int, int|string>}  $records
     * @param  array{api?:string, sql?:string}  $verify
     */
    public static function ran(
        string $summary,
        array $data = [],
        array $records = [],
        array $verify = []
    ): self {
        return new self(StageStatus::Ran, $summary, $data, $records, $verify);
    }

    /**
     * The stage was reached and had nothing to do.
     *
     * The reason is required, not optional. "Skipped" without a reason is the single
     * most common way a trace stops being evidence and starts being decoration.
     *
     * @param  array<string, mixed>  $data
     */
    public static function skipped(string $why, array $data = []): self
    {
        return new self(StageStatus::Skipped, $why, $data);
    }

    /**
     * The stage refused — governance, a role gate, or a missing decision.
     *
     * @param  array<string, mixed>  $data
     */
    public static function blocked(string $why, array $data = []): self
    {
        return new self(StageStatus::Blocked, $why, $data);
    }

    /**
     * The stage is waiting on something, nearly always a person.
     *
     * This is the status that distinguishes a governed system from a broken one, so it
     * carries records and verification like `ran` does: a reader should be able to open
     * the row that is being waited on.
     *
     * @param  array<string, mixed>  $data
     * @param  array{table?:string, ids?:array<int, int|string>}  $records
     * @param  array{api?:string, sql?:string}  $verify
     */
    public static function pending(
        string $why,
        array $data = [],
        array $records = [],
        array $verify = []
    ): self {
        return new self(StageStatus::Pending, $why, $data, $records, $verify);
    }

    /**
     * The turn did not get this far. Carries the reason as a note rather than a summary,
     * because the frontend renders the two differently on purpose: a summary is what
     * happened, a note is why nothing did.
     */
    public static function notReached(string $why): self
    {
        return new self(StageStatus::NotReached, '', note: $why);
    }

    /**
     * Stop the pipeline here, and tell every downstream stage why.
     *
     * Used for genuine dead ends only — a refused agent run, an unparseable question, a
     * decision the caller is not permitted to make. A stage with nothing to do should
     * return `skipped` and let the turn continue.
     */
    public function halting(string $downstreamReason): self
    {
        return $this->with(halt: $downstreamReason);
    }

    /**
     * Attach the reason a stage holds its status, shown when there is no summary.
     *
     * Accepts null so a caller can write `->withNote($blocked === [] ? null : '...')`
     * without a branch around the whole expression; a null note simply leaves the
     * outcome without one.
     */
    public function withNote(?string $note): self
    {
        if ($note === null) {
            return $this;
        }

        return $this->with(note: $note);
    }

    /** Name the class that actually did the work on this turn. */
    public function withComponent(string $component): self
    {
        return $this->with(component: $component);
    }

    /** Set by the runner. A stage never times itself. */
    public function withDuration(int $durationMs): self
    {
        return $this->with(durationMs: $durationMs);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function withData(array $data): self
    {
        return $this->with(data: [...$this->data, ...$data]);
    }

    public function halts(): bool
    {
        return $this->halt !== null;
    }

    /**
     * The wire shape. Identical to the legacy trace's stage shape, so both pipelines
     * render through one frontend component.
     *
     * @return array<string, mixed>
     */
    public function toArray(StageKey $key): array
    {
        return [
            'key' => $key->value,
            'order' => $key->displayOrder(),
            'layer' => $key->layer(),
            'status' => $this->status->value,
            'summary' => $this->summary,
            'component' => $this->component ?? $key->component(),
            'surface' => $key->surface(),
            'data' => $this->data,
            'records' => $this->records,
            'verify' => $this->verify,
            'duration_ms' => $this->durationMs,
            'note' => $this->note,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>|null  $records
     * @param  array<string, mixed>|null  $verify
     */
    private function with(
        ?array $data = null,
        ?array $records = null,
        ?array $verify = null,
        ?string $note = null,
        ?string $component = null,
        ?int $durationMs = null,
        ?string $halt = null,
    ): self {
        return new self(
            status: $this->status,
            summary: $this->summary,
            data: $data ?? $this->data,
            records: $records ?? $this->records,
            verify: $verify ?? $this->verify,
            note: $note ?? $this->note,
            component: $component ?? $this->component,
            durationMs: $durationMs ?? $this->durationMs,
            halt: $halt ?? $this->halt,
        );
    }
}
