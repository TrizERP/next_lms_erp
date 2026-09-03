<?php

namespace App\CareerIntelligence\Ingestion;

/**
 * The normalised result of SubjectEnrolmentAdapter::fetch(). Immutable.
 */
final class DeclaredPlan
{
    /**
     * @param string        $studentId
     * @param string        $academicYear
     * @param int           $grade
     * @param string|null   $stream         canonical stream code (SCIENCE_PCM, COMMERCE, ARTS…) or null if unresolved
     * @param string[]      $subjects       canonical Subject codes the student is enrolled in
     * @param bool          $resolved       false => CAI must return INSUFFICIENT_DATA
     * @param string|null   $unresolvedReason  human-readable why (shown to counsellor, not student)
     * @param array         $raw            original ERP rows, kept for audit / counsellor review
     */
    public function __construct(
        public readonly string  $studentId,
        public readonly string  $academicYear,
        public readonly int     $grade,
        public readonly ?string $stream,
        public readonly array   $subjects,
        public readonly bool    $resolved,
        public readonly ?string $unresolvedReason = null,
        public readonly array   $raw = [],
    ) {}
}
