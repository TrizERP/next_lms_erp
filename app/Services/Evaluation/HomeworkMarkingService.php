<?php

namespace App\Services\Evaluation;

use App\Services\Homework\Exceptions\DocumentExtractionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The marking half of homework evaluation, shared by both homework jobs.
 *
 * There are two homework submission flows and they differ in ways that matter:
 * the legacy one takes a single file, the v2 one takes several and tolerates
 * one of them being unreadable. Those differences are real and stay in their
 * own jobs. What does NOT need two copies is the marking itself, and that is
 * what lives here:
 *
 *  - `markAgainstAnswerKey()` — the exam-grade path, for homework that carries
 *    real questions. Reuses AnswerKeyService, AnswerSheetReaderService and
 *    AnswerSheetScoringService unchanged, so an MCQ in a homework book is
 *    scored by exactly the same code as the same MCQ on an exam answer sheet.
 *  - `answersFromFreeForm()` — turns the whole-submission verdict the old path
 *    produces into the same per-question rows, so the teacher's review screen
 *    never has to know which path ran.
 *  - `persist()` / `annotations()` / `totals()` — shared output.
 *
 * Nothing here writes `teacher_marks`. Everything it produces is a proposal.
 */
class HomeworkMarkingService
{
    public const MODE_ANSWER_KEY = 'answer_key';
    public const MODE_FREE_FORM = 'free_form';

    public function __construct(
        private readonly AnswerKeyService $answerKeys,
        private readonly AnswerSheetReaderService $reader,
        private readonly AnswerSheetScoringService $scorer
    ) {
    }

    /**
     * Marks a submission against the homework's own questions.
     *
     * Several files are read in turn and their responses merged by question
     * number, because an answer book photographed page by page arrives as
     * several files and the questions run across them. First readable answer
     * for a question wins; a later file can only fill a question an earlier one
     * left blank, never overwrite one it answered.
     *
     * @param  array<int,int>  $questionIds
     * @param  array<int,array{path:string,mime:string}>  $files
     * @return array{answers: array<int,array<string,mixed>>, ai_marks: float, max_marks: float, mode: string}
     *
     * @throws DocumentExtractionException when nothing could be read at all.
     */
    public function markAgainstAnswerKey(array $questionIds, int $tenantId, array $files, string $title): array
    {
        $key = $this->answerKeys->forQuestionIds($questionIds, [$tenantId]);
        $readerView = $this->answerKeys->readerView($key['questions']);

        $responses = [];
        $lastFailure = null;

        foreach ($files as $file) {
            try {
                $read = $this->reader->read($file['path'], $file['mime'], $readerView, ['paper_name' => $title]);
            } catch (DocumentExtractionException $exception) {
                // One unreadable page must not lose the rest of the book.
                $lastFailure = $exception;
                Log::warning('Homework answer-key read failed for one file', [
                    'file' => $file['path'],
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }

            foreach ($read['responses'] as $response) {
                $questionNo = (int) $response['question_no'];

                if (! isset($responses[$questionNo]) || ! $responses[$questionNo]['attempted']) {
                    $responses[$questionNo] = $response;
                }
            }
        }

        if ($responses === []) {
            throw $lastFailure ?? new DocumentExtractionException('None of the submitted files could be read.');
        }

        ksort($responses);
        $scored = $this->scorer->score($key['questions'], array_values($responses));

        // The scorer does not carry question text. The review screen wants it,
        // and reading it back later would show whatever the question bank says
        // today rather than what was actually set.
        $titles = [];

        foreach ($key['questions'] as $question) {
            $titles[(int) $question['question_no']] = (string) $question['question_title'];
        }

        $answers = array_map(static function (array $answer) use ($titles) {
            $answer['question_title'] = $titles[(int) $answer['question_no']] ?? '';

            return $answer;
        }, $scored['answers']);

        return [
            'answers' => $answers,
            'ai_marks' => round((float) $scored['ai_total'], 2),
            'max_marks' => round((float) $scored['max_marks'], 2),
            'mode' => self::MODE_ANSWER_KEY,
        ];
    }

    /**
     * The free-form verdict, rewritten as per-question rows.
     *
     * Everything is worth one mark, because on this path nothing knows what any
     * question was worth — there is no key, only the teacher's document and the
     * student's. A partially correct answer takes half, which is the only
     * honest reading of a three-state verdict on a one-mark question.
     *
     * @param  array<int,array<string,mixed>>  $results  From HomeworkEvaluationService::evaluate()['results'].
     * @param  array<int,array<string,mixed>>  $located  From HomeworkAnswerLocatorService, for page/box_2d.
     * @return array{answers: array<int,array<string,mixed>>, ai_marks: float, max_marks: float, mode: string}
     */
    public function answersFromFreeForm(array $results, array $located = []): array
    {
        $byQuestion = [];

        foreach ($located as $answer) {
            if (is_array($answer) && isset($answer['question_no'])) {
                $byQuestion[(int) $answer['question_no']] = $answer;
            }
        }

        $answers = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $questionNo = (int) ($result['question_no'] ?? 0);

            if ($questionNo < 1) {
                continue;
            }

            $location = $byQuestion[$questionNo] ?? null;
            $status = (string) ($result['status'] ?? 'wrong');

            $answers[] = [
                'question_no' => $questionNo,
                'question_id' => 0,
                'question_type' => '',
                'question_title' => '',
                'is_objective' => false,
                'max_marks' => 1.0,
                'selected_options' => [],
                'detected_answer' => (string) ($location['text'] ?? ''),
                'expected_answer' => (string) ($result['expected_answer'] ?? ''),
                'page' => (int) ($location['page'] ?? 1),
                'box_2d' => $location['box_2d'] ?? null,
                'ai_marks' => $status === 'correct' ? 1.0 : ($status === 'partially_correct' ? 0.5 : 0.0),
                'status' => $status,
                // Nothing here was checked against a key, so none of it carries
                // the confidence an objective comparison would.
                'ai_confidence' => null,
                'ai_remark' => (string) ($result['remarks'] ?? ''),
            ];
        }

        return [
            'answers' => $answers,
            'ai_marks' => round((float) array_sum(array_column($answers, 'ai_marks')), 2),
            'max_marks' => (float) count($answers),
            'mode' => self::MODE_FREE_FORM,
        ];
    }

    /**
     * Replaces this submission's per-question rows.
     *
     * Delete-then-insert rather than upsert because a re-run can legitimately
     * produce a different number of questions — a better scan finds answers the
     * first pass missed — and leftovers from the old run would be marked
     * against questions nobody answered.
     *
     * @param  array<int,array<string,mixed>>  $answers
     */
    public function persist(int $homeworkId, int $tenantId, array $answers): void
    {
        $now = now();

        DB::transaction(function () use ($homeworkId, $tenantId, $answers, $now) {
            DB::table('homework_evaluation_answer')->where('homework_id', $homeworkId)->delete();

            $rows = [];

            foreach ($answers as $answer) {
                $rows[] = [
                    'homework_id' => $homeworkId,
                    'sub_institute_id' => $tenantId ?: null,
                    'question_id' => (int) ($answer['question_id'] ?? 0) ?: null,
                    'question_no' => (int) $answer['question_no'],
                    'question_type' => mb_substr((string) ($answer['question_type'] ?? ''), 0, 60),
                    'is_objective' => (bool) ($answer['is_objective'] ?? false),
                    'question_title' => (string) ($answer['question_title'] ?? ''),
                    'detected_answer' => (string) ($answer['detected_answer'] ?? ''),
                    'selected_options' => mb_substr(implode(',', (array) ($answer['selected_options'] ?? [])), 0, 100),
                    'expected_answer' => (string) ($answer['expected_answer'] ?? ''),
                    'max_marks' => (float) ($answer['max_marks'] ?? 0),
                    'ai_marks' => (float) ($answer['ai_marks'] ?? 0),
                    // Deliberately null. Accepting the AI's mark is still the
                    // teacher's act -- approval is what copies it across.
                    'teacher_marks' => null,
                    'status' => (string) ($answer['status'] ?? 'unattempted'),
                    'ai_confidence' => ($answer['ai_confidence'] ?? null) === null ? null : (float) $answer['ai_confidence'],
                    'ai_remark' => mb_substr((string) ($answer['ai_remark'] ?? ''), 0, 500),
                    'page' => (int) ($answer['page'] ?? 1),
                    'box_2d' => ! empty($answer['box_2d']) ? json_encode($answer['box_2d']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('homework_evaluation_answer')->insert($chunk);
            }
        });
    }

    /**
     * The marks to draw on the student's own pages.
     *
     * An untouched question gets nothing: a cross beside a blank space reads as
     * "this was wrong" rather than "nothing was written here".
     *
     * @param  array<int,array<string,mixed>>  $answers
     * @return array<int,array<string,mixed>>
     */
    public function annotations(array $answers): array
    {
        $annotations = [];

        foreach ($answers as $answer) {
            if (($answer['status'] ?? '') === 'unattempted') {
                continue;
            }

            $annotations[] = [
                'question_no' => (int) $answer['question_no'],
                'status' => (string) $answer['status'],
                'expected_answer' => (string) ($answer['expected_answer'] ?? ''),
                'remarks' => (string) ($answer['ai_remark'] ?? ''),
                'page' => (int) ($answer['page'] ?? 1),
                'box_2d' => $answer['box_2d'] ?? null,
            ];
        }

        return $annotations;
    }

    /**
     * @param  array<int,array<string,mixed>>  $answers
     * @return array{correct:int, questions:int, percentage:float|null}
     */
    public function totals(array $answers, float $aiMarks, float $maxMarks): array
    {
        return [
            'correct' => count(array_filter($answers, static fn ($answer) => ($answer['status'] ?? '') === 'correct')),
            'questions' => count($answers),
            // Marks where there are marks, counts where there are not. On the
            // free-form path every question is worth one, so the two coincide
            // and there is no ambiguity either way.
            'percentage' => $maxMarks > 0 ? round($aiMarks / $maxMarks * 100, 2) : null,
        ];
    }
}
