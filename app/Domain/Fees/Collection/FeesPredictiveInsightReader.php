<?php

namespace App\Domain\Fees\Collection;

use Illuminate\Support\Facades\DB;

/**
 * Read-only reshaping of what FeesPredictiveAgent has already persisted
 * (`ai_signals` for `fees_projected_default_risk`) into the Lane-A
 * honesty-ladder card shape. Shared by FeesPredictiveInsightController
 * (the standalone endpoint) and FeesRecommendationIntelligenceController
 * (the five-capability aggregator), so the reshaping logic lives in
 * exactly one place. Never runs the agent itself — see
 * POST /api/ai/agents/k12_fees_predictive/run for that.
 */
class FeesPredictiveInsightReader
{
    public function cardsFor(int $tenant, ?int $studentId = null): array
    {
        $query = DB::table('ai_signals')
            ->where('signal_key', FeesRiskTrendDetector::KEY)
            ->where('subject_entity_key', 'student')
            ->where('sub_institute_id', $tenant)
            ->whereIn('status', ['open', 'cased'])
            ->orderByDesc('detected_at');

        if ($studentId !== null) {
            $query->where('subject_id', $studentId);
        }

        return $query->limit(100)->get()->map(fn ($signal) => $this->cardFor($signal, $tenant))->all();
    }

    private function cardFor(object $signal, int $tenant): array
    {
        $components = $signal->components ? json_decode((string) $signal->components, true) : [];

        // Full records, not just ids: a reviewer deciding whether to trust this
        // card needs to read what was actually cited, not take "5 evidence
        // items exist" on faith.
        $evidenceRecords = DB::table('ai_evidence')
            ->where('subject_entity_key', 'student')
            ->where('subject_id', $signal->subject_id)
            ->where('kind', 'fee_balance_trend')
            ->where('sub_institute_id', $tenant)
            ->orderByDesc('id')
            ->limit(5)
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

        $evidenceIds = array_column($evidenceRecords, 'id');

        $recommendation = DB::table('ai_recommendations')
            ->where('subject_entity_key', 'student')
            ->where('subject_id', $signal->subject_id)
            ->where('workflow_key', 'fees_proactive_outreach')
            ->where('sub_institute_id', $tenant)
            ->orderByDesc('id')
            ->first(['id', 'status', 'governance_passed', 'title', 'risk_level', 'confidence']);

        return [
            'id' => 'fees.predictive.student.'.$signal->subject_id,
            'title' => sprintf('Projected fee default risk — %s', $signal->subject_label ?? ('Student #'.$signal->subject_id)),
            'category' => 'predictive',
            'severity' => $signal->severity,
            'available' => true,
            'reason' => null,
            'evidence' => [
                'subject_id' => (int) $signal->subject_id,
                'subject_label' => $signal->subject_label,
                'projected_30d_change' => $signal->score === null ? null : (float) $signal->score,
                'confidence' => $signal->confidence === null ? null : (float) $signal->confidence,
                'history_points' => $components['history_points'] ?? null,
                'first_score' => $components['first_score'] ?? null,
                'last_score' => $components['last_score'] ?? null,
                'detected_at' => $signal->detected_at,
                'evidence_ids' => $evidenceIds,
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
}
