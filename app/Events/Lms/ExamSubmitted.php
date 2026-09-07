<?php

namespace App\Events\Lms;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once per exam submission, after every required `lms_online_exam`
 * and `lms_online_exam_answer` row for the attempt has been committed —
 * i.e. the earliest point at which the attempt is complete and valid to
 * analyze. Consumed by GenerateAssessmentEvidenceListener (Career
 * Intelligence evidence generation); other listeners can subscribe to the
 * same event later without onlineExamController needing to know about them.
 */
class ExamSubmitted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $studentId,
        public readonly string $academicYear,
        public readonly int $onlineExamId,
        public readonly int $questionPaperId,
    ) {
    }
}
