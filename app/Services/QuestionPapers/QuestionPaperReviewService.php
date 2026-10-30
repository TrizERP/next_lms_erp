<?php

namespace App\Services\QuestionPapers;

use Illuminate\Support\Facades\DB;

/**
 * Review of the questions a paper produced, and activation of the ones saved inactive.
 *
 * A question is stored inactive (status 0) when the paper left something open: no printed answer
 * key, or no chapter. Activation closes exactly those gaps and nothing else:
 *  - the job must be finished (never while a worker is writing),
 *  - the job must now have a standard, subject AND chapter (the question is aligned to them),
 *  - an objective question needs a correct option, either printed in the paper or chosen by the
 *    reviewer from the extracted options (never inferred),
 *  - only questions this job created, in this school, still inactive, can be touched.
 */
class QuestionPaperReviewService
{
    private const OBJECTIVE = ['mcq', 'true_false', 'assertion_reason'];

    public function __construct(private QuestionPaperJobService $jobs, private QuestionPaperChapterSuggester $chapters, private QuestionPaperChapterMatcher $matcher)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(object $job, ?string $state = null, int $limit = 200): array
    {
        $rows = DB::table('question_paper_items')
            ->where('job_id', $job->id)
            ->when($state, fn ($q) => $q->where('state', $state))
            ->orderBy('ordinal')->limit(max(1, min(500, $limit)))
            ->get();

        $chapterNames = $job->standard_id !== null && $job->subject_id !== null
            ? $this->matcher->chapterNames((int) $job->sub_institute_id, (int) $job->standard_id, (int) $job->subject_id)
            : [];

        return $rows->map(function ($row) use ($job, $chapterNames) {
            $payload = json_decode((string) $row->payload, true);
            $payload = is_array($payload) ? $payload : [];
            $blockers = $row->state === 'needs_review' ? $this->blockers($job, $row, $payload, null) : [];

            return [
                'id' => (int) $row->id,
                'ordinal' => (int) $row->ordinal,
                'item_number' => $row->item_number,
                'page' => $row->source_page !== null ? (int) $row->source_page : null,
                'format' => $row->format_code,
                'state' => $row->state,
                'reason' => $row->reason,
                'question_id' => $row->question_id !== null ? (int) $row->question_id : null,
                'question_text' => $payload['question_text'] ?? ($payload['question_text'] ?? null),
                'options' => $payload['options'] ?? [],
                'correct_option' => $payload['correct_option'] ?? null,
                'model_answer' => $payload['model_answer'] ?? null,
                'pairs' => $payload['pairs'] ?? null,
                'marks' => $payload['marks'] ?? null,
                'needs_answer_key' => in_array($row->format_code, self::OBJECTIVE, true) && empty($payload['correct_option']),
                'can_activate' => $row->state === 'needs_review' && $blockers === [],
                'blockers' => $blockers,
                'chapter_suggestion' => $this->chapters->suggest($job, $payload),
                'chapter_id' => isset($payload['chapter_id']) ? (int) $payload['chapter_id'] : null,
                'chapter_name' => isset($payload['chapter_id']) ? ($chapterNames[(int) $payload['chapter_id']] ?? null) : null,
                // auto = assigned by the matcher at high confidence; teacher = chosen by a person; null = the paper-wide chapter or none.
                'chapter_source' => isset($payload['chapter_id']) ? ($payload['chapter_source'] ?? 'teacher') : null,
                'needs_chapter' => ($payload['chapter_id'] ?? $job->chapter_id) === null && in_array($row->state, ['needs_review', 'parsed', 'unresolved'], true),
            ];
        })->all();
    }

    /**
     * @param  list<array{id: int, correct_option?: string|null, chapter_id?: int|null}>  $requests
     * @return list<array{id: int, ok: bool, message: string}>
     */
    public function activate(object $job, array $requests): array
    {
        $results = [];
        foreach ($requests as $request) {
            $itemId = (int) ($request['id'] ?? 0);
            try {
                $message = DB::transaction(fn () => $this->activateOne($job, $itemId, isset($request['correct_option']) ? strtoupper(trim((string) $request['correct_option'])) : null, !empty($request['chapter_id']) ? (int) $request['chapter_id'] : null));
                $results[] = ['id' => $itemId, 'ok' => true, 'message' => $message];
            } catch (ActivationRefused $e) {
                $results[] = ['id' => $itemId, 'ok' => false, 'message' => $e->getMessage()];
            }
        }

        $this->recount((int) $job->id);

        return $results;
    }

    /** @return list<string> why the item cannot be activated right now; empty when it can */
    private function blockers(object $job, object $item, array $payload, ?string $chosenOption, ?int $chosenChapter = null): array
    {
        $blockers = [];

        if (in_array($job->status, ['queued', 'processing', 'retrying'], true)) {
            $blockers[] = 'The paper is still being processed.';
        }
        $missing = [];
        foreach (['standard_id' => 'standard', 'subject_id' => 'subject'] as $column => $label) {
            if ($job->{$column} === null) {
                $missing[] = $label;
            }
        }
        if (($chosenChapter ?? $payload['chapter_id'] ?? $job->chapter_id) === null) $missing[] = 'chapter';
        if ($missing !== []) {
            $blockers[] = in_array('chapter', $missing, true) && count($missing) === 1
                ? 'Confirm the chapter for this question.'
                : 'Choose the ' . implode(', ', $missing) . ' for this paper first.';
        }

        if (in_array($item->format_code, self::OBJECTIVE, true)) {
            $labels = array_column($payload['options'] ?? [], 'label');
            $correct = $chosenOption ?? ($payload['correct_option'] ?? null);
            if ($correct === null || !in_array($correct, $labels, true)) {
                $blockers[] = 'Choose the correct option.';
            }
        }

        return $blockers;
    }

    private function activateOne(object $job, int $itemId, ?string $chosenOption, ?int $chosenChapter = null): string
    {
        $item = DB::table('question_paper_items')->where('id', $itemId)->where('job_id', $job->id)
            ->where('sub_institute_id', $job->sub_institute_id)->lockForUpdate()->first();
        if (!$item) {
            throw new ActivationRefused('That question does not belong to this paper.');
        }
        if ($item->state === 'saved') {
            throw new ActivationRefused('Already active.');
        }
        if ($item->state !== 'needs_review' || $item->question_id === null) {
            throw new ActivationRefused('This question is not saved in the question bank yet.');
        }

        $payload = json_decode((string) $item->payload, true);
        $payload = is_array($payload) ? $payload : [];
        // The teacher's confirmation here beats an automatic pick and the paper-wide chapter; it must be a chapter of THIS standard and subject.
        $chapterId = $chosenChapter ?? (isset($payload['chapter_id']) ? (int) $payload['chapter_id'] : ($job->chapter_id !== null ? (int) $job->chapter_id : null));
        if ($chosenChapter !== null) {
            $payload['chapter_id'] = $chosenChapter;
            $payload['chapter_source'] = 'teacher';
        }
        if ($chapterId !== null && !app(QuestionPaperMetadataResolver::class)->validChapter((int) $job->sub_institute_id, (int) $job->standard_id, (int) $job->subject_id, $chapterId)) {
            throw new ActivationRefused('The selected chapter no longer belongs to this Standard and Subject.');
        }
        if ($blockers = $this->blockers($job, $item, $payload, $chosenOption, $chosenChapter)) {
            throw new ActivationRefused(implode(' ', $blockers));
        }

        $question = DB::table('lms_question_master')->where('id', $item->question_id)
            ->where('sub_institute_id', $job->sub_institute_id)->where('status', 0)->lockForUpdate()->first();
        $envelope = $question ? json_decode((string) $question->answer, true) : null;
        if (!$question || !is_array($envelope) || ($envelope['source']['job'] ?? null) !== $job->uuid) {
            throw new ActivationRefused('The question bank record is missing, already active, or was not created by this paper.');
        }

        // Fill the one gap the reviewer closed: the correct option, chosen from what was extracted.
        if (in_array($item->format_code, self::OBJECTIVE, true)) {
            $correct = $chosenOption ?? $payload['correct_option'];
            foreach ($payload['options'] as &$option) {
                $option['is_correct'] = $option['label'] === $correct;
            }
            unset($option);
            $payload['correct_option'] = $correct;
            $envelope['options'] = $payload['options'];
            $envelope['correct_option'] = $correct;

            if (DB::table('answer_master')->where('question_id', $question->id)->count() === 0) {
                DB::table('answer_master')->insert(array_map(fn ($o) => [
                    'question_id' => $question->id,
                    'answer' => mb_substr($o['text'], 0, 250),
                    'feedback' => null,
                    'correct_answer' => $o['is_correct'] ? 1 : 0,
                    'sub_institute_id' => $job->sub_institute_id,
                    'created_by' => $job->user_id,
                    'created_on' => now(),
                ], $payload['options']));
            }
        }
        $envelope['review_reasons'] = [];
        $envelope['activated_from_review'] = true;
        $payload['review'] = [];

        DB::table('lms_question_master')->where('id', $question->id)->update([
            'status' => 1,
            'standard_id' => $job->standard_id,
            'subject_id' => $job->subject_id,
            'chapter_id' => $chapterId,
            'answer' => json_encode($envelope, JSON_UNESCAPED_UNICODE),
        ]);
        DB::table('question_paper_items')->where('id', $item->id)->update([
            'state' => 'saved', 'reason' => null, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
        ]);

        return 'Activated.';
    }

    /** Re-derive the job's counters from its items so the numbers always match what is stored. */
    private function recount(int $jobId): void
    {
        $states = $this->jobs->breakdowns([$jobId])[$jobId] ?? [];
        DB::table('question_paper_jobs')->where('id', $jobId)->update([
            'questions_saved' => ($states['saved'] ?? 0) + ($states['needs_review'] ?? 0),
            'questions_needing_review' => ($states['needs_review'] ?? 0) + ($states['unresolved'] ?? 0),
            'questions_skipped' => ($states['skipped'] ?? 0) + ($states['duplicate'] ?? 0),
            'updated_at' => now(),
        ]);
    }
}
