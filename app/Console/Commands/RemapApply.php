<?php

namespace App\Console\Commands;

use App\Services\Remap\RemapAuditService;
use App\Services\Remap\RemapGuards;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Applies the loaded crosswalk to the three content tables.
 *
 * This command reads chapter targets ONLY from lms_chapter_crosswalk.
 * It contains no matching logic, no heuristics and no fallbacks: if a
 * legacy group has no applicable decision, its rows are left exactly as
 * they are. That separation is what makes "never write a guess" a
 * property of the architecture rather than of careful coding.
 *
 * Dry-run is the default; --apply is required to mutate anything.
 */
class RemapApply extends Command
{
    protected $signature = 'remap:apply
                            {--run= : crosswalk run id to apply}
                            {--entity=all : questions|content|teacher_resource|all}
                            {--subject= : restrict to one legacy subject}
                            {--apply : actually write (default is dry-run)}
                            {--max-writes=100000 : hard cap on column mutations}';

    protected $description = 'Apply the loaded std-10 chapter crosswalk to Q&A, classroom resources and teacher resources';

    private int $writes = 0;

    public function handle(): int
    {
        $runId = $this->option('run');

        if (!$runId) {
            $this->error('--run is required');
            return self::FAILURE;
        }

        $scope   = config('remap.scope');
        $guards  = new RemapGuards($scope);
        $audit   = new RemapAuditService($runId);
        $live    = (bool) $this->option('apply');
        $maxWrites = (int) $this->option('max-writes');

        $problems = $guards->schemaAssertions();
        if ($problems) {
            $this->error('schema assertions failed:');
            foreach ($problems as $p) {
                $this->line('  - ' . $p);
            }
            return self::FAILURE;
        }

        $decisions = DB::table('lms_chapter_crosswalk')
            ->where('run_id', $runId)
            ->when($this->option('subject'), fn ($q, $s) => $q->where('legacy_subject_id', $s))
            ->orderBy('legacy_subject_id')
            ->orderBy('legacy_chapter_id')
            ->get();

        if ($decisions->isEmpty()) {
            $this->error("no crosswalk rows for run {$runId}");
            return self::FAILURE;
        }

        $which = $this->option('entity');
        $entities = config('remap.entities');

        if ($which !== 'all' && !isset($entities[$which])) {
            $this->error("unknown entity '{$which}'");
            return self::FAILURE;
        }

        $selected = $which === 'all' ? $entities : [$which => $entities[$which]];

        $this->line($live ? 'MODE: APPLY (writing)' : 'MODE: dry-run (no writes)');
        $this->newLine();

        $totals = [];

        foreach ($selected as $key => $entity) {
            $totals[$key] = $this->applyEntity($key, $entity, $decisions, $scope, $guards, $audit, $live, $maxWrites);
        }

        $this->newLine();
        $rows = [];
        foreach ($totals as $key => $t) {
            $rows[] = [$key, $t['remapped'], $t['subject_fixed'], $t['soft_deleted'], $t['skipped'], $t['held']];
        }
        $this->table(['entity', 'chapter remapped', 'subject fixed', 'soft deleted', 'skipped', 'held'], $rows);

        if (!$live) {
            $this->warn('dry-run only. re-run with --apply to write.');
        } else {
            DB::table('lms_remap_run')->where('run_id', $runId)->update([
                'counts_json' => json_encode($totals),
                'finished_at' => now(),
                'status'      => 'completed',
            ]);
            $this->info("applied. verify with: php artisan remap:verify --run={$runId}");
        }

        return self::SUCCESS;
    }

    /**
     * Apply every applicable decision to one entity table.
     */
    private function applyEntity(
        string $key,
        array $entity,
        $decisions,
        array $scope,
        RemapGuards $guards,
        RemapAuditService $audit,
        bool $live,
        int $maxWrites
    ): array {
        $table   = $entity['table'];
        $softCol = $entity['soft_delete_column'];
        $class   = $key === 'questions' ? 'questions' : ($key === 'content' ? 'content' : 'teacher');

        $t = ['remapped' => 0, 'subject_fixed' => 0, 'soft_deleted' => 0, 'skipped' => 0, 'held' => 0];

        foreach ($decisions as $d) {
            $applicable = in_array($d->review_status, ['approved_auto', 'low_confidence'], true);

            $base = DB::table($table)
                ->where('sub_institute_id', $scope['sub_institute_id'])
                ->where('standard_id', $scope['standard_id'])
                ->where('subject_id', $d->legacy_subject_id)
                ->where('chapter_id', $d->legacy_chapter_id);

            if ($key === 'questions') {
                $base->whereNull('deleted_at');
            }

            if (!$applicable) {
                $t['held'] += (clone $base)->count();
                continue;
            }

            if ($d->decision === 'out_of_syllabus') {
                if ($softCol === null) {
                    // content_master.show_hide and lms_teacher_resource
                    // .status have unproven semantics; guessing a
                    // "hidden" token would be worse than leaving the
                    // rows visible and reporting them.
                    $t['held'] += (clone $base)->count();
                    continue;
                }

                $t['soft_deleted'] += $this->softDelete($base, $table, $softCol, $d, $guards, $audit, $live, $maxWrites);
                continue;
            }

            // Target must still be valid right now, not merely when the
            // decision was authored.
            try {
                $guards->assertApplicable($d);
            } catch (\Throwable $e) {
                $this->error('  guard: ' . $e->getMessage());
                $t['skipped'] += (clone $base)->count();
                continue;
            }

            $moved = $this->remap($base, $table, $d, $scope, $guards, $audit, $live, $maxWrites, $key);

            $t['remapped']      += $moved['chapter'];
            $t['subject_fixed'] += $moved['subject'];
            $t['skipped']       += $moved['skipped'];
        }

        return $t;
    }

    /**
     * Move a legacy group's rows onto the decided chapter, one chunk
     * per transaction so a mutation and its audit row are atomic.
     */
    private function remap($base, string $table, $d, array $scope, RemapGuards $guards, RemapAuditService $audit, bool $live, int $maxWrites, string $key): array
    {
        $out = ['chapter' => 0, 'subject' => 0, 'skipped' => 0];
        $chunk = (int) config('remap.batch.chunk', 500);

        $newChapter = (int) $d->new_chapter_id;
        $newSubject = (int) $d->new_subject_id;
        $confidence = $d->confidence;

        (clone $base)->orderBy('id')->chunkById($chunk, function ($rows) use (
            &$out, $table, $d, $scope, $guards, $audit, $live, $maxWrites,
            $newChapter, $newSubject, $confidence, $key
        ) {
            $ids = $rows->pluck('id')->map(fn ($i) => (int) $i)->all();
            $guards->assertRowsInScope($table, $ids);

            if (!$live) {
                $out['chapter'] += count($ids);
                foreach ($rows as $r) {
                    if ((int) $r->subject_id !== $newSubject) {
                        $out['subject']++;
                    }
                }
                return true;
            }

            DB::transaction(function () use ($rows, &$out, $table, $d, $audit, $newChapter, $newSubject, $confidence, $maxWrites, $key) {
                foreach ($rows as $r) {
                    if ($this->writes >= $maxWrites) {
                        throw new \RuntimeException('max-writes cap reached');
                    }

                    $ok = $audit->applyColumn(
                        $table, (int) $r->id, 'chapter_id',
                        (int) $r->chapter_id, $newChapter, 'remap_chapter',
                        ['crosswalk_id' => $d->id, 'confidence' => $confidence, 'method' => 'crosswalk']
                    );

                    if (!$ok) {
                        $out['skipped']++;
                        continue;
                    }

                    $this->writes++;
                    $out['chapter']++;

                    if ((int) $r->subject_id !== $newSubject) {
                        if ($audit->applyColumn(
                            $table, (int) $r->id, 'subject_id',
                            (int) $r->subject_id, $newSubject, 'remap_subject',
                            ['crosswalk_id' => $d->id, 'confidence' => $confidence, 'method' => 'crosswalk']
                        )) {
                            $this->writes++;
                            $out['subject']++;
                        }
                    }

                    if ($key === 'questions') {
                        $audit->mirrorQuestionFix((int) $r->id, (int) $r->chapter_id, $newChapter, 'remap');
                    }
                }
            });

            return true;
        }, 'id');

        return $out;
    }

    /**
     * Soft-delete a retired group's rows.
     *
     * Only ever reached for entities that have a proven soft-delete
     * column, which today means questions alone (deleted_at). The
     * chapter_id is deliberately left untouched so the original filing
     * survives for anyone reviewing the deletion later.
     */
    private function softDelete($base, string $table, string $softCol, $d, RemapGuards $guards, RemapAuditService $audit, bool $live, int $maxWrites): int
    {
        $count = 0;
        $chunk = (int) config('remap.batch.chunk', 500);

        (clone $base)->orderBy('id')->chunkById($chunk, function ($rows) use (
            &$count, $table, $softCol, $d, $guards, $audit, $live, $maxWrites
        ) {
            $ids = $rows->pluck('id')->map(fn ($i) => (int) $i)->all();
            $guards->assertRowsInScope($table, $ids);

            if (!$live) {
                $count += count($ids);
                return true;
            }

            DB::transaction(function () use ($rows, &$count, $table, $softCol, $d, $audit, $maxWrites) {
                $now = now()->toDateTimeString();

                foreach ($rows as $r) {
                    if ($this->writes >= $maxWrites) {
                        throw new \RuntimeException('max-writes cap reached');
                    }

                    if ($audit->applyColumn(
                        $table, (int) $r->id, $softCol,
                        $r->{$softCol}, $now, 'soft_delete',
                        ['crosswalk_id' => $d->id, 'confidence' => $d->confidence, 'method' => 'crosswalk']
                    )) {
                        $this->writes++;
                        $count++;
                        $audit->mirrorQuestionFix((int) $r->id, (int) $r->chapter_id, null, 'soft_delete');
                    }
                }
            });

            return true;
        }, 'id');

        return $count;
    }
}
