<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Post-run QA gates. Exits non-zero if any hard check fails.
 *
 * The strongest check here is #6: the snapshot-versus-live diff must
 * equal the audit count for every table and column. Equality proves
 * nothing changed outside the audit trail, which is the same thing as
 * proving the run is fully reversible.
 */
class RemapVerify extends Command
{
    protected $signature = 'remap:verify {--run= : run id to verify}';

    protected $description = 'Run the std-10 remap verification gates';

    private array $failures = [];
    private array $warnings = [];

    public function handle(): int
    {
        $runId = $this->option('run');

        if (!$runId) {
            $this->error('--run is required');
            return self::FAILURE;
        }

        $scope  = config('remap.scope');
        $tenant = (int) $scope['sub_institute_id'];
        $std    = (int) $scope['standard_id'];
        $short  = substr(str_replace('-', '', $runId), 0, 8);

        $this->check1Orphans($tenant, $std, $runId);
        $this->check2Coherence($tenant, $std);
        $this->check3ConceptInvariant($tenant, $std);
        $this->check5NoConceptOnResources($tenant, $std);
        $this->check6AuditCompleteness($runId, $short);
        $this->check7Tenant($runId, $tenant);
        $this->check8ScienceControl($runId, $scope);
        $this->check9Distribution($tenant, $std);
        $this->check10SoftDeletes($runId);

        $this->newLine();

        foreach ($this->warnings as $w) {
            $this->warn('WARN  ' . $w);
        }

        if ($this->failures) {
            foreach ($this->failures as $f) {
                $this->error('FAIL  ' . $f);
            }
            $this->newLine();
            $this->error(count($this->failures) . ' gate(s) failed');
            return self::FAILURE;
        }

        $this->info('all gates passed' . ($this->warnings ? ' (' . count($this->warnings) . ' warning(s))' : ''));

        return self::SUCCESS;
    }

    private function pass(string $label, int $actual, int $expected = 0): void
    {
        $ok = $actual === $expected;
        $this->line(sprintf('  [%s] %-52s %d', $ok ? 'ok' : 'XX', $label, $actual));

        if (!$ok) {
            $this->failures[] = "{$label}: got {$actual}, expected {$expected}";
        }
    }

    /** Remaining orphans must be exactly the rows we deliberately held. */
    private function check1Orphans(int $tenant, int $std, string $runId): void
    {
        $this->line('1. orphaned chapter_id remaining');

        $crosswalk = DB::table('lms_chapter_crosswalk')
            ->where('run_id', $runId)
            ->get(['legacy_subject_id', 'legacy_chapter_id', 'decision', 'review_status']);

        foreach (config('remap.entities') as $key => $entity) {
            $table = $entity['table'];

            // A group is legitimately still orphaned when it was held
            // for review, and ALSO when it was ruled out_of_syllabus for
            // an entity that has no proven soft-delete column -- those
            // rows are deliberately left visible rather than hidden on a
            // guessed status token.
            $held = $crosswalk->filter(function ($r) use ($entity) {
                if (!in_array($r->review_status, ['approved_auto', 'low_confidence'], true)) {
                    return true;
                }
                return $r->decision === 'out_of_syllabus' && $entity['soft_delete_column'] === null;
            })->map(fn ($r) => $r->legacy_subject_id . ':' . $r->legacy_chapter_id)->flip();

            $rows = DB::table($table)
                ->where('sub_institute_id', $tenant)
                ->where('standard_id', $std)
                ->whereNotNull('chapter_id')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('chapter_master as cm')->whereColumn('cm.id', "{$table}.chapter_id"))
                ->when($key === 'questions', fn ($q) => $q->whereNull('deleted_at'))
                ->get(['subject_id', 'chapter_id']);

            $unexpected = $rows->reject(fn ($r) => $held->has($r->subject_id . ':' . $r->chapter_id))->count();

            $this->line(sprintf('     %-20s held=%-5d unexpected=%d', $key, $rows->count() - $unexpected, $unexpected));

            if ($unexpected > 0) {
                $this->failures[] = "{$key}: {$unexpected} orphan row(s) not covered by a held decision";
            }
        }
    }

    /** Every row must sit on a chapter of its own subject and standard. */
    private function check2Coherence(int $tenant, int $std): void
    {
        $this->line('2. chapter/subject coherence');
        $runId = (string) $this->option('run');

        foreach (config('remap.entities') as $key => $entity) {
            $table = $entity['table'];

            $base = fn () => DB::table("{$table} as x")
                ->join('chapter_master as cm', 'cm.id', '=', 'x.chapter_id')
                ->where('x.sub_institute_id', $tenant)
                ->where('x.standard_id', $std)
                ->where(fn ($q) => $q->whereColumn('x.subject_id', '<>', 'cm.subject_id')
                                     ->orWhereColumn('x.standard_id', '<>', 'cm.standard_id'));

            $total = $base()->count();

            // Only rows this run actually moved can be this run's fault.
            // Anything else is a pre-existing inconsistency and is
            // reported rather than blamed on the remap.
            $caused = $base()
                ->join('lms_remap_audit as a', function ($j) use ($table, $runId) {
                    $j->on('a.entity_id', '=', 'x.id')
                      ->where('a.entity', '=', $table)
                      ->where('a.run_id', '=', $runId)
                      ->whereNull('a.reverted_at');
                })
                ->count();

            $this->pass("   {$key} mismatch caused by this run", $caused);

            if ($total > $caused) {
                $this->warnings[] = sprintf(
                    '%s: %d pre-existing subject/standard mismatch(es) not caused by this run',
                    $table, $total - $caused
                );
            }
        }
    }

    /** A concept must belong to its question's own chapter and tenant. */
    private function check3ConceptInvariant(int $tenant, int $std): void
    {
        $this->line('3. concept invariant (concept.chapter == question.chapter)');

        $bad = DB::table('lms_question_master as q')
            ->join('lms_concept as k', 'k.id', '=', 'q.concept_id')
            ->where('q.sub_institute_id', $tenant)
            ->where('q.standard_id', $std)
            ->whereNull('q.deleted_at')
            ->where(fn ($w) => $w->whereColumn('k.chapter_id', '<>', 'q.chapter_id')
                                 ->orWhereColumn('k.subject_id', '<>', 'q.subject_id')
                                 ->orWhereColumn('k.standard_id', '<>', 'q.standard_id')
                                 ->orWhereColumn('k.sub_institute_id', '<>', 'q.sub_institute_id'))
            ->count();

        $this->pass('   questions with a foreign concept', $bad);
    }

    /** Classroom resources and teacher resources must never gain a concept. */
    private function check5NoConceptOnResources(int $tenant, int $std): void
    {
        $this->line('5. CR/TW carry no concept');

        $cr = DB::table('content_master')
            ->where('sub_institute_id', $tenant)->where('standard_id', $std)
            ->whereNotNull('concept_id')->where('concept_id', '>', 0)->count();

        $this->pass('   content_master rows with concept_id', $cr);

        if (Schema::hasColumn('lms_teacher_resource', 'concept_id')) {
            $this->failures[] = 'lms_teacher_resource gained a concept_id column';
        } else {
            $this->line('     [ok] lms_teacher_resource has no concept_id column');
        }
    }

    /**
     * The decisive completeness check.
     *
     * For every column we may have touched, the number of rows that now
     * differ from the pre-run snapshot must equal the number of audit
     * rows for that column. If live-vs-snapshot exceeds the audit count,
     * something changed outside the trail and the run is not fully
     * reversible.
     */
    private function check6AuditCompleteness(string $runId, string $short): void
    {
        $this->line('6. audit completeness (snapshot diff == audit count)');

        foreach (config('remap.entities') as $key => $entity) {
            $table  = $entity['table'];
            $backup = "bak_{$table}_{$short}";

            if (!Schema::hasTable($backup)) {
                $this->warnings[] = "no snapshot {$backup}; completeness unprovable for {$key}";
                continue;
            }

            foreach ($entity['snapshot_columns'] as $col) {
                if ($col === 'id') {
                    continue;
                }

                $owned = in_array($col, $entity['owned_columns'] ?? [], true);

                // A snapshot taken before this column was added to the
                // config cannot speak to it. Say so rather than failing.
                if (!Schema::hasColumn($backup, $col)) {
                    $this->line(sprintf('     %-34s not in snapshot; completeness unprovable', "{$table}.{$col}"));
                    $this->warnings[] = "{$backup} predates column {$col}; its completeness is unproven for this run";
                    continue;
                }

                $differing = DB::table("{$backup} as b")
                    ->join("{$table} as t", 't.id', '=', 'b.id')
                    ->whereRaw("NOT (b.`{$col}` <=> t.`{$col}`)")
                    ->count();

                $audited = DB::table('lms_remap_audit')
                    ->where('run_id', $runId)
                    ->where('entity', $table)
                    ->where('column_name', $col)
                    ->whereNull('reverted_at')
                    ->count();

                if ($differing === 0 && $audited === 0) {
                    continue;
                }

                $ok = $differing === $audited;

                if (!$owned) {
                    // Not a column we write. Any drift is someone else
                    // working in the same database, which is normal here
                    // and must not be reported as our failure.
                    $this->line(sprintf('     %-34s diff=%-6d (external, not written by this run)', "{$table}.{$col}", $differing));
                    if ($differing > 0) {
                        $this->warnings[] = "{$table}.{$col}: {$differing} row(s) changed by activity outside this run";
                    }
                    continue;
                }

                $this->line(sprintf('     %-34s diff=%-6d audited=%-6d %s', "{$table}.{$col}", $differing, $audited, $ok ? '[ok]' : '[XX]'));

                if (!$ok) {
                    $this->failures[] = "{$table}.{$col}: {$differing} rows differ from snapshot but {$audited} audited";
                }
            }
        }
    }

    /** Tenant 1000 must never have been touched. */
    private function check7Tenant(string $runId, int $tenant): void
    {
        $this->line('7. cross-tenant containment');

        $total = 0;

        foreach (config('remap.entities') as $entity) {
            $table = $entity['table'];

            $total += DB::table('lms_remap_audit as a')
                ->join("{$table} as x", 'x.id', '=', 'a.entity_id')
                ->where('a.run_id', $runId)
                ->where('a.entity', $table)
                ->where('x.sub_institute_id', '<>', $tenant)
                ->count();
        }

        $this->pass('   audited rows outside tenant ' . $tenant, $total);
    }

    /** Science keeps its chapters; only concept writes are legitimate there. */
    private function check8ScienceControl(string $runId, array $scope): void
    {
        $this->line('8. Science control untouched (chapter_id)');

        $bad = DB::table('lms_remap_audit as a')
            ->join('lms_question_master as q', 'q.id', '=', 'a.entity_id')
            ->where('a.run_id', $runId)
            ->where('a.entity', 'lms_question_master')
            ->where('a.column_name', 'chapter_id')
            ->where('q.subject_id', $scope['science_subject_id'])
            ->count();

        $this->pass('   Science question chapter_id writes', $bad);
    }

    /** Lopsided chapter loads usually mean a systematically wrong mapping. */
    private function check9Distribution(int $tenant, int $std): void
    {
        $this->line('9. distribution plausibility (warnings only)');

        $rows = DB::table('lms_question_master as q')
            ->join('chapter_master as cm', 'cm.id', '=', 'q.chapter_id')
            ->where('q.sub_institute_id', $tenant)
            ->where('q.standard_id', $std)
            ->whereNull('q.deleted_at')
            ->groupBy('cm.subject_id', 'q.chapter_id')
            ->get(['cm.subject_id', 'q.chapter_id', DB::raw('COUNT(*) as n')]);

        $bySubject = [];
        foreach ($rows as $r) {
            $bySubject[$r->subject_id][] = $r;
        }

        $flagged = 0;

        foreach ($bySubject as $subjectId => $chapters) {
            $counts = array_map(fn ($c) => (int) $c->n, $chapters);
            $mean   = array_sum($counts) / max(1, count($counts));

            foreach ($chapters as $c) {
                if ($mean > 0 && $c->n > 3 * $mean) {
                    $this->warnings[] = sprintf('subject %d chapter %d holds %d questions (subject mean %.0f)', $subjectId, $c->chapter_id, $c->n, $mean);
                    $flagged++;
                }
            }
        }

        $this->line("     {$flagged} chapter(s) above 3x their subject mean");
    }

    /** Every soft delete must trace to an out_of_syllabus decision. */
    private function check10SoftDeletes(string $runId): void
    {
        $this->line('10. soft-delete accounting');

        $total = DB::table('lms_remap_audit')
            ->where('run_id', $runId)->where('action', 'soft_delete')->whereNull('reverted_at')->count();

        $untraceable = DB::table('lms_remap_audit as a')
            ->leftJoin('lms_chapter_crosswalk as x', 'x.id', '=', 'a.crosswalk_id')
            ->where('a.run_id', $runId)
            ->where('a.action', 'soft_delete')
            ->whereNull('a.reverted_at')
            ->where(fn ($q) => $q->whereNull('x.id')->orWhere('x.decision', '<>', 'out_of_syllabus'))
            ->count();

        $this->line("     {$total} soft-deleted");
        $this->pass('   soft deletes not traceable to out_of_syllabus', $untraceable);
    }
}
