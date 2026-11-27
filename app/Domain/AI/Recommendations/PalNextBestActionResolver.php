<?php

namespace App\Domain\AI\Recommendations;

use App\Services\Mcp\McpRequestContext;
use App\Services\PAL\Coherence\CoherenceRecommender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "What's the single most relevant next action for this learner?" — a
 * ranking READ over already-governed output plus, only when nothing
 * governed exists yet, a clearly-labeled real-time suggestion. Never a new
 * way to draft a recommendation.
 *
 * Modeled on FeesNextBestActionResolver, domain-filtered to 'pal'. One
 * addition Fees' own resolver has no equivalent of: when no governed pick
 * and no open case exist for a named learner (which, given the governed
 * pal_coherence_risk agent only detects compounding multi-chapter gaps, is
 * the common case), fall back to CoherenceRecommender::nextBestAction() —
 * PAL's own real-time, ungoverned "what to work on next" engine — so the
 * Recommendation Intelligence section is not empty just because nothing has
 * been reviewed yet. That fallback is always tagged
 * `governed: false, label: 'real-time suggestion, not yet reviewed by a
 * person'` and is never merged into the governed field.
 *
 * Cross-Module Intelligence and Graph Reasoning (Capabilities 1-2) are
 * deliberately NOT ranked here, same reasoning as Fees' resolver: both
 * produce cohort-level cards with no clean per-learner row to point at.
 */
class PalNextBestActionResolver
{
    private const RISK_RANK = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

    public function __construct(private readonly CoherenceRecommender $recommender)
    {
    }

    public function topPickFor(int $learnerId, McpRequestContext $scope, ?int $standardId = null, ?int $subjectId = null): array
    {
        $recommendation = $this->bestRecommendation($scope, $learnerId);

        if ($recommendation !== null) {
            return ['available' => true, 'reason' => null, 'top_pick' => $recommendation];
        }

        $case = $this->openCaseFallback($scope, $learnerId);

        if ($case !== null) {
            return ['available' => true, 'reason' => null, 'top_pick' => $case];
        }

        if ($standardId !== null && $subjectId !== null) {
            $realtime = $this->realtimeFallback($learnerId, $standardId, $subjectId);

            if ($realtime !== null) {
                return ['available' => true, 'reason' => null, 'top_pick' => $realtime];
            }
        }

        return [
            'available' => false,
            'reason' => sprintf(
                'No governed recommendation, no open case, and no coherence-map suggestion exist for learner #%d — there is nothing to recommend.',
                $learnerId
            ),
            'top_pick' => null,
        ];
    }

    /**
     * Same ranking as topPickFor(), without a learner filter — the single
     * highest-priority governed PAL recommendation (or open case) anywhere
     * in the tenant. No real-time fallback here: that engine only answers
     * for one named learner, so a tenant-wide pick cannot invent one.
     */
    public function topPickForTenant(McpRequestContext $scope): array
    {
        $recommendation = $this->bestRecommendation($scope);

        if ($recommendation !== null) {
            return ['available' => true, 'reason' => null, 'top_pick' => $recommendation];
        }

        $case = $this->openCaseFallback($scope);

        if ($case !== null) {
            return ['available' => true, 'reason' => null, 'top_pick' => $case];
        }

        return [
            'available' => false,
            'reason' => 'No governed PAL recommendation and no open PAL case exist anywhere in this school — there is nothing to recommend.',
            'top_pick' => null,
        ];
    }

    private function bestRecommendation(McpRequestContext $scope, ?int $learnerId = null): ?array
    {
        if (! Schema::hasTable('ai_recommendations')) {
            return null;
        }

        $rows = DB::table('ai_recommendations')
            ->where('domain', 'pal')
            ->when($learnerId !== null, fn ($q) => $q->where('subject_entity_key', 'student')->where('subject_id', $learnerId))
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->whereIn('status', ['pending_approval', 'draft'])
            ->where('governance_passed', true)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $ranked = $rows->map(function ($row) {
            $evidenceIds = $row->evidence_ids ? json_decode((string) $row->evidence_ids, true) : [];

            return (object) [
                'row' => $row,
                'sort_key' => [
                    self::RISK_RANK[$row->risk_level] ?? 0,
                    (float) ($row->confidence ?? 0),
                    (string) $row->created_at,
                    is_array($evidenceIds) ? count($evidenceIds) : 0,
                ],
            ];
        })->sort(fn ($a, $b) => $b->sort_key <=> $a->sort_key)->values();

        $best = $ranked->first()->row;
        $evidenceIds = $best->evidence_ids ? json_decode((string) $best->evidence_ids, true) : [];
        $evidenceIds = is_array($evidenceIds) ? $evidenceIds : [];

        return [
            'source' => 'recommendation',
            'governed' => true,
            'id' => (int) $best->id,
            'title' => $best->title,
            'risk_level' => $best->risk_level,
            'confidence' => $best->confidence === null ? null : (float) $best->confidence,
            'evidence_ids' => $evidenceIds,
            'evidence_records' => $this->evidenceRecordsFor($evidenceIds, $scope->selectedInstituteId),
            'recommendation_id' => (int) $best->id,
            'workflow_key' => $best->workflow_key,
            'requires_approval' => (bool) $best->requires_approval,
            'status' => $best->status,
            'subject_entity_key' => $best->subject_entity_key,
            'subject_id' => $best->subject_id,
        ];
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function evidenceRecordsFor(array $ids, int $tenant): array
    {
        if ($ids === [] || ! Schema::hasTable('ai_evidence')) {
            return [];
        }

        return DB::table('ai_evidence')
            ->whereIn('id', $ids)
            ->where('sub_institute_id', $tenant)
            ->get(['id', 'summary', 'source_service', 'source_table', 'verified', 'is_generated'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'summary' => $row->summary,
                'source' => $row->source_service ?? $row->source_table,
                'verified' => (bool) $row->verified,
                'is_generated' => (bool) $row->is_generated,
            ])
            ->all();
    }

    private function openCaseFallback(McpRequestContext $scope, ?int $learnerId = null): ?array
    {
        if (! Schema::hasTable('ai_cases')) {
            return null;
        }

        $case = DB::table('ai_cases')
            ->where('domain', 'pal')
            ->when($learnerId !== null, fn ($q) => $q->where('subject_entity_key', 'student')->where('subject_id', $learnerId))
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->whereIn('status', ['open', 'cased', 'in_progress'])
            ->orderByDesc('priority_score')
            ->orderByDesc('opened_at')
            ->first();

        if ($case === null) {
            return null;
        }

        return [
            'source' => 'case',
            'governed' => true,
            'id' => (int) $case->id,
            'title' => $case->title,
            'risk_level' => null,
            'confidence' => null,
            'evidence_ids' => [],
            'recommendation_id' => null,
            'workflow_key' => null,
            'requires_approval' => null,
            'status' => $case->status,
            'subject_entity_key' => $case->subject_entity_key,
            'subject_id' => $case->subject_id,
        ];
    }

    /**
     * PAL's own real-time "what next" engine, surfaced only when nothing
     * governed exists for this learner yet. Always clearly labeled as not
     * reviewed by a person.
     */
    private function realtimeFallback(int $learnerId, int $standardId, int $subjectId): ?array
    {
        $result = $this->recommender->nextBestAction($learnerId, $standardId, $subjectId);

        if (in_array($result['action'], ['no_map', 'no_entry_point'], true)) {
            return null;
        }

        $why = $result['why'] ?? [];

        return [
            'source' => 'coherence_recommender',
            'governed' => false,
            'label' => 'Real-time suggestion, not yet reviewed by a person.',
            'id' => null,
            'title' => $result['concept']['name'] ?? ($result['action'] === 'complete' ? 'All mapped concepts mastered' : 'Next concept'),
            'risk_level' => $this->riskLevelFor($result['action'], (int) ($why['depth'] ?? 0)),
            'confidence' => null,
            'evidence_ids' => [],
            'evidence_records' => [],
            'recommendation_id' => null,
            'workflow_key' => null,
            'requires_approval' => null,
            'status' => 'not_governed',
            'subject_entity_key' => 'student',
            'subject_id' => $learnerId,
            'action' => $result['action'],
            'why' => $why,
            'concept' => $result['concept'],
        ];
    }

    private function riskLevelFor(string $action, int $depth): string
    {
        if ($action === 'unblock' && $depth >= 2) {
            return 'high';
        }

        if ($action === 'unblock' || $action === 'consolidate') {
            return 'medium';
        }

        return 'low';
    }
}
