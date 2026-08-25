<?php

namespace App\Domain\AI\Signals;

use App\Services\Mcp\McpRequestContext;

/**
 * What every signal detector must offer.
 *
 * A detector reads; it never writes. Persisting is SignalStore's job, and keeping
 * that separation means a detector can be run in "preview" mode from the
 * conversational layer without leaving rows behind.
 *
 * The context argument is not optional and not derivable inside the detector: it
 * arrives already scoped by McpContextResolver, so a detector physically cannot
 * widen the requesting user's reach.
 */
interface SignalDetector
{
    /** Stable key, matching `ai_signal_definitions.signal_key`. */
    public function key(): string;

    /** The ontology entity this detector produces signals about. */
    public function subjectEntityKey(): string;

    public function domain(): string;

    /**
     * Detect for a single subject.
     *
     * Returns null when nothing worth recording was found — which is the normal case
     * and must not be treated as an error.
     */
    public function detectFor(int|string $subjectId, McpRequestContext $context): ?DetectedSignal;

    /**
     * Detect across a population, bounded.
     *
     * @param  array<int, int|string>|null  $subjectIds  Null means "the whole in-scope population"
     * @return array<int, DetectedSignal>
     */
    public function detect(McpRequestContext $context, ?array $subjectIds = null, int $limit = 100): array;
}
