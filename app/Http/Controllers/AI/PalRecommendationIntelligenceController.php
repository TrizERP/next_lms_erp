<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Agents\AgentRegistry;
use App\Domain\AI\Agents\AgentManifest;
use App\Domain\AI\Recommendations\PalNextBestActionResolver;
use App\Domain\KnowledgeGraph\KnowledgeGraphSettingsResolver;
use App\Domain\KnowledgeGraph\Pal\PalCrossModuleInsightEngine;
use App\Domain\KnowledgeGraph\Pal\PalGraphReasoningEngine;
use App\Domain\Pal\PalPredictiveInsightReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The Teach/Learn (PAL) "Recommendation" panel's single aggregator — all
 * five intelligence capabilities as tagged sections in one payload, the
 * same composition shape as FeesRecommendationIntelligenceController.
 *
 * `cross_module`/`graph_reasoning` share the one gate the two Fees sections
 * share (Knowledge Graph on/off for this module) — `predictive` never does,
 * since it reads SQL (ai_signals / PredictiveInterventionEngine), not
 * Neo4j. `recommendation` is only computed per-learner when the caller
 * names one; otherwise it ranks across the whole tenant rather than
 * inventing a per-learner slice of a cohort statistic.
 */
class PalRecommendationIntelligenceController extends AiController
{
    private const MODULE_KEY = 'teach_learn';

    public function __construct(
        private readonly PalCrossModuleInsightEngine $crossModuleEngine,
        private readonly PalGraphReasoningEngine $reasoningEngine,
        private readonly PalPredictiveInsightReader $predictiveReader,
        private readonly PalNextBestActionResolver $nextBestActionResolver,
        private readonly AgentRegistry $agentRegistry,
        private readonly KnowledgeGraphSettingsResolver $knowledgeGraphSettings,
    ) {
    }

    public function index(Request $request)
    {
        try {
            $scope = $this->scope($request);
            $tenant = $scope->selectedInstituteId;

            $validated = $request->validate([
                'learner_id' => 'nullable|integer|min:1',
                'standard_id' => 'nullable|integer|min:1',
                'subject_id' => 'nullable|integer|min:1',
            ]);

            $learnerId = $validated['learner_id'] ?? null;
            $standardId = $validated['standard_id'] ?? null;
            $subjectId = $validated['subject_id'] ?? null;

            $kgEnabled = $this->knowledgeGraphSettings->isEnabledFor(self::MODULE_KEY, $tenant);
            $kgOffReason = 'Knowledge Graph is switched off for Teach/Learn in this school.';
            $syear = $this->resolveSyear($scope->academicYear);

            return $this->success('PAL recommendation intelligence loaded.', [
                'generated_at' => now()->toIso8601String(),
                'sub_institute_id' => $tenant,
                'learner_id' => $learnerId,
                'standard_id' => $standardId,
                'subject_id' => $subjectId,
                'capabilities' => [
                    'cross_module' => $kgEnabled
                        ? ['available' => true, 'reason' => null, 'cards' => $this->crossModuleEngine->insightsFor($tenant, $syear)]
                        : ['available' => false, 'reason' => $kgOffReason, 'cards' => []],
                    'graph_reasoning' => $kgEnabled
                        ? ['available' => true, 'reason' => null, 'cards' => $this->reasoningEngine->reasoningFor($tenant, $learnerId, $standardId, $subjectId)]
                        : ['available' => false, 'reason' => $kgOffReason, 'cards' => []],
                    'predictive' => [
                        'available' => true,
                        'reason' => null,
                        'cards' => $this->predictiveReader->cardsFor($tenant, $learnerId),
                    ],
                    'recommendation' => $this->recommendationSection($learnerId, $standardId, $subjectId, $scope),
                    'agentic' => [
                        'available' => true,
                        'reason' => null,
                        'agent_state' => $this->agentState($scope),
                        'pending_recommendations' => $this->pendingRecommendations($tenant),
                        'execution_note' => 'Approving records a human decision. Nothing executes automatically for PAL yet.',
                    ],
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    private function recommendationSection(?int $learnerId, ?int $standardId, ?int $subjectId, $scope): array
    {
        if ($learnerId !== null) {
            return $this->nextBestActionResolver->topPickFor($learnerId, $scope, $standardId, $subjectId);
        }

        return $this->nextBestActionResolver->topPickForTenant($scope);
    }

    private function agentState($scope): array
    {
        $manifests = $this->agentRegistry->forRole($scope->role, $scope->selectedInstituteId, 'pal');

        return array_map(fn (AgentManifest $manifest) => $manifest->toArray(), $manifests);
    }

    /**
     * The actual rows waiting on a person, not just a count — neither of
     * the platform's two generic approval-queue views would surface these:
     * ModuleApprovalQueue filters by workflow_key (pal_coherence_risk binds
     * none) and the generic /recommendations/pending endpoint filters to
     * status='pending_approval' only (these drafts are deliberately
     * is_consequential:false, so they stay in 'draft' — see
     * RecommendVerb::normalizeForPersistence()). DecisionGate::approve()
     * itself already accepts both statuses, so this list's ids are
     * approvable through the existing generic decision endpoints as-is.
     */
    private function pendingRecommendations(int $tenant): array
    {
        if (! Schema::hasTable('ai_recommendations')) {
            return [];
        }

        return DB::table('ai_recommendations')
            ->where('sub_institute_id', $tenant)
            ->where('domain', 'pal')
            ->where('governance_passed', true)
            ->whereIn('status', ['draft', 'pending_approval'])
            ->orderByDesc('confidence')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'title', 'body', 'rationale', 'risk_level', 'confidence', 'status', 'subject_entity_key', 'subject_id', 'evidence_ids', 'created_at'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'title' => $row->title,
                'body' => $row->body,
                'rationale' => $row->rationale,
                'risk_level' => $row->risk_level,
                'confidence' => $row->confidence === null ? null : (float) $row->confidence,
                'status' => $row->status,
                'subject_entity_key' => $row->subject_entity_key,
                'subject_id' => $row->subject_id,
                'evidence_count' => $row->evidence_ids ? count(json_decode((string) $row->evidence_ids, true) ?: []) : 0,
                'created_at' => $row->created_at,
            ])
            ->all();
    }

    /**
     * Best-effort syear lookup for the one card (Fees' academic correlation)
     * that needs it — PAL itself is not academic-year-scoped, so when the
     * caller's token carries no academic_year, that one card reports why it
     * can't resolve rather than guessing a "current" year.
     */
    private function resolveSyear(?int $academicYearId): ?string
    {
        if ($academicYearId === null || ! Schema::hasTable('academic_year')) {
            return null;
        }

        $syear = DB::table('academic_year')->where('id', $academicYearId)->value('syear');

        return $syear !== null ? (string) $syear : null;
    }
}
