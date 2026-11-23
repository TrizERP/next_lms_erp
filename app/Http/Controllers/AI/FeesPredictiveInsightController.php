<?php

namespace App\Http\Controllers\AI;

use App\Domain\Fees\Collection\FeesPredictiveInsightReader;
use App\Http\Controllers\AI\Concerns\RequiresFeesStaffAccess;
use Illuminate\Http\Request;
use Throwable;

/**
 * The Fees "Recommendation" panel's Predictive Intelligence section — a
 * thin, read-only reshaping of what FeesPredictiveAgent has already
 * persisted (`ai_signals` for `fees_projected_default_risk`, plus the
 * recommendation it drafted if governance passed). This endpoint never
 * runs the agent itself: a GET must never trigger a potentially slow
 * sweep, and "nothing found yet" here honestly means "the agent hasn't
 * run, or found nothing" rather than "there is nothing to find" — see
 * POST /api/ai/agents/k12_fees_predictive/run to actually run it.
 *
 * Deliberately NOT gated by KnowledgeGraphSettingsResolver, unlike the
 * graph-backed Fees controllers: this capability is entirely SQL/ai_signals
 * sourced and has nothing to do with Neo4j reachability.
 */
class FeesPredictiveInsightController extends AiController
{
    use RequiresFeesStaffAccess;

    public function __construct(private readonly FeesPredictiveInsightReader $reader)
    {
    }

    public function index(Request $request)
    {
        try {
            $scope = $this->scope($request);
            $this->assertStaffAccess($scope);

            $validated = $request->validate([
                'student_id' => 'nullable|integer',
            ]);

            $cards = $this->reader->cardsFor($scope->selectedInstituteId, $validated['student_id'] ?? null);

            return $this->success('Fees predictive insights loaded.', [
                'generated_at' => now()->toIso8601String(),
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
