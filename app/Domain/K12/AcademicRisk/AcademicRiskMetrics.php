<?php

namespace App\Domain\K12\AcademicRisk;

/**
 * The metric keys academic-risk outcomes are measured against.
 *
 * Kept as constants rather than loose strings because EsoBindingRule requires a
 * recommendation to name a metric, and OutcomeTracker requires a resolver registered
 * under exactly that name. A typo would produce a recommendation that passes
 * governance and can never be scored.
 */
final class AcademicRiskMetrics
{
    public const ASSESSMENT_AVERAGE = 'k12.assessment_average_percent';

    public const ATTENDANCE_RATE = 'k12.attendance_present_percent';

    public const ASSIGNMENT_COMPLETION = 'k12.assignment_completion_percent';
}
