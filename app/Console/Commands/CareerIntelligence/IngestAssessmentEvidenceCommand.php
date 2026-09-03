<?php

namespace App\Console\Commands\CareerIntelligence;

use App\CareerIntelligence\Evidence\AssessmentEvidenceAdapter;
use Illuminate\Console\Command;

/**
 * Operational entry point for AssessmentEvidenceAdapter (Phase 6). Run
 * per-student, e.g. from a scheduled job once real batch triggers are
 * decided — deliberately NOT wired into app/Console/Kernel's schedule here,
 * since how/when to batch this across the whole student roster is a product
 * decision this pass didn't make.
 */
class IngestAssessmentEvidenceCommand extends Command
{
    protected $signature = 'cai:ingest-evidence {studentId} {academicYear}';

    protected $description = 'Ingest assessment-derived evidence_events rows for one student';

    public function handle(AssessmentEvidenceAdapter $adapter): int
    {
        $studentId = (string) $this->argument('studentId');
        $academicYear = (string) $this->argument('academicYear');

        $written = $adapter->ingest($studentId, $academicYear);

        if (empty($written)) {
            $this->info("No new evidence written for student {$studentId} (no assessment data, or below the minimum-attempt threshold per subject).");

            return self::SUCCESS;
        }

        $this->info(count($written) . " evidence_events row(s) written for student {$studentId}.");

        return self::SUCCESS;
    }
}
