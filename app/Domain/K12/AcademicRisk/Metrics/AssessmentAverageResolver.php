<?php

namespace App\Domain\K12\AcademicRisk\Metrics;

use App\Domain\AI\Outcomes\MetricResolver;
use App\Domain\K12\AcademicRisk\AcademicRiskMetrics;
use App\Domain\K12\AcademicRisk\StudentScope;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads a student's recent assessment average, as a percentage.
 *
 * Uses the same right/wrong ratio the decline detector uses, so a baseline captured
 * at intervention time and an observation taken a month later are the same
 * measurement — comparing a ratio against a raw mark would make every outcome
 * meaningless.
 */
class AssessmentAverageResolver implements MetricResolver
{
    private const WINDOW = 3;

    public function __construct(private readonly StudentScope $scope)
    {
    }

    public function metricKey(): string
    {
        return AcademicRiskMetrics::ASSESSMENT_AVERAGE;
    }

    public function label(): string
    {
        return 'Assessment average (%)';
    }

    public function resolve(string $subjectEntityKey, int|string $subjectId, McpRequestContext $scope): ?float
    {
        if ($subjectEntityKey !== 'student' || ! Schema::hasTable('lms_online_exam')) {
            return null;
        }

        // Scope first: the exam table has no tenant column of its own.
        if ($this->scope->name((int) $subjectId, $scope) === null) {
            return null;
        }

        $attempts = DB::table('lms_online_exam')
            ->where('student_id', (int) $subjectId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::WINDOW)
            ->get(['obtain_marks', 'total_right', 'total_wrong']);

        if ($attempts->isEmpty()) {
            // Unknown, not zero. Returning 0.0 would score the intervention as a
            // catastrophic failure when in fact nothing has been assessed yet.
            return null;
        }

        $ratios = [];

        foreach ($attempts as $attempt) {
            $right = (int) ($attempt->total_right ?? 0);
            $wrong = (int) ($attempt->total_wrong ?? 0);
            $answered = $right + $wrong;

            if ($answered > 0) {
                $ratios[] = $right / $answered;

                continue;
            }

            if (is_numeric($attempt->obtain_marks ?? null)) {
                $ratios[] = max(0.0, min(1.0, (float) $attempt->obtain_marks / 100));
            }
        }

        if ($ratios === []) {
            return null;
        }

        return round((array_sum($ratios) / count($ratios)) * 100, 2);
    }
}
