<?php

namespace App\Events\Pal;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by EsoPolicyService right after a concept reaches a D4 mastery verdict
 * or survives a D5 spaced-retrieval check, once the matching eso_decision_log
 * row has been written. Consumed by GeneratePalEvidenceListener (Career
 * Intelligence evidence generation), which reads that log — so the event must
 * never be dispatched before the row exists.
 */
class ConceptOutcomeRecorded
{
    use Dispatchable;
    use SerializesModels;

    public const MASTERED = 'mastered';
    public const RETAINED = 'retained';

    public function __construct(
        public readonly int $studentId,
        public readonly int $conceptId,
        public readonly string $outcome,
    ) {
    }
}
