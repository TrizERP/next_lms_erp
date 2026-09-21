<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Human-readable account of a run: what moved, what did not, and why.
 *
 * The "held for review" section is the important half. Those rows are
 * the ones the pipeline deliberately refused to guess at, and each is
 * listed with the reason so someone can act on it.
 */
class RemapReport extends Command
{
    protected $signature = 'remap:report
                            {--run= : run id to report on}
                            {--out= : also write the report to this path}';

    protected $description = 'Summarise a std-10 remap run';

    public function handle(): int
    {
        $runId = $this->option('run');

        if (!$runId) {
            $this->error('--run is required');
            return self::FAILURE;
        }

        $scope = config('remap.scope');
        $lines = [];

        $add = function (string $line = '') use (&$lines) {
            $lines[] = $line;
            $this->line($line);
        };

        $add('STD-10 CHAPTER / CONCEPT REMAP');
        $add('run: ' . $runId);
        $add('scope: standard_id=' . $scope['standard_id'] . ', sub_institute_id=' . $scope['sub_institute_id']);
        $add(str_repeat('=', 78));
        $add();

        // --- what changed -------------------------------------------
        $add('MUTATIONS APPLIED');
        $audit = DB::table('lms_remap_audit')
            ->where('run_id', $runId)->whereNull('reverted_at')
            ->groupBy('entity', 'column_name', 'action')
            ->orderBy('entity')->orderBy('column_name')
            ->get(['entity', 'column_name', 'action', DB::raw('COUNT(*) as n')]);

        foreach ($audit as $a) {
            $add(sprintf('  %-24s %-12s %-16s %d', $a->entity, $a->column_name, $a->action, $a->n));
        }

        if ($audit->isEmpty()) {
            $add('  (none)');
        }

        // --- current coverage ----------------------------------------
        $add();
        $add('COVERAGE NOW');

        foreach (config('remap.entities') as $key => $entity) {
            $table = $entity['table'];

            $q = DB::table("{$table} as x")
                ->where('x.sub_institute_id', $scope['sub_institute_id'])
                ->where('x.standard_id', $scope['standard_id']);

            if ($key === 'questions') {
                $q->whereNull('x.deleted_at');
            }

            $total = (clone $q)->count();
            $valid = (clone $q)->whereExists(fn ($s) => $s->select(DB::raw(1))->from('chapter_master as cm')->whereColumn('cm.id', 'x.chapter_id'))->count();

            $add(sprintf('  %-20s %5d rows, %5d on a live chapter (%s)', $key, $total, $valid, $total ? round(100 * $valid / $total) . '%' : '-'));
        }

        $concepts = DB::table('lms_question_master')
            ->where('sub_institute_id', $scope['sub_institute_id'])
            ->where('standard_id', $scope['standard_id'])
            ->whereNull('deleted_at');

        $add(sprintf(
            '  %-20s %5d with a concept, %5d with a topic',
            'questions',
            (clone $concepts)->whereNotNull('concept_id')->where('concept_id', '>', 0)->count(),
            (clone $concepts)->whereNotNull('topic_id')->where('topic_id', '>', 0)->count()
        ));

        $this->heldSection($runId, $add);
        $this->rollbackSection($runId, $add);

        if ($path = $this->option('out')) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, implode("\n", $lines) . "\n");
            $this->newLine();
            $this->info("written: {$path}");
        }

        return self::SUCCESS;
    }

    /**
     * Everything the pipeline refused to decide, with the reason and
     * the row counts, so it can be acted on rather than forgotten.
     */
    private function heldSection(string $runId, callable $add): void
    {
        $add();
        $add('HELD FOR REVIEW  (nothing was written for these)');
        $add(str_repeat('-', 78));

        $rows = DB::table('lms_chapter_crosswalk')
            ->where('run_id', $runId)
            ->whereIn('decision', ['needs_review', 'out_of_syllabus'])
            ->orderBy('decision')
            ->orderBy('legacy_subject_id')
            ->orderBy('legacy_chapter_id')
            ->get();

        $subjects = DB::table('subject')->pluck('subject_name', 'id');
        $current  = null;

        foreach ($rows as $r) {
            if ($current !== $r->decision) {
                $current = $r->decision;
                $add();
                $add('  [' . strtoupper(str_replace('_', ' ', $current)) . ']');
            }

            $counts = json_decode($r->row_counts_json ?? '{}', true) ?: [];
            $why    = json_decode($r->evidence_json ?? '{}', true)['why'] ?? '';

            $add(sprintf(
                '  %-22s legacy %-6d  q=%-4d c=%-4d t=%-3d',
                mb_substr($subjects[$r->legacy_subject_id] ?? ('subject ' . $r->legacy_subject_id), 0, 22),
                $r->legacy_chapter_id,
                $counts['questions'] ?? 0,
                $counts['content'] ?? 0,
                $counts['teacher'] ?? 0
            ));
            $add('      ' . wordwrap(mb_substr($why, 0, 220), 68, "\n      ", true));
        }

        if ($rows->isEmpty()) {
            $add('  (none)');
        }

        // Questions left without a concept are a separate, finer gap:
        // the chapter is right, only the concept could not be chosen.
        $noConcept = DB::table('lms_question_master as q')
            ->join('chapter_master as cm', 'cm.id', '=', 'q.chapter_id')
            ->where('q.sub_institute_id', config('remap.scope.sub_institute_id'))
            ->where('q.standard_id', config('remap.scope.standard_id'))
            ->whereNull('q.deleted_at')
            ->where(fn ($w) => $w->whereNull('q.concept_id')->orWhere('q.concept_id', 0))
            ->groupBy('q.subject_id')
            ->get(['q.subject_id', DB::raw('COUNT(*) as n')]);

        if ($noConcept->isNotEmpty()) {
            $add();
            $add('  [ON A VALID CHAPTER BUT NO CONCEPT CHOSEN]');
            $add('  Evidence was too thin to pick one of the chapter\'s concepts.');

            foreach ($noConcept as $r) {
                $add(sprintf('  %-22s %d question(s)', mb_substr($subjects[$r->subject_id] ?? '?', 0, 22), $r->n));
            }
        }
    }

    private function rollbackSection(string $runId, callable $add): void
    {
        $low = DB::table('lms_chapter_crosswalk')
            ->where('run_id', $runId)->where('review_status', 'low_confidence')->count();

        $add();
        $add('REVERSING THIS RUN');
        $add(str_repeat('-', 78));
        $add('  everything:            php artisan remap:rollback --run=' . $runId . ' --apply');
        $add('  only the weak calls:   php artisan remap:rollback --run=' . $runId . ' --review-status=low_confidence --apply');
        $add('  one legacy chapter:    php artisan remap:rollback --run=' . $runId . ' --legacy-chapter=<id> --apply');
        $add();
        $add("  {$low} decision(s) are banded low_confidence.");
        $add('  A rollback never overwrites a value changed after the run; those are reported instead.');
    }
}
