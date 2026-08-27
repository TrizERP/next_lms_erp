<?php

namespace App\Console\Commands\PAL;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair pass for generated MCQs that never got their options materialised.
 *
 * QuestionGenerationService stores the full option objects inside the
 * `lms_question_master.answer` envelope, but the PAL quiz runtime reads options
 * from `answer_master` only — palController::create() INNER JOINs it to pick
 * questions, and exam.blade.php renders `answer_master.id ## correct_answer` as
 * the radio value grading later compares against. Questions generated before
 * the service wrote those rows are therefore complete in JSON yet invisible in
 * "Start Quiz", which reports "Questions Not Found".
 *
 * Dry-run by default. Idempotent: a question that already has answer_master
 * rows is skipped, so re-running never duplicates options.
 */
class BackfillAnswerOptionsCommand extends Command
{
    protected $signature = 'pal:backfill-answer-options
        {--tenant= : restrict to one sub_institute_id}
        {--chapter= : restrict to one chapter_id}
        {--apply : write the rows (default is a dry run)}';

    protected $description = 'PAL: materialise answer_master options for generated MCQs that only have them in the answer JSON envelope';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $query = DB::table('lms_question_master as q')
            ->leftJoin('answer_master as am', 'am.question_id', '=', 'q.id')
            ->select('q.id', 'q.sub_institute_id', 'q.chapter_id', 'q.created_by', 'q.answer')
            ->where('q.question_type_id', 1)
            ->whereNotNull('q.answer')
            ->whereNull('am.id');

        if ($tenant = $this->option('tenant')) {
            $query->where('q.sub_institute_id', (int) $tenant);
        }
        if ($chapter = $this->option('chapter')) {
            $query->where('q.chapter_id', (int) $chapter);
        }

        $candidates = $query->orderBy('q.id')->get();

        if ($candidates->isEmpty()) {
            $this->info('Nothing to backfill — every generated MCQ already has answer_master rows.');
            return self::SUCCESS;
        }

        $rows = [];
        $repaired = 0;
        $skipped = [];

        foreach ($candidates as $q) {
            $envelope = json_decode($q->answer, true);
            if (!is_array($envelope) || empty($envelope['options']) || !is_array($envelope['options'])) {
                $skipped[] = [$q->id, 'no options in envelope'];
                continue;
            }

            $questionRows = [];
            foreach ($envelope['options'] as $option) {
                $text = trim((string) ($option['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $feedback = trim((string) ($option['rationale'] ?? ''));

                $questionRows[] = [
                    'question_id'      => $q->id,
                    // answer / feedback are varchar(250) in answer_master.
                    'answer'           => mb_substr($text, 0, 250),
                    'feedback'         => $feedback === '' ? null : mb_substr($feedback, 0, 250),
                    'correct_answer'   => !empty($option['is_correct']) ? 1 : 0,
                    'sub_institute_id' => $q->sub_institute_id,
                    'created_by'       => $q->created_by,
                    'created_on'       => now(),
                ];
            }

            // An option set with no correct answer would be served and then
            // graded wrong for every learner — leave it JSON-only and report it.
            $correct = array_sum(array_column($questionRows, 'correct_answer'));
            if (count($questionRows) < 2 || $correct < 1) {
                $skipped[] = [$q->id, $correct < 1 ? 'no correct option' : 'fewer than 2 options'];
                continue;
            }

            $rows = array_merge($rows, $questionRows);
            $repaired++;
        }

        $this->line('');
        $this->info(sprintf(
            '%s: %d question(s) repairable, %d option row(s), %d skipped.',
            $apply ? 'Applying' : 'Dry run',
            $repaired,
            count($rows),
            count($skipped)
        ));

        if (!empty($skipped)) {
            $this->warn('Skipped (left JSON-only):');
            $this->table(['question_id', 'reason'], $skipped);
        }

        if (!$apply) {
            $this->line('');
            $this->comment('Re-run with --apply to write these rows.');
            return self::SUCCESS;
        }

        if (!empty($rows)) {
            DB::transaction(function () use ($rows) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('answer_master')->insert($chunk);
                }
            });
        }

        $this->info(sprintf('Inserted %d answer_master row(s) for %d question(s).', count($rows), $repaired));

        return self::SUCCESS;
    }
}
