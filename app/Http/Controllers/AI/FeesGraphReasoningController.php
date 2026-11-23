<?php

namespace App\Http\Controllers\AI;

use App\Domain\KnowledgeGraph\Fees\FeesGraphReasoningEngine;
use App\Domain\KnowledgeGraph\KnowledgeGraphSettingsResolver;
use App\Http\Controllers\AI\Concerns\RequiresFeesStaffAccess;
use Illuminate\Http\Request;
use Throwable;

/**
 * The Fees "Recommendation" panel's Graph Reasoning section — relationships
 * discovered by walking/aggregating the Fees graph, never ones stated as a
 * single direct edge. See FeesGraphReasoningEngine for which cards are
 * re-tagged from FeesGraphRecommendationEngine versus genuinely new.
 *
 * For on-demand "explain the connection between these two specific nodes"
 * drill-down (not the cohort sweep this endpoint returns), the existing
 * generic GET /api/ai/knowledge-graph/browser/path already covers it —
 * deliberately not duplicated here.
 */
class FeesGraphReasoningController extends AiController
{
    use RequiresFeesStaffAccess;

    private const MODULE_KEY = 'fees';

    public function __construct(
        private readonly FeesGraphReasoningEngine $engine,
        private readonly KnowledgeGraphSettingsResolver $knowledgeGraphSettings
    ) {
    }

    public function index(Request $request)
    {
        try {
            $scope = $this->scope($request);
            $this->assertStaffAccess($scope);

            $validated = $request->validate([
                'syear' => 'required|string|max:20',
            ]);

            if (! $this->knowledgeGraphSettings->isEnabledFor(self::MODULE_KEY, $scope->selectedInstituteId)) {
                return $this->success('Fees graph reasoning loaded.', [
                    'generated_at' => now()->toIso8601String(),
                    'syear' => $validated['syear'],
                    'sub_institute_id' => $scope->selectedInstituteId,
                    'cards' => [],
                    'available' => false,
                    'reason' => 'Knowledge Graph is switched off for Fees in this school.',
                ]);
            }

            $cards = $this->engine->reasoningFor($scope->selectedInstituteId, $validated['syear']);

            return $this->success('Fees graph reasoning loaded.', [
                'generated_at' => now()->toIso8601String(),
                'syear' => $validated['syear'],
                'sub_institute_id' => $scope->selectedInstituteId,
                'cards' => $cards,
                'available' => true,
                'reason' => null,
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }
}
