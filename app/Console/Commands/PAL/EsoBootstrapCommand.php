<?php

namespace App\Console\Commands\PAL;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Makes a chapter ESO-ready for one institute, so its students stop seeing the
 * "Adaptive learning content isn't available for your subjects yet" empty state.
 *
 * WHY THIS EXISTS
 * ---------------
 * EsoPolicyService::orderedReadyChapterIds() drops any chapter with no
 * ESO-ready concept, and "ESO-ready" means a `pal_concept_nodes` row visible to
 * the student's tenant (ConceptNode::scopeForTenant accepts only
 * {sub_institute_id, 0}). Every node row in this database belongs to
 * sub_institute_id = 1, so every other institute's dashboard returns
 * `no_content` no matter how complete its chapters, concepts and questions are.
 *
 * Nothing else in this codebase writes `pal_concept_nodes` or
 * `pal_question_metadata.node_id` — tenant 1's rows were loaded out of band.
 * This command is that missing step, made repeatable and auditable.
 *
 * WHAT IT WRITES, AND FROM WHAT
 * -----------------------------
 *   1. One K (Knowledge) node per concept in scope that has none for the
 *      target tenant. Deliberately K-only: splitting a concept into K/A/S
 *      needs a Bloom signal per question, and these questions carry no
 *      metadata at all yet. One node per concept is honest about what the
 *      evidence supports; run pal:tag-content later and add A/S nodes when
 *      there is something to split on.
 *
 *   2. One `pal_question_metadata` row per MCQ on the chapter, pointing at the
 *      node of the concept the question already declares in
 *      lms_question_master.concept_id. Nothing is inferred — a question with no
 *      concept_id, or whose concept_id is not in scope, is skipped and counted.
 *
 * Only MCQs (question_type_id = 1) are mapped, because hydrateQuestion() serves
 * nothing else; mapping the rest would inflate the counts with items the
 * student can never be shown.
 *
 * CONTENT LAW C5
 * --------------
 * `quality_status` is written as 'approved' — the only servable status
 * (config pal_content.servable_statuses) — even though 'approved' is
 * human-only for the AI tagging pipeline (PalVocabulary::isMachineWritable).
 * That is the point of --confirm: an operator is making the approval call for a
 * pilot cohort, deliberately and on the record, not a model doing it silently.
 * Pass --status=draft to stage the rows for human review instead; the dashboard
 * will then render but serve no items.
 *
 * Idempotent: existing nodes are reused, existing metadata rows are left alone
 * except for filling a NULL node_id. Dry-run by default.
 *
 *   php artisan pal:eso-bootstrap --institute=341 --chapter=8677
 *   php artisan pal:eso-bootstrap --institute=341 --chapter=8677 --confirm
 *   php artisan pal:eso-bootstrap --institute=341 --standard=4261 --confirm
 */
class EsoBootstrapCommand extends Command
{
    protected $signature = 'pal:eso-bootstrap
        {--institute= : sub_institute_id owning the chapters and concepts (required)}
        {--chapter=* : chapter_master.id to make ESO-ready (repeatable)}
        {--standard= : every chapter of this standard_id, instead of --chapter}
        {--node-tenant= : sub_institute_id to stamp on new nodes and metadata; defaults to --institute. Pass 0 to share across every institute}
        {--status=approved : quality_status for new metadata rows (approved = servable, draft = staged for review)}
        {--confirm : actually write; without this the command only reports what it WOULD do}';

    protected $description = 'Make a chapter ESO-ready for one institute: K nodes per concept + question-to-node mapping (dry-run by default)';

    public function handle(): int
    {
        $institute = $this->option('institute') !== null ? (int) $this->option('institute') : null;
        if ($institute === null) {
            $this->error('--institute is required.');

            return self::FAILURE;
        }

        $nodeTenant = $this->option('node-tenant') !== null ? (int) $this->option('node-tenant') : $institute;
        $status = (string) $this->option('status');
        $confirm = (bool) $this->option('confirm');

        if (! in_array($status, ['approved', 'draft'], true)) {
            $this->error("--status must be 'approved' or 'draft', got '{$status}'.");

            return self::FAILURE;
        }

        $chapterIds = $this->resolveChapterIds($institute);
        if ($chapterIds === []) {
            $this->warn('No chapters matched --chapter/--standard for that institute — nothing to do.');

            return self::SUCCESS;
        }

        $concepts = DB::table('lms_concept')
            ->whereIn('chapter_id', $chapterIds)
            ->where('sub_institute_id', $institute)
            ->orderBy('chapter_id')
            ->orderBy('id')
            ->get(['id', 'name', 'chapter_id']);

        if ($concepts->isEmpty()) {
            $this->warn("No lms_concept rows on those chapters for institute {$institute} — author concepts first.");

            return self::SUCCESS;
        }

        $this->line('Chapters in scope: '.implode(', ', $chapterIds));
        $this->line("Concepts: {$concepts->count()}   node tenant: {$nodeTenant}   metadata status: {$status}");
        $this->newLine();

        $existingNodes = DB::table('pal_concept_nodes')
            ->whereIn('concept_id', $concepts->pluck('id'))
            ->whereIn('sub_institute_id', array_unique([$nodeTenant, 0]))
            ->get(['id', 'concept_id'])
            ->keyBy('concept_id');

        $questions = DB::table('lms_question_master')
            ->whereIn('chapter_id', $chapterIds)
            ->where('question_type_id', 1) // MCQ — the only type hydrateQuestion() can serve
            ->get(['id', 'concept_id', 'chapter_id']);

        $existingMeta = DB::table('pal_question_metadata')
            ->whereIn('question_id', $questions->pluck('id'))
            ->whereIn('sub_institute_id', array_unique([$nodeTenant, 0]))
            ->get(['id', 'question_id', 'node_id'])
            ->keyBy('question_id');

        $conceptIds = $concepts->pluck('id')->flip();
        $nodesToCreate = $concepts->reject(fn ($c) => $existingNodes->has($c->id));
        $mappable = $questions->filter(fn ($q) => $q->concept_id !== null && $conceptIds->has((int) $q->concept_id));
        $orphanQuestions = $questions->count() - $mappable->count();

        $metaToCreate = $mappable->reject(fn ($q) => $existingMeta->has($q->id));
        $metaToBackfill = $mappable->filter(fn ($q) => $existingMeta->has($q->id) && $existingMeta[$q->id]->node_id === null);

        $this->table(['Action', 'Rows'], [
            ['K nodes to create', $nodesToCreate->count()],
            ['K nodes already present', $existingNodes->count()],
            ['Question metadata to create', $metaToCreate->count()],
            ['Question metadata to backfill node_id', $metaToBackfill->count()],
            ['MCQs skipped (concept_id missing or out of scope)', $orphanQuestions],
        ]);

        if ($nodesToCreate->isEmpty() && $metaToCreate->isEmpty() && $metaToBackfill->isEmpty()) {
            $this->info('Already bootstrapped — nothing to write.');

            return self::SUCCESS;
        }

        if (! $confirm) {
            $this->newLine();
            $this->warn('Dry run — nothing written. Re-run with --confirm to apply.');

            return self::SUCCESS;
        }

        $now = now();

        DB::transaction(function () use ($nodesToCreate, $existingNodes, $mappable, $existingMeta, $nodeTenant, $status, $now) {
            foreach ($nodesToCreate as $concept) {
                $id = DB::table('pal_concept_nodes')->insertGetId([
                    'concept_id' => $concept->id,
                    'sub_institute_id' => $nodeTenant,
                    'node_type' => 'K',
                    'label' => mb_substr('Recall: '.$concept->name, 0, 191),
                    'description' => null,
                    'mastery_threshold' => null,
                    'sort_order' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $existingNodes[$concept->id] = (object) ['id' => $id, 'concept_id' => $concept->id];
            }

            foreach ($mappable as $question) {
                $nodeId = $existingNodes[(int) $question->concept_id]->id ?? null;
                if ($nodeId === null) {
                    continue;
                }

                $meta = $existingMeta[$question->id] ?? null;

                if ($meta === null) {
                    DB::table('pal_question_metadata')->insert([
                        'question_id' => $question->id,
                        'sub_institute_id' => $nodeTenant,
                        'scope' => 'tenant',
                        'concept_ref_id' => (int) $question->concept_id,
                        'node_id' => $nodeId,
                        'chapter_ref_id' => (int) $question->chapter_id,
                        'quality_status' => $status,
                        'tagged_by' => 'derived',
                        'confidence' => 1.0, // the concept link is authored data, not an inference
                        'version' => '1.0',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                // Row already exists — fill only the missing link, never
                // overwrite a status or a tag a human set.
                if ($meta->node_id === null) {
                    DB::table('pal_question_metadata')
                        ->where('id', $meta->id)
                        ->update(['node_id' => $nodeId, 'updated_at' => $now]);
                }
            }
        });

        $this->newLine();
        $this->info('Written. Students on these chapters will now see the adaptive dashboard.');

        return self::SUCCESS;
    }

    /** @return array<int, int> */
    protected function resolveChapterIds(int $institute): array
    {
        $chapters = array_map('intval', (array) $this->option('chapter'));

        if ($chapters !== []) {
            return DB::table('chapter_master')
                ->whereIn('id', $chapters)
                ->where('sub_institute_id', $institute)
                ->pluck('id')
                ->all();
        }

        $standard = $this->option('standard');
        if ($standard === null) {
            return [];
        }

        return DB::table('chapter_master')
            ->where('sub_institute_id', $institute)
            ->where('standard_id', (int) $standard)
            ->pluck('id')
            ->all();
    }
}
