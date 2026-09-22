<?php

namespace App\Services\Evaluation;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The marking key for one question paper.
 *
 * Reads the paper the same way the printed-paper renderer does -- through
 * `question_paper.question_ids`, in the teacher's chosen order -- and adds the
 * half a printed paper never carries: which option is right
 * (`answer_master.correct_answer`) and, for a written question, the model
 * answer the teacher saved on the question (`lms_question_master.answer`).
 *
 * A question is treated as OBJECTIVE when it has options with at least one
 * marked correct. That is the signal that actually matters -- it holds for
 * MCQ, true/false and assertion-reason alike, and it does not depend on a
 * school having spelled its `question_type` rows the way we expected. Every
 * other question is SUBJECTIVE and is graded against its model answer.
 */
class AnswerKeyService
{
    public const KIND_OBJECTIVE = 'objective';
    public const KIND_SUBJECTIVE = 'subjective';

    /** A, B, C ... for the option letters a student actually writes or bubbles. */
    private const LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    /**
     * @param  array<int,int>  $tenantIds  Tenants this caller may read from.
     * @return array{paper: array<string,mixed>, questions: array<int,array<string,mixed>>, total_marks: float, objective_count: int, subjective_count: int}
     */
    public function forPaper(int $paperId, array $tenantIds): array
    {
        $paper = DB::table('question_paper')
            ->leftJoin('standard', 'standard.id', '=', 'question_paper.standard_id')
            ->leftJoin('academic_section', 'academic_section.id', '=', 'question_paper.grade_id')
            ->leftJoin('subject', 'subject.id', '=', 'question_paper.subject_id')
            ->where('question_paper.id', $paperId)
            ->whereIn('question_paper.sub_institute_id', $tenantIds)
            ->select(
                'question_paper.*',
                'standard.name as standard_name',
                'academic_section.title as grade_name',
                'subject.subject_name as subject_name'
            )
            ->first();

        if (! $paper) {
            throw new RuntimeException('Question paper not found.');
        }

        $questionIds = collect(explode(',', (string) $paper->question_ids))
            ->map(fn ($value) => (int) trim($value))
            ->filter()
            ->values();

        if ($questionIds->isEmpty()) {
            throw new RuntimeException('This question paper has no questions on it yet.');
        }

        $built = $this->forQuestionIds($questionIds->all(), $tenantIds);

        return array_merge($built, [
            'paper' => [
                'id' => (int) $paper->id,
                'paper_name' => (string) ($paper->paper_name ?? ''),
                'exam_type' => (string) ($paper->exam_type ?? ''),
                'standard_id' => (int) ($paper->standard_id ?? 0),
                'standard_name' => (string) ($paper->standard_name ?? ''),
                'grade_id' => (int) ($paper->grade_id ?? 0),
                'grade_name' => (string) ($paper->grade_name ?? ''),
                'subject_id' => (int) ($paper->subject_id ?? 0),
                'subject_name' => (string) ($paper->subject_name ?? ''),
                'syear' => (string) ($paper->syear ?? ''),
                'sub_institute_id' => (int) ($paper->sub_institute_id ?? 0),
            ],
        ]);
    }

    /**
     * The same key, built from an explicit list of question ids.
     *
     * Homework carries its questions as a comma-separated `question_ids` list
     * on its own row rather than through a `question_paper`, so it has no paper
     * to look up — but the marking key it needs is identical. Keeping this as
     * the shared core means an MCQ is scored the same way whether it turns up
     * on an exam answer sheet or in a homework book.
     *
     * @param  array<int,int>  $questionIds  In the order they were set.
     * @param  array<int,int>  $tenantIds
     * @return array{questions: array<int,array<string,mixed>>, total_marks: float, objective_count: int, subjective_count: int}
     */
    public function forQuestionIds(array $questionIds, array $tenantIds): array
    {
        $questionIds = collect($questionIds)
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values();

        if ($questionIds->isEmpty()) {
            throw new RuntimeException('No questions were given to build a marking key from.');
        }

        $rows = DB::table('lms_question_master as q')
            ->leftJoin('question_type_master as qt', 'qt.id', '=', 'q.question_type_id')
            ->whereIn('q.id', $questionIds)
            ->get([
                'q.id',
                'q.question_type_id',
                'q.question_title',
                'q.points',
                'q.multiple_answer',
                'q.answer',
                'qt.question_type',
            ])
            ->keyBy('id');

        $options = DB::table('answer_master')
            ->whereIn('question_id', $questionIds)
            ->orderBy('id')
            ->get(['id', 'question_id', 'answer', 'correct_answer'])
            ->groupBy('question_id');

        $questions = [];
        $questionNo = 0;
        $totalMarks = 0.0;
        $objective = 0;
        $subjective = 0;

        // whereIn() does not preserve order and `question_ids` IS the order the
        // paper was printed in -- which is the order the student numbered their
        // answers in, so it has to be the order we grade in.
        foreach ($questionIds as $questionId) {
            $row = $rows->get($questionId);

            if (! $row) {
                continue;
            }

            $questionNo++;
            $maxMarks = max(0.0, (float) ($row->points ?? 0));
            $totalMarks += $maxMarks;

            $rawOptions = $options->get($questionId, collect());
            $built = [];
            $correctLetters = [];

            foreach ($rawOptions->values() as $index => $option) {
                $letter = self::LETTERS[$index] ?? (string) ($index + 1);
                $isCorrect = (int) ($option->correct_answer ?? 0) === 1;

                $built[] = [
                    'id' => (int) $option->id,
                    'letter' => $letter,
                    'text' => (string) ($option->answer ?? ''),
                    'is_correct' => $isCorrect,
                ];

                if ($isCorrect) {
                    $correctLetters[] = $letter;
                }
            }

            $kind = $correctLetters !== [] ? self::KIND_OBJECTIVE : self::KIND_SUBJECTIVE;
            $kind === self::KIND_OBJECTIVE ? $objective++ : $subjective++;

            $questions[] = [
                'question_no' => $questionNo,
                'question_id' => (int) $row->id,
                'question_type' => trim((string) ($row->question_type ?? '')),
                'question_title' => $this->plainText((string) ($row->question_title ?? '')),
                'kind' => $kind,
                'max_marks' => $maxMarks,
                'multiple_answer' => (int) ($row->multiple_answer ?? 0) === 1,
                'options' => $built,
                'correct_letters' => $correctLetters,
                'model_answer' => $this->plainText((string) ($row->answer ?? '')),
            ];
        }

        if ($questions === []) {
            throw new RuntimeException('None of the questions on this paper could be loaded.');
        }

        return [
            'questions' => $questions,
            'total_marks' => round($totalMarks, 2),
            'objective_count' => $objective,
            'subjective_count' => $subjective,
        ];
    }

    /**
     * The question list as the reader is allowed to see it.
     *
     * Deliberately WITHOUT `correct_letters` and `model_answer`: the reader's
     * only job is to transcribe what the student wrote, and a model that has
     * been shown the right answer first is a model that drifts towards
     * transcribing the right answer. Scoring happens afterwards, against the
     * full key, where the student's transcription can no longer be influenced.
     *
     * @param  array<int,array<string,mixed>>  $questions
     */
    public function readerView(array $questions): array
    {
        return array_map(static fn (array $question) => [
            'question_no' => $question['question_no'],
            'kind' => $question['kind'],
            'max_marks' => $question['max_marks'],
            'multiple_answer' => $question['multiple_answer'],
            'question' => mb_substr($question['question_title'], 0, 300),
            'option_letters' => array_column($question['options'], 'letter'),
        ], $questions);
    }

    /** Question titles are stored as HTML by the rich-text authoring screens. */
    private function plainText(string $value): string
    {
        $value = preg_replace('/<br\s*\/?>/i', ' ', $value) ?? $value;
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
