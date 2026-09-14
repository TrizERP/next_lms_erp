<?php

namespace App\Console\Commands\PAL;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copies one standard's whole curriculum onto another standard of the same
 * institute: chapters, concepts, questions and answer options.
 *
 * WHY A COPY AND NOT A RE-LINK
 * ----------------------------
 * The obvious cheaper move — point the target standard's concepts at the
 * questions that already exist — is impossible. `pal_question_metadata` carries
 * a unique index on (question_id, sub_institute_id), so within one institute a
 * question can be mapped to exactly one node. Questions already serving the
 * source standard therefore cannot also serve the target one; they have to be
 * duplicated so each copy can carry its own metadata row.
 *
 * `lms_question_master` is itself uniquely indexed on
 * (concept_id, question_type_id, g_content_hash). The copies clear that
 * constraint naturally because each one is attached to a NEW concept id, so an
 * identical question body is not a collision.
 *
 * WHAT IS REMAPPED, AND WHAT IS CARRIED OVER UNCHANGED
 * ----------------------------------------------------
 * Remapped: chapter_id, concept_id, standard_id and grade_id, so the copies
 * belong to the target standard rather than pointing back at the source.
 * grade_id is read from the TARGET standard row, not copied, because the two
 * standards can sit in different academic sections.
 *
 * Carried over unchanged: topic_id. Topics are not part of the adaptive chain
 * (nothing in EsoPolicyService reads them) and copying the topic tree would
 * widen this command considerably, so the copies reference the source topics.
 * Worth knowing before relying on topic-scoped reporting for the target.
 *
 * This does NOT make the target adaptive on its own — it creates the content.
 * Run pal:eso-bootstrap afterwards to author the K nodes and question mapping.
 *
 * Idempotent by refusal, not by merge: if the target standard already has
 * chapters, the command stops rather than producing a second copy.
 *
 *   php artisan pal:clone-curriculum --institute=341 --from=4261 --to=4264
 *   php artisan pal:clone-curriculum --institute=341 --from=4261 --to=4264 --confirm
 */
class CloneCurriculumCommand extends Command
{
    protected $signature = 'pal:clone-curriculum
        {--institute= : sub_institute_id owning both standards (required)}
        {--from= : source standard_id to copy from (required)}
        {--to= : target standard_id to copy onto (required)}
        {--chapter=* : limit to these source chapter ids (default: every chapter of the source standard)}
        {--confirm : actually write; without this the command only reports what it WOULD do}';

    protected $description = 'Copy chapters, concepts, questions and answers from one standard onto another (dry-run by default)';

    public function handle(): int
    {
        $institute = $this->option('institute') !== null ? (int) $this->option('institute') : null;
        $from = $this->option('from') !== null ? (int) $this->option('from') : null;
        $to = $this->option('to') !== null ? (int) $this->option('to') : null;

        if ($institute === null || $from === null || $to === null) {
            $this->error('--institute, --from and --to are all required.');

            return self::FAILURE;
        }

        if ($from === $to) {
            $this->error('--from and --to must be different standards.');

            return self::FAILURE;
        }

        $targetStandard = DB::table('standard')->where('id', $to)->where('sub_institute_id', $institute)->first();
        if ($targetStandard === null) {
            $this->error("Standard {$to} does not exist for institute {$institute}.");

            return self::FAILURE;
        }

        $existing = DB::table('chapter_master')
            ->where('sub_institute_id', $institute)
            ->where('standard_id', $to)
            ->count();

        if ($existing > 0) {
            $this->error("Standard {$to} already has {$existing} chapters — refusing to create a second copy.");

            return self::FAILURE;
        }

        $only = array_map('intval', (array) $this->option('chapter'));

        $chapters = DB::table('chapter_master')
            ->where('sub_institute_id', $institute)
            ->where('standard_id', $from)
            ->when($only !== [], fn ($q) => $q->whereIn('id', $only))
            ->orderBy('sort_order')
            ->get();

        if ($chapters->isEmpty()) {
            $this->warn("Source standard {$from} has no chapters — nothing to copy.");

            return self::SUCCESS;
        }

        $concepts = DB::table('lms_concept')
            ->whereIn('chapter_id', $chapters->pluck('id'))
            ->where('sub_institute_id', $institute)
            ->get();

        $questions = DB::table('lms_question_master')
            ->whereIn('chapter_id', $chapters->pluck('id'))
            ->whereNull('deleted_at')
            ->get();

        $answers = $questions->isEmpty() ? collect() : DB::table('answer_master')
            ->whereIn('question_id', $questions->pluck('id'))
            ->get();

        $this->line("Institute {$institute}:  standard {$from}  ->  standard {$to} ({$targetStandard->name})");
        $this->newLine();
        $this->table(['Table', 'Rows to create'], [
            ['chapter_master', $chapters->count()],
            ['lms_concept', $concepts->count()],
            ['lms_question_master', $questions->count()],
            ['answer_master', $answers->count()],
        ]);

        if (! $this->option('confirm')) {
            $this->newLine();
            $this->warn('Dry run — nothing written. Re-run with --confirm to apply.');

            return self::SUCCESS;
        }

        $now = now();
        $counts = ['chapters' => 0, 'concepts' => 0, 'questions' => 0, 'answers' => 0];

        DB::transaction(function () use ($chapters, $concepts, $questions, $answers, $institute, $to, $targetStandard, $now, &$counts) {
            // ── chapters ────────────────────────────────────────────────────
            $chapterMap = [];
            foreach ($chapters as $chapter) {
                $chapterMap[$chapter->id] = DB::table('chapter_master')->insertGetId([
                    'extraction_id'    => $chapter->extraction_id,
                    'unit_id'          => $chapter->unit_id,
                    'syear'            => $chapter->syear,
                    'sub_institute_id' => $institute,
                    'grade_id'         => $targetStandard->grade_id,
                    'standard_id'      => $to,
                    'subject_id'       => $chapter->subject_id,
                    'chapter_name'     => $chapter->chapter_name,
                    'key_concepts'     => $chapter->key_concepts,
                    'no_of_periods'    => $chapter->no_of_periods,
                    'chapter_desc'     => $chapter->chapter_desc,
                    'availability'     => $chapter->availability,
                    'show_hide'        => $chapter->show_hide,
                    'sort_order'       => $chapter->sort_order,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
                $counts['chapters']++;
            }

            // ── concepts ────────────────────────────────────────────────────
            $conceptMap = [];
            foreach ($concepts as $concept) {
                $newChapterId = $chapterMap[$concept->chapter_id] ?? null;
                if ($newChapterId === null) {
                    continue;
                }

                $conceptMap[$concept->id] = DB::table('lms_concept')->insertGetId([
                    'extraction_id'             => $concept->extraction_id,
                    'name'                      => $concept->name,
                    'description'               => $concept->description,
                    'subject_id'                => $concept->subject_id,
                    'standard_id'               => $to,
                    'chapter_id'                => $newChapterId,
                    'topic_id'                  => $concept->topic_id,
                    'sub_institute_id'          => $institute,
                    'mastery_threshold'         => $concept->mastery_threshold,
                    'learning_pattern'          => $concept->learning_pattern,
                    'estimated_mastery_minutes' => $concept->estimated_mastery_minutes,
                    'syear'                     => $concept->syear,
                    'created_at'                => $now,
                    'updated_at'                => $now,
                ]);
                $counts['concepts']++;
            }

            // ── questions ───────────────────────────────────────────────────
            $questionMap = [];
            foreach ($questions as $question) {
                $newChapterId = $chapterMap[$question->chapter_id] ?? null;
                if ($newChapterId === null) {
                    continue;
                }

                $questionMap[$question->id] = DB::table('lms_question_master')->insertGetId([
                    'question_type_id'             => $question->question_type_id,
                    'grade_id'                     => $targetStandard->grade_id,
                    'standard_id'                  => $to,
                    'subject_id'                   => $question->subject_id,
                    'chapter_id'                   => $newChapterId,
                    // A question whose concept did not come across keeps no
                    // stale pointer into the source standard.
                    'concept_id'                   => $conceptMap[$question->concept_id] ?? null,
                    'topic_id'                     => $question->topic_id,
                    'question_title'               => $question->question_title,
                    'description'                  => $question->description,
                    'points'                       => $question->points,
                    'multiple_answer'              => $question->multiple_answer,
                    'concept'                      => $question->concept,
                    'subconcept'                   => $question->subconcept,
                    'category'                     => $question->category,
                    'pre_grade_topic'              => $question->pre_grade_topic,
                    'post_grade_topic'             => $question->post_grade_topic,
                    'cross_curriculum_grade_topic' => $question->cross_curriculum_grade_topic,
                    'sub_institute_id'             => $institute,
                    'status'                       => $question->status,
                    'created_by'                   => $question->created_by,
                    'created_on'                   => $now,
                    'answer'                       => $question->answer,
                    'g_bloom'                      => $question->g_bloom,
                    'g_difficulty'                 => $question->g_difficulty,
                    'g_dok'                        => $question->g_dok,
                    'g_content_hash'               => $question->g_content_hash,
                    'hint_text'                    => $question->hint_text,
                    'learning_outcome'             => $question->learning_outcome,
                ]);
                $counts['questions']++;
            }

            // ── answer options ──────────────────────────────────────────────
            foreach ($answers->chunk(200) as $chunk) {
                $rows = [];
                foreach ($chunk as $answer) {
                    $newQuestionId = $questionMap[$answer->question_id] ?? null;
                    if ($newQuestionId === null) {
                        continue;
                    }

                    $rows[] = [
                        'question_id'      => $newQuestionId,
                        'answer'           => $answer->answer,
                        'feedback'         => $answer->feedback,
                        'correct_answer'   => $answer->correct_answer,
                        'misconception_id' => $answer->misconception_id,
                        'sub_institute_id' => $institute,
                        'created_by'       => $answer->created_by,
                        'created_on'       => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('answer_master')->insert($rows);
                    $counts['answers'] += count($rows);
                }
            }
        });

        $this->newLine();
        $this->info("Copied: {$counts['chapters']} chapters, {$counts['concepts']} concepts, {$counts['questions']} questions, {$counts['answers']} answer options.");
        $this->line("Next: php artisan pal:eso-bootstrap --institute={$institute} --standard={$to} --confirm");

        return self::SUCCESS;
    }
}
