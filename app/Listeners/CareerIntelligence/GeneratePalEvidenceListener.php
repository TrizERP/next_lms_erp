<?php

namespace App\Listeners\CareerIntelligence;

use App\CareerIntelligence\Evidence\PalAdaptiveEvidenceAdapter;
use App\Events\Pal\ConceptOutcomeRecorded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PAL-to-Career bridge: regenerates the student's source_type='pal'
 * evidence_events whenever ESO records a mastery or retention outcome, so
 * adaptive practice reaches Career Intelligence without the manual
 * `cai:ingest-evidence` run.
 *
 * Queued for the same reason as GenerateAssessmentEvidenceListener: the PAL
 * answer request must never wait on, or fail because of, evidence generation.
 * No evidence rules live here. PalAdaptiveEvidenceAdapter owns them, including
 * the MIN_CONCEPTS floor and the no-op check that skips a run with nothing new.
 */
class GeneratePalEvidenceListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 2;

    public function handle(ConceptOutcomeRecorded $event): void
    {
        // The adapter wants an academic year, which the PAL answer path does
        // not carry. The student's most recent enrolment row is the same
        // resolution the adapter itself uses to stamp the grade.
        $syear = DB::table('tblstudent_enrollment')
            ->where('student_id', $event->studentId)
            ->orderByDesc('syear')
            ->orderByDesc('id')
            ->value('syear');

        if ($syear === null) {
            return;
        }

        try {
            app(PalAdaptiveEvidenceAdapter::class)->ingest((string) $event->studentId, (string) $syear);
        } catch (Throwable $e) {
            // On QUEUE_CONNECTION=sync this runs inside the PAL answer request,
            // where an evidence failure must never surface to the student.
            $this->failed($event, $e);
        }
    }

    public function failed(ConceptOutcomeRecorded $event, Throwable $exception): void
    {
        Log::warning('PAL evidence generation failed after concept outcome: ' . $exception->getMessage(), [
            'student_id' => $event->studentId,
            'concept_id' => $event->conceptId,
            'outcome' => $event->outcome,
        ]);
    }
}
