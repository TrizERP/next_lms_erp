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

    public function __construct(private GeminiClient $gemini)
    {
    }

    /**
     * A copy of this service whose model calls resolve the `evaluation_ai` configuration for one school.
     */
    public function forInstitute(int|string|null $subInstituteId): static
    {
        $copy = clone $this;
        $copy->gemini = $this->gemini->forInstitute($subInstituteId);

        return $copy;
    }

    /**
     * @param  array<int,array<string,mixed>>  $keyQuestions  From AnswerKeyService::forPaper()['questions'].
     * @param  array<int,array<string,mixed>>  $responses     From AnswerSheetReaderService::read()['responses'].
     * @param  bool  $rethrowTransient  A queued caller that can retry later passes true, so a busy provider
     *                                  or rate limit during the written-answer pass is raised to it instead
     *                                  of leaving every written answer ungraded. Callers with nothing to
     *                                  wait on (homework) keep the default and get the graceful fallback.
     * @return array{answers: array<int,array<string,mixed>>, ai_total: float, max_marks: float, needs_review: bool}
     */
    public function score(array $keyQuestions, array $responses, bool $rethrowTransient = false): array
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
                // What the sheet itself calls this question; null when the reader could not say.
                'question_label' => $response['question_label'] ?? null,
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
            $answers = $this->scoreSubjective($answers, $subjective, $rethrowTransient);
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
    private function scoreSubjective(array $answers, array $subjective, bool $rethrowTransient = false): array
    {
        $items = [];

        foreach ($subjective as $questionNo => $question) {
            // No key, no mark. A question with no model answer and no rubric has
            // nothing to be graded against, and asking a model to "judge on
            // subject correctness alone" is inventing a marking scheme. It goes
            // to the teacher unmarked, flagged, instead.
            if (trim((string) $question['model_answer']) === '') {
                $answers[$questionNo] = $this->leftForTeacher(
                    $answers[$questionNo],
                    'No model answer is saved for this question -- please mark it by hand.'
                );

                continue;
            }

            $items[] = [
                'question_no' => $questionNo,
                'question' => $question['question_title'],
                'model_answer' => $question['model_answer'],
                'rubric' => $question['rubric'] ?? null,
                'max_marks' => (float) $question['max_marks'],
                'student_answer' => (string) $answers[$questionNo]['detected_answer'],
            ];
        }

        if ($items === []) {
            return $answers;
        }

        try {
            $graded = $this->callGrader($items);
        } catch (EvaluationException $exception) {
            // When the caller can retry later, hand it the transient failure
            // rather than a sheet of ungraded written answers.
            if ($rethrowTransient && TransientAiFailure::is($exception)) {
                throw $exception->getPrevious() ?? $exception;
            }

            // A failed grading pass must not take the objective half of the
            // sheet down with it. Those marks are already computed and sound.
            // The written questions simply arrive at the teacher ungraded,
            // which is the same place they would have started from anyway.
            Log::warning('Subjective grading pass failed; written answers left for the teacher', [
                'message' => $exception->getMessage(),
            ]);

            foreach ($items as $item) {
                $answers[$item['question_no']] = $this->leftForTeacher(
                    $answers[$item['question_no']],
                    'Could not be graded automatically -- please mark this one by hand.'
                );
            }

            return $answers;
        }

        foreach ($items as $item) {
            $questionNo = $item['question_no'];

            // The grader answered, but not for this one. Left as it was it would
            // read "nothing written, 100% sure" -- a silent zero. Flag it.
            if (! isset($graded[$questionNo])) {
                $answers[$questionNo] = $this->leftForTeacher(
                    $answers[$questionNo],
                    'The AI did not return a mark for this answer -- please mark it by hand.'
                );

                continue;
            }

            $row = $graded[$questionNo];
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
     * An answer the AI did not mark: zero, zero confidence, and a remark saying
     * why. Zero confidence is what puts it at the top of the review queue
     * (`needs_attention`), and status `wrong` rather than `unattempted` keeps it
     * from being mistaken for a question the student skipped.
     *
     * @param  array<string,mixed>  $answer
     * @return array<string,mixed>
     */
    private function leftForTeacher(array $answer, string $remark): array
    {
        $answer['status'] = 'wrong';
        $answer['ai_marks'] = 0.0;
        $answer['ai_confidence'] = 0.0;
        $answer['ai_remark'] = $remark;

        return $answer;
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

        // Items reaching here always have a model answer: scoreSubjective() sends
        // a question with no key to the teacher instead of asking a model to
        // judge it with nothing to judge it against.
        foreach ($items as $item) {
            $blocks[] = "### Question {$item['question_no']} (out of {$item['max_marks']} marks)\n"
                . "QUESTION: {$item['question']}\n"
                . "MODEL ANSWER: {$item['model_answer']}\n"
                . $this->rubricBlock($item['rubric'] ?? null)
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
- Where a question lists MARKING POINTS, award one mark per point the student
  has actually made (their wording may differ; the ACCEPT list shows the kind of
  alternative that counts and the REJECT list shows near-answers that do not).
  Keywords are supporting evidence only -- a keyword on its own, with no
  understanding around it, earns nothing.
- Where a question lists COMMON ERRORS, an answer that makes that error must not
  score above the stated ceiling.
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

        $decoded = null;

        // Two goes at getting parseable JSON: model output at low temperature is
        // near-deterministic, so the retry nudges the temperature rather than
        // repeating the identical request. A provider failure is not retried
        // here -- the client already retries those, and the job retries after.
        foreach ([0.1, 0.3] as $temperature) {
            try {
                $result = $this->gemini->generateContent([
                    ['text' => $prompt],
                ], [
                    'temperature' => $temperature,
                    'maxOutputTokens' => 8000,
                    'responseMimeType' => 'application/json',
                ]);
            } catch (\Throwable $exception) {
                throw new EvaluationException($exception->getMessage(), 0, $exception);
            }

            $decoded = json_decode($this->stripMarkdownFences($result['text']), true);

            if (is_array($decoded) && is_array($decoded['results'] ?? null)) {
                break;
            }

            $decoded = null;

            // Cut off at the token limit: asking again for the same length hits
            // the same limit, so say so now rather than spend a second call.
            if (($result['finish_reason'] ?? '') === 'MAX_TOKENS') {
                throw new EvaluationException('The written answers were too long to grade in one pass; the AI response was cut short.');
            }
        }

        if ($decoded === null) {
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

    /**
     * The per-mark criteria, accepted alternatives, rejected near-answers,
     * keywords and capped errors for one question, or nothing when the key is
     * plain text. Only what the question itself carries -- never anything made up.
     *
     * @param  array<string,mixed>|null  $rubric  From QuestionRubric::parse(), when structured.
     */
    private function rubricBlock(?array $rubric): string
    {
        if ($rubric === null) {
            return '';
        }

        $lines = [];

        foreach ((array) ($rubric['marking_points'] ?? []) as $index => $point) {
            $line = 'MARKING POINT ' . ($index + 1) . ' (1 mark): ' . $point['criterion'];

            if ($point['accept'] !== []) {
                $line .= ' | ACCEPT: ' . implode('; ', $point['accept']);
            }

            if ($point['reject'] !== []) {
                $line .= ' | REJECT: ' . implode('; ', $point['reject']);
            }

            $lines[] = $line;
        }

        $keywords = [];

        foreach ((array) ($rubric['keywords'] ?? []) as $keyword) {
            $keywords[] = $keyword['synonyms'] === []
                ? $keyword['term']
                : $keyword['term'] . ' (' . implode(', ', $keyword['synonyms']) . ')';
        }

        if ($keywords !== []) {
            $lines[] = 'KEYWORDS: ' . implode('; ', $keywords);
        }

        foreach ((array) ($rubric['common_errors'] ?? []) as $error) {
            $lines[] = 'COMMON ERROR (at most ' . $error['mark_ceiling'] . ' marks): ' . $error['erroneous_answer'];
        }

        return $lines === [] ? '' : implode("\n", $lines) . "\n";
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
