<?php

namespace App\Console\Commands;

use App\Services\Remap\RemapAuditService;
use Illuminate\Console\Command;

/**
 * Reverts audited mutations.
 *
 * Because lms_remap_audit records one row per COLUMN changed, rollback
 * is a single generic statement regardless of which table is involved.
 * A value that someone changed after the run is reported and left
 * alone, never forced back -- a rollback that overwrites later human
 * edits would be worse than no rollback at all.
 *
 * The selector that matters operationally is --review-status=
 * low_confidence, which undoes exactly the weakly-evidenced tranche
 * while leaving the confident mappings in place.
 */
class RemapRollback extends Command
{
    protected $signature = 'remap:rollback
                            {--run= : run id to revert}
                            {--entity= : lms_question_master|content_master|lms_teacher_resource}
                            {--crosswalk= : a single crosswalk row id}
                            {--legacy-chapter= : all rows from one legacy chapter}
                            {--subject= : all rows from one legacy subject}
                            {--review-status= : e.g. low_confidence}
                            {--apply : actually revert (default is dry-run)}';

    protected $description = 'Revert mutations recorded in lms_remap_audit';

    public function handle(): int
    {
        if (!$this->option('run') && !$this->option('crosswalk')) {
            $this->error('give at least --run or --crosswalk');
            return self::FAILURE;
        }

        $filters = array_filter([
            'run_id'            => $this->option('run'),
            'entity'            => $this->option('entity'),
            'crosswalk_id'      => $this->option('crosswalk'),
            'legacy_chapter_id' => $this->option('legacy-chapter'),
            'subject_id'        => $this->option('subject'),
            'review_status'     => $this->option('review-status'),
        ]);

        $live   = (bool) $this->option('apply');
        $audit  = new RemapAuditService((string) $this->option('run'));
        $result = $audit->rollback($filters, $live);

        $this->line($live ? 'MODE: APPLY (reverting)' : 'MODE: dry-run (no writes)');

        foreach (array_slice($result['details'], 0, 15) as $line) {
            $this->line('  ' . $line);
        }

        if (count($result['details']) > 15) {
            $this->line('  ... ' . (count($result['details']) - 15) . ' more');
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d mutation(s); %d skipped because the value had changed since the run',
            $live ? 'reverted' : 'would revert',
            $result['reverted'],
            $result['skipped']
        ));

        if (!$live) {
            $this->warn('dry-run only. re-run with --apply to revert.');
        }

        return self::SUCCESS;
    }
}
