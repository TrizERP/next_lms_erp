<?php

namespace App\Services\Eso;

use App\Models\Eso\LearnerNodeState;
use App\Models\Eso\ResponseLog;
use RuntimeException;

/**
 * Capability Confidence — "does this learner actually have it?"
 *
 * The one score in the Evidence & Confidence framework that is about a person
 * rather than about content, and the one the worked ESO example (#25) bands on.
 *
 * Deliberately NOT the BKT mastery estimate. A learner can reach a high
 * estimate by answering one question repeatedly with hints; that is a score,
 * not a capability. So mastery is the largest input and never the only one —
 * independence and variety are weighted alongside it, exactly as the spec
 * requires ("evidence-driven: attempt variety, hint dependence, independent vs
 * assisted performance, NOT just raw score").
 *
 * Returns null below the evidence floor. A confidence built from one attempt
 * is a guess wearing a number, and ADR-001 §5's rule applies here as it does
 * everywhere else in ESO: absence of evidence is a reason to gather more, not
 * a low score.
 *
 * Read-only.
 */
class CapabilityConfidenceService
{
    public function __construct(
        private readonly EsoPolicyService $policy
    ) {
    }

    /**
     * This learner's capability confidence for one concept, 0.0-1.0, with the
     * band it falls in and the evidence behind it.
     *
     * @return array<string, mixed>|null null when there is not enough evidence
     */
    public function forConcept(int $studentId, int $conceptId, int $subInstituteId): ?array
    {
        $cfg = config('pal_content.capability_confidence');
        $this->assertWeights($cfg['weights']);

        $nodes = $this->policy->esoNodesForConcept($conceptId, $subInstituteId);
        if ($nodes->isEmpty()) {
            return null;
        }

        $responses = ResponseLog::forStudent($studentId)
            ->forConcept($conceptId)
            ->get(['question_id', 'correct', 'hint_used', 'mode']);

        if ($responses->count() < (int) $cfg['min_attempts']) {
            return null;
        }

        $mastery = $this->policy->conceptMasteryEstimate($studentId, $conceptId, $subInstituteId);

        if ($mastery === null) {
            // Responses exist but no node carries enough attempts to evidence a
            // mastery estimate. Reported as "not yet" rather than as zero.
            return null;
        }

        $independence = $this->independence($responses);
        $variety = $this->variety($responses, (int) $cfg['variety_target']);

        $score = round(
            $mastery * $cfg['weights']['mastery']
            + $independence * $cfg['weights']['independence']
            + $variety * $cfg['weights']['variety'],
            3
        );

        $band = $this->bandFor($score, $cfg['bands']);

        return [
            'capability_confidence' => $score,
            'band' => $band['key'],
            'action' => $band['action'],
            // The inputs travel with the score. A number a teacher cannot
            // interrogate is one they have to take on trust, and this one
            // decides whether a student is sent back through a concept.
            'evidence' => [
                'mastery_estimate' => $mastery,
                'independence' => $independence,
                'variety' => $variety,
                'attempts' => $responses->count(),
                'distinct_questions' => $responses->pluck('question_id')->filter()->unique()->count(),
            ],
        ];
    }

    /**
     * Share of attempts made without a hint AND in independent mode.
     *
     * Both conditions, not either: a hint-free answer inside a guided session
     * was still scaffolded, and counting it as independent would overstate what
     * the learner did alone.
     *
     * @param  \Illuminate\Support\Collection<int, ResponseLog>  $responses
     */
    private function independence($responses): float
    {
        $total = $responses->count();
        if ($total === 0) {
            return 0.0;
        }

        $unassisted = $responses->filter(
            fn ($r) => ! $r->hint_used && $r->mode !== LearnerNodeState::MODE_GUIDED
        )->count();

        return round($unassisted / $total, 3);
    }

    /**
     * How broadly the evidence is spread, against the configured target.
     *
     * Answering one item ten times is one piece of evidence repeated, not ten.
     * Capped at 1.0 — beyond the target, more breadth stops adding confidence.
     *
     * @param  \Illuminate\Support\Collection<int, ResponseLog>  $responses
     */
    private function variety($responses, int $target): float
    {
        if ($target <= 0) {
            return 1.0;
        }

        $distinct = $responses->pluck('question_id')->filter()->unique()->count();

        return round(min(1.0, $distinct / $target), 3);
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     * @return array<string, mixed>
     */
    private function bandFor(float $score, array $bands): array
    {
        foreach ($bands as $band) {
            if ($score >= (float) $band['min']) {
                return $band;
            }
        }

        // Bands are configured high-to-low with a 0.0 floor, so this is only
        // reachable if that floor is removed. Failing loudly beats banding a
        // learner into nothing.
        throw new RuntimeException(
            'No capability confidence band matched score ' . $score . '. The band list must include a 0.0 floor.'
        );
    }

    /** @param array<string, float> $weights */
    private function assertWeights(array $weights): void
    {
        $sum = array_sum($weights);

        // Asserted rather than renormalised: a typo that silently rescales
        // every band would move the relearn boundary without anyone noticing.
        if (abs($sum - 1.0) > 0.001) {
            throw new RuntimeException(
                'pal_content.capability_confidence.weights must sum to 1.0, got ' . $sum . '.'
            );
        }
    }
}
