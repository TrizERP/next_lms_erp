<?php

namespace App\Http\Controllers\AI;

use App\Domain\KnowledgeGraph\Fees\FeesGraphRecommendationEngine;
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
    public function __construct(private readonly FeesGraphRecommendationEngine $engine)
    {
    }

    public function index(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'syear' => 'required|string|max:20',
            ]);

            $cards = $this->engine->cardsFor($scope->selectedInstituteId, $validated['syear']);

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
