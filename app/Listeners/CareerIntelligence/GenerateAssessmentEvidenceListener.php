<?php

namespace App\Listeners\CareerIntelligence;

use App\CareerIntelligence\Evidence\AssessmentEvidenceAdapter;
use App\Events\Lms\ExamSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Real-time exam-to-evidence bridge: regenerates the submitting student's
 * evidence_events from their complete assessment history (including this
 * submission) whenever an LMS online exam is submitted.
 *
 * Queued (ShouldQueue) deliberately: AssessmentEvidenceAdapter::ingest()
 * re-derives evidence across the student's WHOLE answer history on every
 * call, which grows with the student's exam activity over the school
 * year — that cost must never sit on the exam-submission request/response
 * path. On a queue worker (QUEUE_CONNECTION=database/redis/...) this runs
 * fully out-of-band; on QUEUE_CONNECTION=sync (this app's local default)
 * it still runs inline, so behaviour is unchanged in that configuration —
 * only environments with a real queue worker get the async benefit, with
 * no code change required to adopt it.
 *
 * No evidence-format or evidence-writing logic lives here — that stays
 * entirely inside AssessmentEvidenceAdapter (source_type='assessment',
 * same performance_level/strength/reliability rules, same
 * verified/contested/superseded_by versioning via supersede(), same
 * MIN_ATTEMPTS + subject-mapping + observed_at no-op idempotency checks
 * already used by the manual `cai:ingest-evidence` backfill command). This
 * listener only decides WHEN to call it.
 */
class GenerateAssessmentEvidenceListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Transient failures (DB contention, etc.) are worth one retry now that
     * this runs off the request path — unlike the old inline call, a retry
     * here can never re-show an error to the student, since the exam
     * submission response was already sent.
     */
    public int $tries = 2;

    public function handle(ExamSubmitted $event): void
    {
        app(AssessmentEvidenceAdapter::class)->ingest($event->studentId, $event->academicYear);
    }

    /**
     * Exhausted retries: log loudly (queue failed_jobs already records this,
     * but a warning keeps it visible in the same log stream ops already
     * watches) and swallow — evidence generation must never surface as a
     * user-facing failure, matching the exam-submission contract this
     * replaces.
     */
    public function failed(ExamSubmitted $event, Throwable $exception): void
    {
        Log::warning('Evidence generation failed after exam submit: ' . $exception->getMessage(), [
            'student_id' => $event->studentId,
            'academic_year' => $event->academicYear,
            'online_exam_id' => $event->onlineExamId,
        ]);
    }
}
