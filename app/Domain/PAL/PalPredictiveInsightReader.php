<?php

namespace App\Domain\Pal;

use App\Domain\PAL\CoherenceRisk\CompoundingRootBlockerDetector;
use App\Services\PAL\Intelligence\PredictiveInterventionEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only reshaping of PAL's predictive signal into the Recommendation
 * panel's "Predictive Intelligence" section, modeled on
 * FeesPredictiveInsightReader's own honesty-ladder card shape.
 *
 * Two sources, deliberately not blended into one number:
 *
 *   1. ai_signals already persisted by the governed pal_coherence_risk
 *      agent (signal_key = CompoundingRootBlockerDetector::KEY) — real,
 *      already-flowing governed data.
 *   2. PredictiveInterventionEngine::predictDisengagement/predictFailure/
 *      predictBurnout — real code, but verified live (2026-11-25):
 *      pal_learning_sessions has 1 row, pal_session_events has 18 rows
 *      platform-wide. Each method's own `has_data` flag is read and
 *      trusted as-is: when it is false the card reports exactly why
 *      (the row-count reason), never a fabricated "low risk". This is the
 *      honest-emptiness case the master prompt explicitly requires — wired
 *      in deliberately, not suppressed.
 */
class PalPredictiveInsightReader
{
    public function tenantCardsFor(int $tenant): array
    {
        return $this->signalCardsFor($tenant, null);
    }

    public function cardsFor(int $tenant, ?int $learnerId): array
    {
        $cards = $this->signalCardsFor($tenant, $learnerId);

        if ($learnerId !== null) {
            $cards = [...$cards, ...$this->interventionCardsFor($learnerId)];
        }

        return $cards;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function signalCardsFor(int $tenant, ?int $learnerId): array
    {
        if (! Schema::hasTable('ai_signals')) {
            return [$this->unavailable('pal.predictive.signals', 'PAL compounding-gap signal', 'ai_signals table is not present on this installation.')];
        }

        $query = DB::table('ai_signals')
            ->where('signal_key', CompoundingRootBlockerDetector::KEY)
            ->where('subject_entity_key', 'student')
            ->where('sub_institute_id', $tenant)
            ->whereIn('status', ['open', 'cased'])
            ->orderByDesc('detected_at');

        if ($learnerId !== null) {
            $query->where('subject_id', $learnerId);
        }

        $signals = $query->limit(100)->get();

        if ($signals->isEmpty()) {
            return [$this->unavailable(
                'pal.predictive.signals',
                'PAL compounding-gap signal',
                $learnerId !== null
                    ? sprintf('No open compounding-gap signal exists for learner #%d.', $learnerId)
                    : 'No open compounding-gap signal exists for this school right now.'
            )];
        }

        return $signals->map(fn ($signal) => $this->signalCard($signal, $tenant))->all();
    }

    private function signalCard(object $signal, int $tenant): array
    {
        $components = $signal->components ? json_decode((string) $signal->components, true) : [];

        $evidenceRecords = DB::table('ai_evidence')
            ->where('subject_entity_key', 'student')
            ->where('subject_id', $signal->subject_id)
            ->whereIn('kind', ['compounding_root_blockers', 'root_blocker'])
            ->where('sub_institute_id', $tenant)
            ->orderByDesc('id')
            ->limit(9)
            ->get(['id', 'summary', 'source_service', 'verified', 'is_generated', 'observed_at'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'summary' => $row->summary,
                'source' => $row->source_service,
                'verified' => (bool) $row->verified,
                'is_generated' => (bool) $row->is_generated,
                'observed_at' => $row->observed_at,
            ])
            ->all();

        $recommendation = DB::table('ai_recommendations')
            ->where('subject_entity_key', 'student')
            ->where('subject_id', $signal->subject_id)
            ->where('domain', 'pal')
            ->where('sub_institute_id', $tenant)
            ->orderByDesc('id')
            ->first(['id', 'status', 'governance_passed', 'title', 'risk_level', 'confidence']);

        return [
            'id' => 'pal.predictive.student.'.$signal->subject_id,
            'title' => sprintf('Compounding root-blocker risk — %s', $signal->subject_label ?? ('Student #'.$signal->subject_id)),
            'category' => 'predictive',
            'severity' => $signal->severity,
            'available' => true,
            'reason' => null,
            ...$this->priorityForSeverity($signal->severity),
            'evidence' => [
                'subject_id' => (int) $signal->subject_id,
                'subject_label' => $signal->subject_label,
                'score' => $signal->score === null ? null : (float) $signal->score,
                'confidence' => $signal->confidence === null ? null : (float) $signal->confidence,
                'distinct_chapters' => $components['distinct_chapters'] ?? null,
                'root_blocker_count' => $components['root_blocker_count'] ?? null,
                'detected_at' => $signal->detected_at,
                'evidence_records' => $evidenceRecords,
                'recommendation' => $recommendation ? [
                    'id' => $recommendation->id,
                    'status' => $recommendation->status,
                    'governance_passed' => (bool) $recommendation->governance_passed,
                    'title' => $recommendation->title,
                    'risk_level' => $recommendation->risk_level,
                    'confidence' => $recommendation->confidence === null ? null : (float) $recommendation->confidence,
                ] : null,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function interventionCardsFor(int $learnerId): array
    {
        $engine = app(PredictiveInterventionEngine::class);

        return [
            $this->interventionCard('pal.predictive.disengagement', 'Disengagement risk', $engine->predictDisengagement($learnerId), $learnerId, 'pal_learning_sessions/pal_session_events'),
            $this->interventionCard('pal.predictive.failure', 'Assessment failure risk', $engine->predictFailure($learnerId), $learnerId, 'pal_assessment_results/pal_competencies'),
            $this->interventionCard('pal.predictive.burnout', 'Burnout risk', $engine->predictBurnout($learnerId), $learnerId, 'pal_learning_sessions/pal_session_events'),
        ];
    }

    private function interventionCard(string $id, string $title, array $result, int $learnerId, string $sourceTables): array
    {
        if (! ($result['has_data'] ?? false)) {
            return $this->unavailable(
                $id,
                $title,
                sprintf(
                    'Learner #%d has no usable history in %s yet — this engine reports "not enough data" rather than a fabricated low-risk score.',
                    $learnerId,
                    $sourceTables
                )
            );
        }

        return [
            'id' => $id,
            'title' => $title,
            'category' => 'predictive',
            'severity' => $result['risk_level'],
            'available' => true,
            'reason' => null,
            ...$this->priorityForSeverity($result['risk_level']),
            'evidence' => [
                'risk_score' => $result['risk_score'],
                'signals' => $result['signals'] ?? [],
                'trigger_intervention' => $result['trigger_intervention'] ?? null,
                'recommended_actions' => $result['recommended_actions'] ?? [],
            ],
        ];
    }

    private function priorityForSeverity(string $severity): array
    {
        $severity = strtolower($severity);

        if (in_array($severity, ['critical', 'high'], true)) {
            return ['priority' => 'Major', 'priority_reason' => sprintf('Severity band "%s" — at or above the high-risk threshold.', $severity)];
        }

        if (in_array($severity, ['moderate', 'medium'], true)) {
            return ['priority' => 'Medium', 'priority_reason' => sprintf('Severity band "%s".', $severity)];
        }

        return ['priority' => 'Minor', 'priority_reason' => sprintf('Severity band "%s" — below the Medium threshold.', $severity)];
    }

    private function unavailable(string $id, string $title, string $reason): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'category' => 'predictive',
            'severity' => 'info',
            'available' => false,
            'reason' => $reason,
            'priority' => 'Minor',
            'priority_reason' => 'Not available — see reason.',
            'evidence' => [],
        ];
    }
}
