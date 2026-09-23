<?php

namespace App\Services\Evaluation;

use App\Services\Homework\Exceptions\EvaluationException;
use App\Services\Homework\GeminiClient;
use Illuminate\Support\Facades\Log;

/**
 * Turns what the reader saw into marks.
 *
 * The two halves of a paper are scored by two different things on purpose:
 *
 *  - OBJECTIVE questions are compared to the key in PHP. An MCQ has one right
 *    answer and comparing two short strings is not a job for a language model;
 *    doing it here makes the mark deterministic, free, instant and auditable,
 *    and it is the reason an OMR sheet can be trusted to total itself.
 *
 *  - SUBJECTIVE questions go to Gemini once per sheet, in a single batched
 *    call, graded against the teacher's own model answer and the marks the
 *    question carries. What comes back is a PROPOSAL with a confidence and a
 *    reason. It is written to `ai_marks`; only a teacher writes
 *    `teacher_marks`, and only `teacher_marks` reaches the gradebook.
 *
 * A subjective question scored below CONFIDENCE_REVIEW_THRESHOLD is flagged so
 * the review screen can sort it to the top. Nothing here ever finalises a mark.
 */
class AnswerSheetScoringService
{
    /** Below this, the review queue puts the question in front of a teacher first. */
    public const CONFIDENCE_REVIEW_THRESHOLD = 75.0;

    public function __construct(private readonly GeminiClient $gemini)
    {
    }

    /**
     * @param  array<int,array<string,mixed>>  $keyQuestions  From AnswerKeyService::forPaper()['questions'].
     * @param  array<int,array<string,mixed>>  $responses     From AnswerSheetReaderService::read()['responses'].
     * @return array{answers: array<int,array<string,mixed>>, ai_total: float, max_marks: float, needs_review: bool}
     */
    public function score(array $keyQuestions, array $responses): array
    {
        $byQuestion = [];

        foreach ($responses as $response) {
            $byQuestion[(int) $response['question_no']] = $response;
        }

        $answers = [];
        $subjective = [];

        foreach ($keyQuestions as $question) {
            $questionNo = (int) $question['question_no'];
            $response = $byQuestion[$questionNo] ?? null;

            $answer = [
                'question_no' => $questionNo,
                'question_id' => (int) $question['question_id'],
                'question_type' => (string) $question['question_type'],
                'is_objective' => $question['kind'] === AnswerKeyService::KIND_OBJECTIVE,
                'max_marks' => (float) $question['max_marks'],
                'selected_options' => $response['selected_options'] ?? [],
                'detected_answer' => (string) ($response['answer_text'] ?? ''),
                'expected_answer' => $this->expectedAnswer($question),
                'page' => (int) ($response['page'] ?? 1),
                'box_2d' => $response['box_2d'] ?? null,
                'ai_marks' => 0.0,
                'status' => 'unattempted',
                'ai_confidence' => 100.0,
                'ai_remark' => '',
            ];

            $attempted = (bool) ($response['attempted'] ?? false);

            if (! $attempted) {
                $answer['ai_remark'] = 'Nothing was written against this question.';
                $answers[$questionNo] = $answer;

                continue;
            }

            if ($answer['is_objective']) {
                $answers[$questionNo] = $this->scoreObjective($answer, $question);

                continue;
            }

            // Held back and sent as one batch below, so a 30-question paper is
            // one model call rather than thirty.
            $answers[$questionNo] = $answer;
            $subjective[$questionNo] = $question;
        }

        if ($subjective !== []) {
            $answers = $this->scoreSubjective($answers, $subjective);
        }

        ksort($answers);
        $answers = array_values($answers);

        $aiTotal = 0.0;
        $maxMarks = 0.0;
        $needsReview = false;

        foreach ($answers as $answer) {
            $aiTotal += (float) $answer['ai_marks'];
            $maxMarks += (float) $answer['max_marks'];

            if (! $answer['is_objective'] && $answer['status'] !== 'unattempted'
                && (float) $answer['ai_confidence'] < self::CONFIDENCE_REVIEW_THRESHOLD) {
                $needsReview = true;
            }
        }

        return [
            'answers' => $answers,
            'ai_total' => round($aiTotal, 2),
            'max_marks' => round($maxMarks, 2),
            // Any written answer at all means a teacher should look before this
            // becomes a result, whatever the model's confidence.
            'needs_review' => $needsReview || $subjective !== [],
        ];
    }

    /**
     * Exact-set comparison against the key.
     *
     * A single-answer question is right only on an exact match -- two bubbles
     * marked where one was allowed is wrong, the same as it would be on a
     * hand-checked OMR sheet. A multiple-answer question earns marks in
     * proportion to the correct options found, but only while nothing wrong was
     * selected; ticking everything must not score.
     */
    private function scoreObjective(array $answer, array $question): array
    {
        $correct = array_map('strtoupper', (array) $question['correct_letters']);
        $selected = array_map('strtoupper', (array) $answer['selected_options']);
        sort($correct);
        sort($selected);

        $answer['ai_confidence'] = 100.0;

        if ($selected === $correct) {
            $answer['ai_marks'] = (float) $question['max_marks'];
            $answer['status'] = 'correct';
            $answer['ai_remark'] = 'Matches the answer key.';

            return $answer;
        }

        $wrongPicks = array_diff($selected, $correct);
        $rightPicks = array_intersect($selected, $correct);

        if ($question['multiple_answer'] && $wrongPicks === [] && $rightPicks !== [] && $correct !== []) {
            $share = count($rightPicks) / count($correct);
            $answer['ai_marks'] = round((float) $question['max_marks'] * $share, 2);
            $answer['status'] = 'partially_correct';
            $answer['ai_remark'] = sprintf(
                'Marked %s; the key is %s.',
                implode(', ', $selected),
                implode(', ', $correct)
            );

            return $answer;
        }

        $answer['ai_marks'] = 0.0;
        $answer['status'] = 'wrong';
        $answer['ai_remark'] = sprintf(
            'Marked %s; the key is %s.',
            $selected === [] ? 'nothing readable' : implode(', ', $selected),
            implode(', ', $correct)
        );

        return $answer;
    }

    /**
     * @param  array<int,array<string,mixed>>  $answers
     * @param  array<int,array<string,mixed>>  $subjective
     * @return array<int,array<string,mixed>>
     */
    private function scoreSubjective(array $answers, array $subjective): array
    {
        $items = [];

        foreach ($subjective as $questionNo => $question) {
            $items[] = [
                'question_no' => $questionNo,
                'question' => $question['question_title'],
                'model_answer' => $question['model_answer'],
                'max_marks' => (float) $question['max_marks'],
                'student_answer' => (string) $answers[$questionNo]['detected_answer'],
            ];
        }

        try {
            $graded = $this->callGrader($items);
        } catch (EvaluationException $exception) {
            // A failed grading pass must not take the objective half of the
            // sheet down with it. Those marks are already computed and sound.
            // The written questions simply arrive at the teacher ungraded,
            // which is the same place they would have started from anyway.
            Log::warning('Subjective grading pass failed; written answers left for the teacher', [
                'message' => $exception->getMessage(),
            ]);

            foreach ($subjective as $questionNo => $question) {
                $answers[$questionNo]['status'] = 'wrong';
                $answers[$questionNo]['ai_marks'] = 0.0;
                $answers[$questionNo]['ai_confidence'] = 0.0;
                $answers[$questionNo]['ai_remark'] = 'Could not be graded automatically -- please mark this one by hand.';
            }

            return $answers;
        }

        foreach ($graded as $questionNo => $row) {
            if (! isset($answers[$questionNo])) {
                continue;
            }

            $max = (float) $answers[$questionNo]['max_marks'];
            $marks = max(0.0, min($max, (float) $row['marks']));

            $answers[$questionNo]['ai_marks'] = round($marks, 2);
            $answers[$questionNo]['ai_confidence'] = max(0.0, min(100.0, (float) $row['confidence']));
            $answers[$questionNo]['ai_remark'] = mb_substr(trim((string) $row['remark']), 0, 500);
            $answers[$questionNo]['status'] = $this->statusForMarks($marks, $max);
        }

        return $answers;
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @return array<int,array{marks:float, confidence:float, remark:string}>
     *
     * @throws EvaluationException
     */
    private function callGrader(array $items): array
    {
        $blocks = [];

        foreach ($items as $item) {
            $modelAnswer = $item['model_answer'] !== ''
                ? $item['model_answer']
                : '(no model answer was saved for this question -- judge the response on subject correctness alone)';

            $blocks[] = "### Question {$item['question_no']} (out of {$item['max_marks']} marks)\n"
                . "QUESTION: {$item['question']}\n"
                . "MODEL ANSWER: {$modelAnswer}\n"
                . "STUDENT WROTE: {$item['student_answer']}";
        }

        $body = implode("\n\n", $blocks);

        $prompt = <<<PROMPT
You are helping a schoolteacher mark written exam answers. For each question
below you are given the question, the teacher's model answer, and what the
student actually wrote (transcribed from their handwriting, so spelling and
transcription slips are possible).

Award marks the way a fair subject teacher would:
- Give credit for correct subject content, correct reasoning and correct
  working, even when the wording differs from the model answer.
- Award partial marks for a partly correct answer. Do not round to all-or-
  nothing.
- Do not take marks off for handwriting, spelling or grammar unless the
  question is itself about language.
- Never award more than the marks the question carries.
- `confidence` is how sure you are that a teacher would agree with your mark,
  0 to 100. Be honest and use low numbers: an answer that is hard to read, off
  topic, partly transcribed, or that argues a defensible point the model answer
  does not cover, deserves a LOW confidence so a human looks at it.
- `remark` is one short sentence a teacher could hand to the student saying
  what earned or lost the marks.

{$body}

Respond with ONLY a single valid JSON object -- no markdown fences, no
commentary -- in exactly this shape:
{
  "results": [
    {
      "question_no": <integer>,
      "marks": <number, 0 to that question's maximum>,
      "confidence": <number 0-100>,
      "remark": "<one short sentence>"
    }
  ]
}
PROMPT;

        try {
            $result = $this->gemini->generateContent([
                ['text' => $prompt],
            ], [
                'temperature' => 0.1,
                'maxOutputTokens' => 8000,
                'responseMimeType' => 'application/json',
            ]);
        } catch (\Throwable $exception) {
            throw new EvaluationException($exception->getMessage(), 0, $exception);
        }

        $decoded = json_decode($this->stripMarkdownFences($result['text']), true);

        if (! is_array($decoded) || ! is_array($decoded['results'] ?? null)) {
            throw new EvaluationException('The grader returned a payload that could not be parsed as JSON.');
        }

        $graded = [];

        foreach ($decoded['results'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $questionNo = (int) ($row['question_no'] ?? 0);

            if ($questionNo < 1) {
                continue;
            }

            $graded[$questionNo] = [
                'marks' => (float) ($row['marks'] ?? 0),
                'confidence' => (float) ($row['confidence'] ?? 0),
                'remark' => (string) ($row['remark'] ?? ''),
            ];
        }

        if ($graded === []) {
            throw new EvaluationException('The grader returned no usable marks.');
        }

        return $graded;
    }

    private function statusForMarks(float $marks, float $max): string
    {
        if ($max <= 0.0) {
            return 'unattempted';
        }

        if ($marks >= $max) {
            return 'correct';
        }

        return $marks > 0.0 ? 'partially_correct' : 'wrong';
    }

    private function expectedAnswer(array $question): string
    {
        if ($question['kind'] === AnswerKeyService::KIND_OBJECTIVE) {
            $letters = (array) $question['correct_letters'];
            $texts = [];

            foreach ($question['options'] as $option) {
                if (! empty($option['is_correct'])) {
                    $texts[] = $option['letter'] . '. ' . $option['text'];
                }
            }

            return $texts !== [] ? implode(' | ', $texts) : implode(', ', $letters);
        }

        return (string) $question['model_answer'];
    }

    private function stripMarkdownFences(string $text): string
    {
        $text = trim($text);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```[a-zA-Z]*\s*/', '', $text) ?? $text;
            $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        }

        return trim($text);
    }
}
