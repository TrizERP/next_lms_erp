<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Recommendations\FeesNextBestActionResolver;
use App\Domain\Fees\Collection\FeesPredictiveInsightReader;
use App\Http\Controllers\AI\Concerns\RequiresFeesStaffAccess;
use App\Domain\KnowledgeGraph\Fees\FeesCrossModuleInsightEngine;
use App\Domain\KnowledgeGraph\Fees\FeesGraphReasoningEngine;
use App\Domain\KnowledgeGraph\KnowledgeGraphSettingsResolver;
use App\Services\AI\Fees\FeesAgentPipelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The Fees "Recommendation" panel's single aggregator — all five
 * intelligence capabilities as tagged sections in one payload, rather
 * than five separate menu tabs (there is only ever one `recommendation`
 * row in `fees_menu_categories`; sub-navigation within it is a frontend
 * concern). Every section delegates to that capability's own engine; this
 * controller only composes them and applies the one gate (Knowledge Graph
 * on/off) that two of the five sections share.
 *
 * `cross_module`/`graph_reasoning` degrade to `available:false` together
 * when Fees' Knowledge Graph is off — `predictive`/`agentic` never do,
 * since neither depends on Neo4j. `recommendation` is only computed when
 * the caller names a subject (it's a per-subject ranking read, not a
 * cohort sweep); otherwise it reports why nothing was ranked.
 */
class FeesRecommendationIntelligenceController extends AiController
{
    use RequiresFeesStaffAccess;

    private const MODULE_KEY = 'fees';

    private const WORKFLOW_KEYS = ['fees_collection', 'fees_proactive_outreach'];

    public function __construct(
        private readonly FeesCrossModuleInsightEngine $crossModuleEngine,
        private readonly FeesGraphReasoningEngine $reasoningEngine,
        private readonly FeesPredictiveInsightReader $predictiveReader,
        private readonly FeesNextBestActionResolver $nextBestActionResolver,
        private readonly FeesAgentPipelineService $pipelineService,
        private readonly KnowledgeGraphSettingsResolver $knowledgeGraphSettings,
    ) {
    }

    public function index(Request $request)
    {
        try {
            $scope = $this->scope($request);
            $this->assertStaffAccess($scope);
            $tenant = $scope->selectedInstituteId;

            $validated = $request->validate([
                'syear' => 'required|string|max:20',
                'subject_entity_key' => 'nullable|string|max:50',
                'subject_id' => 'nullable|integer',
            ]);

            $syear = $validated['syear'];
            $kgEnabled = $this->knowledgeGraphSettings->isEnabledFor(self::MODULE_KEY, $tenant);
            $kgOffReason = 'Knowledge Graph is switched off for Fees in this school.';

            return $this->success('Fees recommendation intelligence loaded.', [
                'generated_at' => now()->toIso8601String(),
                'syear' => $syear,
                'sub_institute_id' => $tenant,
                'capabilities' => [
                    'cross_module' => $kgEnabled
                        ? ['available' => true, 'reason' => null, 'cards' => $this->crossModuleEngine->insightsFor($tenant, $syear)]
                        : ['available' => false, 'reason' => $kgOffReason, 'cards' => []],
                    'graph_reasoning' => $kgEnabled
                        ? ['available' => true, 'reason' => null, 'cards' => $this->reasoningEngine->reasoningFor($tenant, $syear)]
                        : ['available' => false, 'reason' => $kgOffReason, 'cards' => []],
                    'predictive' => [
                        'available' => true,
                        'reason' => null,
                        'cards' => $this->predictiveReader->cardsFor($tenant, $validated['subject_id'] ?? null),
                    ],
                    'recommendation' => $this->recommendationSection($validated, $scope),
                    'agentic' => [
                        'available' => true,
                        'reason' => null,
                        'agent_state' => $this->pipelineService->getAgentState($tenant),
                        'pending_approvals_count' => $this->pendingApprovalsCount($tenant),
                    ],
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    private function recommendationSection(array $validated, $scope): array
    {
        if (! isset($validated['subject_entity_key'], $validated['subject_id'])) {
            return [
                'available' => false,
                'reason' => 'Pass subject_entity_key and subject_id to get a next-best-action pick for one subject.',
                'top_pick' => null,
            ];
        }

        return $this->nextBestActionResolver->topPickFor(
            $validated['subject_entity_key'],
            $validated['subject_id'],
            $scope
        );
    }

    private function pendingApprovalsCount(int $tenant): int
    {
        if (! Schema::hasTable('ai_recommendations')) {
            return 0;
        }

        return DB::table('ai_recommendations')
            ->where('sub_institute_id', $tenant)
            ->where('status', 'pending_approval')
            ->whereIn('workflow_key', self::WORKFLOW_KEYS)
            ->count();
    }
}
