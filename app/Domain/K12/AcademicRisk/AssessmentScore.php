<?php

namespace App\Domain\K12\AcademicRisk;

/**
 * Converts an attempt to a percentage only when its denominator is recorded.
 *
 * Raw obtain_marks has no maximum in the legacy table, so it cannot be assumed to
 * be a percentage.
 */
final class AssessmentScore
{
    public const MIN_ANSWERED_QUESTIONS = 3;

    public static function ratio(object|array $attempt): ?float
    {
        $attempt = is_array($attempt) ? (object) $attempt : $attempt;
        $right = (int) ($attempt->total_right ?? 0);
        $wrong = (int) ($attempt->total_wrong ?? 0);
        $answered = $right + $wrong;

        return $answered >= self::MIN_ANSWERED_QUESTIONS ? $right / $answered : null;
    }
}
