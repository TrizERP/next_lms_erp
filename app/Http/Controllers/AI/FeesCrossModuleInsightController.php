<?php

namespace App\Http\Controllers\AI;

use App\Domain\KnowledgeGraph\Fees\FeesCrossModuleInsightEngine;
use App\Domain\KnowledgeGraph\KnowledgeGraphSettingsResolver;
use App\Http\Controllers\AI\Concerns\RequiresFeesStaffAccess;
use Illuminate\Http\Request;
use Throwable;

/**
 * The Fees "Recommendation" panel's Cross-Module Intelligence section —
 * insights that need a second module's data (attendance, admissions, PAL
 * engagement/mastery), not just Fees' own. See FeesCrossModuleInsightEngine
 * for which KG layer backs each pair.
 */
class FeesCrossModuleInsightController extends AiController
{
    use RequiresFeesStaffAccess;

    private const MODULE_KEY = 'fees';

    public function __construct(
        private readonly FeesCrossModuleInsightEngine $engine,
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
                return $this->success('Fees cross-module insights loaded.', [
                    'generated_at' => now()->toIso8601String(),
                    'syear' => $validated['syear'],
                    'sub_institute_id' => $scope->selectedInstituteId,
                    'cards' => [],
                    'available' => false,
                    'reason' => 'Knowledge Graph is switched off for Fees in this school.',
                ]);
            }

            $cards = $this->engine->insightsFor($scope->selectedInstituteId, $validated['syear']);

            return $this->success('Fees cross-module insights loaded.', [
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
