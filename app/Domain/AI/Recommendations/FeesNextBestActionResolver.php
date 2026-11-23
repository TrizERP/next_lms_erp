<?php

namespace App\Domain\AI\Recommendations;

use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "What's the single most relevant next action for this subject?" — a
 * ranking READ over already-governed output, never a new way to draft a
 * recommendation.
 *
 * Two genuinely per-subject sources exist today:
 *   1. ai_recommendations already drafted for this subject (FeesAgent's
 *      fee_collection reviews, FeesPredictiveAgent's fees_proactive_outreach
 *      drafts) — ranked among themselves by risk level, confidence,
 *      recency, then evidence count.
 *   2. an open ai_cases row for this subject with no qualifying
 *      recommendation yet — a weaker, fallback pick, surfaced only when
 *      no recommendation exists.
 *
 * Cross-Module Intelligence and Graph Reasoning (Capabilities 1-2) are
 * deliberately NOT ranked here: both produce cohort-level cards (e.g. "15%
 * of the fee-gap cohort shows low attendance"), with no clean per-subject
 * row to point at for one individual student. Ranking them in would mean
 * inventing a fake per-subject slice of an aggregate statistic — exactly
 * the kind of overclaiming this resolver exists to refuse.
 *
 * It cannot rank a recommendation that failed RecommendVerb::validate()
 * into first place (governance_passed=true is a hard filter), and it
 * cannot promote a bare open case above an actual governed recommendation.
 */
class FeesNextBestActionResolver
{
    private const RISK_RANK = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

    public function topPickFor(string $subjectEntityKey, int|string $subjectId, McpRequestContext $scope): array
    {
        $recommendation = $this->bestRecommendation($subjectEntityKey, $subjectId, $scope);

        if ($recommendation !== null) {
            return ['available' => true, 'reason' => null, 'top_pick' => $recommendation];
        }

        $case = $this->openCaseFallback($subjectEntityKey, $subjectId, $scope);

        if ($case !== null) {
            return ['available' => true, 'reason' => null, 'top_pick' => $case];
        }

        return [
            'available' => false,
            'reason' => sprintf(
                'No governed recommendation and no open case exist for %s #%s — there is nothing to recommend.',
                $subjectEntityKey,
                $subjectId
            ),
            'top_pick' => null,
        ];
    }

    private function bestRecommendation(string $subjectEntityKey, int|string $subjectId, McpRequestContext $scope): ?array
    {
        if (! Schema::hasTable('ai_recommendations')) {
            return null;
        }

        $rows = DB::table('ai_recommendations')
            ->where('subject_entity_key', $subjectEntityKey)
            ->where('subject_id', $subjectId)
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
        ];
    }

    /**
     * The actual cited records, not just their ids — so a reviewer can read
     * what was cited rather than take "N evidence items" on faith.
     *
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

    private function openCaseFallback(string $subjectEntityKey, int|string $subjectId, McpRequestContext $scope): ?array
    {
        if (! Schema::hasTable('ai_cases')) {
            return null;
        }

        $case = DB::table('ai_cases')
            ->where('subject_entity_key', $subjectEntityKey)
            ->where('subject_id', $subjectId)
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
            'id' => (int) $case->id,
            'title' => $case->title,
            'risk_level' => null,
            'confidence' => null,
            'evidence_ids' => [],
            'recommendation_id' => null,
            'workflow_key' => null,
            'requires_approval' => null,
            'status' => $case->status,
        ];
    }
}
