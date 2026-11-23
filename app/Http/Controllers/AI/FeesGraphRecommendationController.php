<?php

namespace App\Http\Controllers\AI;

use App\Domain\KnowledgeGraph\Fees\FeesGraphRecommendationEngine;
use App\Domain\KnowledgeGraph\KnowledgeGraphSettingsResolver;
use App\Http\Controllers\AI\Concerns\RequiresFeesStaffAccess;
use Illuminate\Http\Request;
use Throwable;

/**
 * The Fees "Recommendation" panel's data source. Deliberately separate from
 * FeesRecommendationService/RecommendationController (the SQL-based
 * recommendation governance queue) and from BrainFeesIntelligenceController
 * (the SQL "Enterprise Brain" screen) — everything returned here is read
 * straight from Neo4j via FeesGraphRecommendationEngine, with no SQL
 * fallback. See that class for the available/reason honesty contract.
 */
class FeesGraphRecommendationController extends AiController
{
    use RequiresFeesStaffAccess;

    private const MODULE_KEY = 'fees';

    public function __construct(
        private readonly FeesGraphRecommendationEngine $engine,
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

            // Every card this engine produces is Neo4j-sourced, so when Fees'
            // Knowledge Graph is off for this school the honest answer is the
            // same five-card shape with each one individually unavailable —
            // not a 403, since the panel's frontend always expects `cards`.
            $cards = $this->knowledgeGraphSettings->isEnabledFor(self::MODULE_KEY, $scope->selectedInstituteId)
                ? $this->engine->cardsFor($scope->selectedInstituteId, $validated['syear'])
                : $this->engine->disabledCards();

            return $this->success('Fees recommendations loaded.', [
                'generated_at' => now()->toIso8601String(),
                'syear' => $validated['syear'],
                'sub_institute_id' => $scope->selectedInstituteId,
                'cards' => $cards,
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }
}
