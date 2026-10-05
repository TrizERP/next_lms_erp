<?php

namespace App\Console\Commands\Concept;

use App\Models\LMS\ConceptPrerequisite;
use App\Services\Concept\GradeMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Load an authored prerequisite file into `concept_prerequisite`.
 *
 * THE FILE REFERENCES REAL CONCEPT IDS
 * Entries name `lms_concept.id` directly, with the concept name in a trailing
 * comment for the human reading the file. Concept names on this estate are bespoke
 * to the extraction run - "Why Five Handspans Disagree", "Hit-and-trial balancing
 * method" - so keying authored work on them would neither resolve reliably nor
 * survive a re-extraction. Ids remove the matching problem entirely.
 *
 * AN ID THAT DOES NOT EXIST IS AN ERROR, NOT A SKIP
 * Every unknown id is reported with the entry that named it, and the import exits
 * non-zero. A map that looks complete because the misses were dropped is worse than
 * one that admits the gap.
 *
 * GRADES ARE FILLED IN HERE, NOT AUTHORED
 * `concept_grade` and `prerequisite_grade` are resolved from each concept's standard
 * at import time, so the author cannot get them wrong and they cannot drift from the
 * concept they describe. They exist on the row only to make the grade-order check one
 * WHERE clause instead of two joins.
 *
 * IDEMPOTENT
 * Upserts on (concept_id, prerequisite_id, sub_institute_id), so re-running after a
 * corrected reason updates in place rather than duplicating.
 */
class PrereqImportCommand extends Command
{
    protected $signature = 'concept:prereq-import
        {--file= : file under database/data/concept_prerequisites/, or an absolute path}
        {--source-tenant=1 : sub_institute_id holding the concept catalogue}
        {--tenant=0 : sub_institute_id to write the links under (0 = shared)}
        {--status=approved : draft|approved}
        {--dry-run : validate and report, write nothing}';

    protected $description = 'Import an authored concept prerequisite file';

    /** Shorter than this is a label, not a reason. */
    private const MIN_REASON = 40;

    public function handle(GradeMap $grades): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tenant = (int) $this->option('tenant');
        $sourceTenant = (int) $this->option('source-tenant');

        $path = $this->resolvePath();

        if ($path === null) {
            return self::FAILURE;
        }

        $entries = require $path;

        $this->info('Concept prerequisites - import');
        $this->line("source: {$path}");
        $this->line("target: sub_institute_id={$tenant}".($dryRun ? '   [DRY RUN]' : ''));
        $this->line(str_repeat('-', 72));

        // One lookup for every id the file mentions, so a bad id is caught before
        // anything is written rather than on the row that happens to hit it.
        $ids = [];

        foreach ($entries as $entry) {
            foreach (['concept', 'prerequisite'] as $end) {
                if (isset($entry[$end])) {
                    $ids[(int) $entry[$end]] = true;
                }
            }
        }

        $known = DB::table('lms_concept')
            ->whereIn('id', array_keys($ids))
            ->where('sub_institute_id', $sourceTenant)
            ->pluck('name', 'id')
            ->all();

        $gradeOf = $grades->forConcepts(array_keys($ids), $sourceTenant);

        $written = 0;
        $rejected = [];

        foreach ($entries as $i => $entry) {
            $problem = $this->reject($entry, $i, $known, $gradeOf);

            if ($problem !== null) {
                $rejected[] = $problem;
                continue;
            }

            $conceptId = (int) $entry['concept'];
            $prerequisiteId = (int) $entry['prerequisite'];

            if (! $dryRun) {
                ConceptPrerequisite::updateOrCreate(
                    [
                        'concept_id' => $conceptId,
                        'prerequisite_id' => $prerequisiteId,
                        'sub_institute_id' => $tenant,
                    ],
                    [
                        'link_type' => $entry['type'] ?? ConceptPrerequisite::REQUIRES,
                        'is_gate' => (bool) ($entry['gate'] ?? false),
                        'concept_grade' => $gradeOf[$conceptId] ?? null,
                        'prerequisite_grade' => $gradeOf[$prerequisiteId] ?? null,
                        'reason' => mb_substr(trim($entry['reason']), 0, 500),
                        'source_ref' => mb_substr(trim($entry['source'] ?? 'NCERT'), 0, 191),
                        'origin' => $entry['origin'] ?? 'expert',
                        'status' => $this->option('status'),
                    ]
                );
            }

            $written++;
        }

        $this->line('entries  : '.count($entries));
        $this->line('written  : '.$written.($dryRun ? ' (would be)' : ''));
        $this->line('rejected : '.count($rejected));

        if ($rejected !== []) {
            $this->line('');
            $this->warn('REJECTED - these entries were not written:');
            $this->table(['entry', 'why'], array_slice($rejected, 0, 40));
        }

        if ($dryRun) {
            $this->line('');
            $this->comment('Dry run - nothing was written.');
        }

        return $rejected === [] ? self::SUCCESS : self::INVALID;
    }

    private function resolvePath(): ?string
    {
        $file = $this->option('file');

        if (! $file) {
            $this->error('--file is required.');

            return null;
        }

        $path = file_exists($file) ? $file : database_path('data/concept_prerequisites/'.$file);

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return null;
        }

        return $path;
    }

    /**
     * Why this entry must not be written, or null when it is sound.
     *
     * @return array{0:string,1:string}|null
     */
    private function reject(array $entry, int $i, array $known, array $gradeOf): ?array
    {
        $label = "#{$i}";

        foreach (['concept', 'prerequisite'] as $end) {
            if (! isset($entry[$end])) {
                return [$label, "no {$end} id"];
            }

            if (! isset($known[(int) $entry[$end]])) {
                return [$label, "{$end} id ".$entry[$end].' is not a concept on this estate'];
            }
        }

        $conceptId = (int) $entry['concept'];
        $prerequisiteId = (int) $entry['prerequisite'];

        if ($conceptId === $prerequisiteId) {
            return [$label, 'both ends are the same concept'];
        }

        $type = $entry['type'] ?? ConceptPrerequisite::REQUIRES;

        if (! in_array($type, ConceptPrerequisite::LINK_TYPES, true)) {
            return [$label, "unknown link type '{$type}'"];
        }

        $reason = trim((string) ($entry['reason'] ?? ''));

        if ($reason === '') {
            return [$label, 'no reason - a link nobody can argue with cannot be reviewed'];
        }

        if (mb_strlen($reason) < self::MIN_REASON) {
            return [$label, 'reason is '.mb_strlen($reason).' chars; a reason, not a label'];
        }

        // Caught here as well as by the check command, because writing a backwards
        // link and finding out later means every downstream answer was wrong in between.
        $cg = $gradeOf[$conceptId] ?? null;
        $pg = $gradeOf[$prerequisiteId] ?? null;

        if ($cg !== null && $pg !== null && $pg > $cg) {
            return [$label, "class {$pg} cannot be a prerequisite of class {$cg}"];
        }

        return null;
    }
}
