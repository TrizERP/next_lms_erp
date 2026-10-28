<?php

namespace App\Domain\K12\AcademicRisk\Metrics;

use App\Domain\AI\Outcomes\MetricResolver;
use App\Domain\K12\AcademicRisk\AcademicRiskMetrics;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads a student's recent ESO/PAL concept mastery, as a percentage.
 *
 * Sourced from `learner_node_state.mastery_estimate` — the ADR-001-ratified,
 * learner-facing mastery state `EsoPolicyService` writes on every scored response
 * — not `pal_competencies` (a derived, chapter-grain historical metric) or BKT's
 * `pal_concept_mastery` (a different engine's own internal state; writing this
 * resolver against it would read someone else's working data). Windowed to the
 * student's most recently active nodes, the same rolling-average shape
 * AssessmentAverageResolver uses, so a baseline captured at intervention time and
 * an observation taken later compare like with like.
 *
 * PAL/ESO coverage is not universal — most chapters do not yet carry a full
 * learner journey. Returning null for a student with no PAL attempts (rather than
 * 0.0) is what keeps that sparseness from ever being scored as a regression.
 */
class PalMasteryTrendResolver implements MetricResolver
{
    private const WINDOW = 5;

    public function metricKey(): string
    {
        return AcademicRiskMetrics::PAL_MASTERY_TREND;
    }

    public function label(): string
    {
        return 'PAL concept mastery (%)';
    }

    public function resolve(string $subjectEntityKey, int|string $subjectId, McpRequestContext $scope): ?float
    {
        if ($subjectEntityKey !== 'student' || ! Schema::hasTable('learner_node_state')) {
            return null;
        }

        $rows = DB::table('learner_node_state')
            ->where('student_id', (int) $subjectId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->where('attempts', '>', 0)
            ->orderByDesc('last_seen_at')
            ->limit(self::WINDOW)
            ->get(['mastery_estimate']);

        if ($rows->isEmpty()) {
            // Unknown, not zero: no PAL attempts yet is not the same as "mastery
            // collapsed to zero", and scoring it that way would make every
            // PAL-uncovered student's outcome read as a catastrophic failure.
            return null;
        }

        return round(((float) $rows->avg('mastery_estimate')) * 100, 2);
    }
}
