<?php

namespace App\Console\Commands;

use App\Services\Remap\ConceptAssigner;
use App\Services\Remap\RemapAuditService;
use App\Services\Remap\RemapGuards;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Assigns concept_id, and the topic that concept belongs to, to std-10
 * questions.
 *
 * Two distinct operations:
 *
 *   1. concept assignment -- a ranked choice among the concepts of the
 *      question's OWN chapter, skipped when the evidence is too thin
 *   2. topic derivation -- pure lookup of lms_concept.topic_id for a
 *      question that already has a concept. No inference at all, so it
 *      runs for every question with a concept, including the Science
 *      set that was already correctly mapped
 *
 * Classroom resources and teacher resources are never touched here:
 * they are chapter-only by design.
 */
class RemapAssignConcepts extends Command
{
    protected $signature = 'remap:assign-concepts
                            {--run= : run id for the audit trail}
                            {--subject= : restrict to one subject}
                            {--apply : actually write (default is dry-run)}
                            {--topics-only : only derive topic_id from existing concepts}';

    protected $description = 'Assign concepts (and their topics) to std-10 questions';

    public function handle(): int
    {
        $runId = $this->option('run');

        if (!$runId) {
            $this->error('--run is required');
            return self::FAILURE;
        }

        $scope    = config('remap.scope');
        $live     = (bool) $this->option('apply');
        $assigner = new ConceptAssigner($scope);
        $guards   = new RemapGuards($scope);
        $audit    = new RemapAuditService($runId);

        $this->line($live ? 'MODE: APPLY (writing)' : 'MODE: dry-run (no writes)');
        $this->newLine();

        $stats = ['assigned' => 0, 'skipped_thin' => 0, 'no_candidates' => 0, 'topics' => 0, 'conflicts' => 0];

        if (!$this->option('topics-only')) {
            $stats = $this->assignConcepts($assigner, $guards, $audit, $scope, $live, $stats);
        }

        $stats['topics'] = $this->deriveTopics($audit, $scope, $live);

        $this->newLine();
        $this->table(
            ['concepts assigned', 'skipped (thin evidence)', 'chapters w/o concepts', 'topics derived', 'write conflicts'],
            [[$stats['assigned'], $stats['skipped_thin'], $stats['no_candidates'], $stats['topics'], $stats['conflicts']]]
        );

        if (!$live) {
            $this->warn('dry-run only. re-run with --apply to write.');
        }

        return self::SUCCESS;
    }

    /**
     * Rank the chapter's own concepts for each question that lacks one.
     */
    private function assignConcepts(ConceptAssigner $assigner, RemapGuards $guards, RemapAuditService $audit, array $scope, bool $live, array $stats): array
    {
        $query = DB::table('lms_question_master as q')
            ->join('chapter_master as cm', 'cm.id', '=', 'q.chapter_id')
            ->where('q.sub_institute_id', $scope['sub_institute_id'])
            ->where('q.standard_id', $scope['standard_id'])
            ->whereNull('q.deleted_at')
            ->where(fn ($w) => $w->whereNull('q.concept_id')->orWhere('q.concept_id', 0))
            ->when($this->option('subject'), fn ($q, $s) => $q->where('q.subject_id', $s))
            ->select([
                'q.id', 'q.chapter_id', 'q.subject_id', 'q.standard_id', 'q.sub_institute_id',
                'q.question_title', 'q.answer', 'q.concept', 'q.subconcept', 'q.concept_id', 'q.topic_id',
            ]);

        $total = (clone $query)->count();
        $bar   = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('q.id')->chunkById((int) config('remap.batch.chunk', 500), function ($rows) use (
            $assigner, $guards, $audit, $live, &$stats, $bar
        ) {
            $pending = [];

            foreach ($rows as $q) {
                $bar->advance();

                $match = $assigner->match($q, (int) $q->chapter_id);

                if ($match === null) {
                    $candidates = $assigner->candidates((int) $q->chapter_id);
                    empty($candidates['rows']) ? $stats['no_candidates']++ : $stats['skipped_thin']++;
                    continue;
                }

                $concept = $match['concept'];

                // The invariant that currently holds at 100% for the
                // already-correct Science set, re-asserted per row.
                $guards->assertConceptMatchesQuestion($concept, $q);

                $pending[] = ['q' => $q, 'm' => $match, 'c' => $concept];
            }

            if (!$live) {
                $stats['assigned'] += count($pending);
                return true;
            }

            DB::transaction(function () use ($pending, $audit, &$stats) {
                foreach ($pending as $p) {
                    $ok = $audit->applyColumn(
                        'lms_question_master', (int) $p['q']->id, 'concept_id',
                        $p['q']->concept_id, (int) $p['c']->id, 'assign_concept',
                        [
                            'confidence' => round(min(0.95, 0.55 + 0.4 * $p['m']['margin']), 3),
                            'method'     => 'lexical_chapter_scoped',
                            'evidence'   => ['terms' => $p['m']['terms'], 'concept' => $p['c']->name],
                        ]
                    );

                    $ok ? $stats['assigned']++ : $stats['conflicts']++;
                }
            });

            return true;
        }, 'q.id', 'id');

        $bar->finish();
        $this->newLine();

        return $stats;
    }

    /**
     * Copy each question's topic from the concept it already carries.
     *
     * Pure derivation: the topic is read from lms_concept.topic_id, so
     * nothing is inferred and this is safe to run over the whole std-10
     * set, including the Science questions that were already correct.
     */
    private function deriveTopics(RemapAuditService $audit, array $scope, bool $live): int
    {
        $query = DB::table('lms_question_master as q')
            ->join('lms_concept as k', 'k.id', '=', 'q.concept_id')
            ->where('q.sub_institute_id', $scope['sub_institute_id'])
            ->where('q.standard_id', $scope['standard_id'])
            ->whereNull('q.deleted_at')
            ->whereNotNull('k.topic_id')
            ->where('k.topic_id', '>', 0)
            // Only where the topic is missing or disagrees with the concept.
            ->where(fn ($w) => $w->whereNull('q.topic_id')
                                 ->orWhere('q.topic_id', 0)
                                 ->orWhereColumn('q.topic_id', '<>', 'k.topic_id'))
            // The concept must belong to the question's own chapter.
            ->whereColumn('k.chapter_id', 'q.chapter_id')
            ->when($this->option('subject'), fn ($q, $s) => $q->where('q.subject_id', $s))
            ->select(['q.id', 'q.topic_id', 'k.topic_id as new_topic_id', 'k.id as concept_id', 'k.name as concept_name']);

        $count = 0;
        $total = (clone $query)->count();

        $this->line("topic derivation: {$total} question(s) to update");

        if ($total === 0) {
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('q.id')->chunkById((int) config('remap.batch.chunk', 500), function ($rows) use ($audit, $live, &$count, $bar) {
            if (!$live) {
                $count += $rows->count();
                $bar->advance($rows->count());
                return true;
            }

            DB::transaction(function () use ($rows, $audit, &$count, $bar) {
                foreach ($rows as $r) {
                    $bar->advance();

                    if ($audit->applyColumn(
                        'lms_question_master', (int) $r->id, 'topic_id',
                        $r->topic_id, (int) $r->new_topic_id, 'assign_topic',
                        [
                            'method'   => 'derived_from_concept',
                            'evidence' => ['concept_id' => (int) $r->concept_id, 'concept' => $r->concept_name],
                        ]
                    )) {
                        $count++;
                    }
                }
            });

            return true;
        }, 'q.id', 'id');

        $bar->finish();
        $this->newLine();

        return $count;
    }
}
