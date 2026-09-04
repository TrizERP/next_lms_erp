<?php

namespace App\Services\Homework;

use App\Services\Homework\Exceptions\EvaluationException;

/**
 * Calls Gemini to compare a student's answers against the original
 * assignment questions and returns a structured evaluation.
 */
class HomeworkEvaluationService
{
    public function __construct(private readonly GeminiClient $gemini)
    {
    }

    /**
     * @return array{overall_score:int,total_questions:int,percentage:float,results:array<int,array<string,mixed>>}
     *
     * @throws EvaluationException
     */
    public function evaluate(string $assignmentQuestionsText, string $studentAnswersText, ?string $studentLevel = null): array
    {
        $prompt = $this->buildPrompt($assignmentQuestionsText, $studentAnswersText, $studentLevel);

        try {
            $result = $this->gemini->generateContent([
                ['text' => $prompt],
            ], [
                'temperature' => 0.2,
                'maxOutputTokens' => 8000,
                'responseMimeType' => 'application/json',
            ]);
        } catch (\Throwable $exception) {
            throw new EvaluationException('Gemini evaluation call failed: ' . $exception->getMessage(), 0, $exception);
        }

        return $this->parseEvaluation($result['text']);
    }

    private function buildPrompt(string $questions, string $answers, ?string $studentLevel): string
    {
        $level = trim((string) $studentLevel) !== '' ? trim($studentLevel) : 'Not specified';

        return <<<PROMPT
You are grading a K-12 student's homework submission.

ORIGINAL ASSIGNMENT QUESTIONS (extracted from the teacher's uploaded PDF):
---
{$questions}
---

STUDENT ANSWERS (extracted from the student's uploaded submission):
---
{$answers}
---

Student level: {$level}

TASK:
For every question in the assignment:
1. Identify the question text.
2. Determine the expected answer.
3. Compare it against what the student wrote.
4. Classify the student's answer as exactly one of: "correct", "partially_correct", "wrong".
5. Write a short, encouraging, specific remark (max 25 words) explaining the classification.

If the student did not answer a question at all, classify it as "wrong" and say so in the remark.

Respond with ONLY a single valid JSON object (no markdown fences, no commentary) in exactly this shape:
{
  "overall_score": <integer, number of fully correct answers>,
  "total_questions": <integer, total number of questions found>,
  "percentage": <number, overall_score / total_questions * 100, rounded to 1 decimal>,
  "results": [
    {
      "question_no": <integer>,
      "question": "<question text>",
      "student_answer": "<student's answer, or empty string if missing>",
      "expected_answer": "<expected answer>",
      "status": "correct" | "partially_correct" | "wrong",
      "remarks": "<short remark>"
    }
  ]
}
PROMPT;
    }

    private function parseEvaluation(string $rawText): array
    {
        $json = $this->stripMarkdownFences($rawText);
        $decoded = json_decode($json, true);

        if (!is_array($decoded) || !isset($decoded['results']) || !is_array($decoded['results'])) {
            throw new EvaluationException('Gemini returned an evaluation payload that could not be parsed as JSON.');
        }

        $results = [];
        foreach ($decoded['results'] as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = strtolower((string) ($row['status'] ?? 'wrong'));
            if (!in_array($status, ['correct', 'partially_correct', 'wrong'], true)) {
                $status = 'wrong';
            }
            $results[] = [
                'question_no' => (int) ($row['question_no'] ?? ($index + 1)),
                'question' => (string) ($row['question'] ?? ''),
                'student_answer' => (string) ($row['student_answer'] ?? ''),
                'expected_answer' => (string) ($row['expected_answer'] ?? ''),
                'status' => $status,
                'remarks' => (string) ($row['remarks'] ?? ''),
            ];
        }

        $totalQuestions = (int) ($decoded['total_questions'] ?? count($results));
        $totalQuestions = $totalQuestions > 0 ? $totalQuestions : count($results);

        $overallScore = $decoded['overall_score'] ?? null;
        if (!is_numeric($overallScore)) {
            $overallScore = count(array_filter($results, fn ($row) => $row['status'] === 'correct'));
        }
        $overallScore = (int) $overallScore;

        $percentage = $decoded['percentage'] ?? null;
        if (!is_numeric($percentage)) {
            $percentage = $totalQuestions > 0 ? round(($overallScore / $totalQuestions) * 100, 1) : 0;
        }

        if (empty($results)) {
            throw new EvaluationException('Gemini evaluation returned no gradable questions.');
        }

        return [
            'overall_score' => $overallScore,
            'total_questions' => $totalQuestions,
            'percentage' => round((float) $percentage, 1),
            'results' => $results,
        ];
    }

    private function stripMarkdownFences(string $text): string
    {
        $text = trim($text);
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```[a-zA-Z]*\s*/', '', $text);
            $text = preg_replace('/\s*```$/', '', $text);
        }

        return trim($text);
    }
}
