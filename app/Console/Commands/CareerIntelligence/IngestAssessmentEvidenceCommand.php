<?php

namespace App\Console\Commands\CareerIntelligence;

use App\CareerIntelligence\Evidence\AssessmentEvidenceAdapter;
use App\CareerIntelligence\Evidence\PalAdaptiveEvidenceAdapter;
use Illuminate\Console\Command;

/**
 * Operational entry point for the evidence_events ingestion adapters (Phase 6).
 * Run per-student, e.g. from a scheduled job once real batch triggers are
 * decided — deliberately NOT wired into app/Console/Kernel's schedule here,
 * since how/when to batch this across the whole student roster is a product
 * decision this pass didn't make.
 *
 * Runs every registered adapter by default. Each one is independent and writes
 * its own source_type, so a student with assessment history but no adaptive
 * learning (or the reverse) simply gets rows from the adapter that has
 * something to say.
 */
class IngestAssessmentEvidenceCommand extends Command
{
    protected $signature = 'cai:ingest-evidence {studentId} {academicYear} {--source= : Limit to one source_type (assessment|pal)}';

    protected $description = 'Ingest evidence_events rows for one student from every evidence source';

    public function handle(AssessmentEvidenceAdapter $assessment, PalAdaptiveEvidenceAdapter $pal): int
    {
        $studentId = (string) $this->argument('studentId');
        $academicYear = (string) $this->argument('academicYear');
        $only = $this->option('source');

        $adapters = array_filter([
            'assessment' => $only === null || $only === 'assessment' ? $assessment : null,
            'pal' => $only === null || $only === 'pal' ? $pal : null,
        ]);

        if ($adapters === []) {
            $this->error("Unknown source '{$only}'. Valid sources: assessment, pal.");

            return self::FAILURE;
        }

        $total = 0;
        foreach ($adapters as $source => $adapter) {
            $written = $adapter->ingest($studentId, $academicYear);
            $total += count($written);

            $this->line(empty($written)
                ? "  {$source}: nothing new (no data, or below this source's minimum threshold per subject)."
                : '  ' . $source . ': ' . count($written) . ' evidence_events row(s) written.');
        }

        $this->info("{$total} evidence_events row(s) written in total for student {$studentId}.");

        return self::SUCCESS;
    }
}
