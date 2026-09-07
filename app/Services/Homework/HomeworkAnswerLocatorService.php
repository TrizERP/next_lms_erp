<?php

namespace App\Services\Homework;

use App\Services\Homework\Exceptions\DocumentExtractionException;

/**
 * Reads a student's submitted homework/assignment file (PDF, scanned
 * notebook PDF, or a mobile photo of a notebook page) and, for every
 * answer it can find, returns both the transcribed text AND where that
 * answer sits on the page — so HomeworkAnnotatedPdfService can draw a
 * mark directly next to it on the STUDENT'S OWN page, instead of a
 * separate typed report.
 *
 * Unlike HomeworkDocumentExtractionService (used for the teacher's
 * assignment file, where only the question text matters), this always
 * calls Gemini's vision input — a cheap pdfparser text-layer shortcut
 * would give us the words but never their position on the page.
 */
class HomeworkAnswerLocatorService
{
    public function __construct(private readonly GeminiClient $gemini)
    {
    }

    /**
     * @return array{answers: array<int, array{question_no:int, text:string, page:int, box_2d: array{0:int,1:int,2:int,3:int}|null}>, combined_text: string}
     *
     * @throws DocumentExtractionException
     */
    public function locateAnswers(string $absolutePath, string $mimeType): array
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new DocumentExtractionException("File not found or unreadable: {$absolutePath}");
        }

        $bytes = @file_get_contents($absolutePath);
        if ($bytes === false) {
            throw new DocumentExtractionException("Unable to read submission file: {$absolutePath}");
        }

        $prompt = <<<PROMPT
This file is a student's handwritten or printed homework submission — it may
be a scanned notebook page, a mobile photo of a notebook, or a typed PDF.

For every answer you can identify (numbered or in order of appearance),
transcribe it and report exactly where it is on the page, so a mark can be
drawn right next to it later.

Respond with ONLY a single valid JSON object (no markdown fences, no
commentary) in exactly this shape:
{
  "answers": [
    {
      "question_no": <integer, 1-based, in the order the questions appear>,
      "text": "<the student's answer, transcribed as written>",
      "page": <integer, 1-based page number this answer is on>,
      "box_2d": [<ymin>, <xmin>, <ymax>, <xmax>]
    }
  ]
}

box_2d is the bounding box tightly around the answer text (not the question
text), normalized to a 0-1000 scale relative to that page's width/height,
in [ymin, xmin, ymax, xmax] order. If you cannot determine a precise box for
an answer, omit "box_2d" for that entry rather than guessing wildly — do not
omit the answer itself.
PROMPT;

        try {
            $result = $this->gemini->generateContent([
                ['text' => $prompt],
                [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => base64_encode($bytes),
                    ],
                ],
            ], [
                'temperature' => 0.1,
                'maxOutputTokens' => 6000,
                'responseMimeType' => 'application/json',
            ]);
        } catch (\Throwable $exception) {
            throw new DocumentExtractionException('Answer location via Gemini failed: ' . $exception->getMessage(), 0, $exception);
        }

        return $this->parseAnswers($result['text']);
    }

    private function parseAnswers(string $rawText): array
    {
        $json = $this->stripMarkdownFences($rawText);
        $decoded = json_decode($json, true);

        if (!is_array($decoded) || !isset($decoded['answers']) || !is_array($decoded['answers'])) {
            throw new DocumentExtractionException('Gemini returned an answer-location payload that could not be parsed as JSON.');
        }

        $answers = [];
        $combinedLines = [];
        foreach ($decoded['answers'] as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $questionNo = (int) ($row['question_no'] ?? ($index + 1));
            $text = trim((string) ($row['text'] ?? ''));
            $page = max(1, (int) ($row['page'] ?? 1));
            $box = $this->normalizeBox($row['box_2d'] ?? null);

            $answers[] = [
                'question_no' => $questionNo,
                'text' => $text,
                'page' => $page,
                'box_2d' => $box,
            ];
            $combinedLines[] = "Q{$questionNo}: {$text}";
        }

        if (empty($answers)) {
            throw new DocumentExtractionException('No answers could be located on the submitted document.');
        }

        return [
            'answers' => $answers,
            'combined_text' => implode("\n", $combinedLines),
        ];
    }

    /** @return array{0:int,1:int,2:int,3:int}|null */
    private function normalizeBox(mixed $box): ?array
    {
        if (!is_array($box) || count($box) !== 4) {
            return null;
        }

        $values = array_map(static fn ($v) => (int) $v, array_values($box));
        foreach ($values as $value) {
            if ($value < 0 || $value > 1000) {
                return null;
            }
        }
        // [ymin, xmin, ymax, xmax] — ymin must be above ymax, xmin left of xmax.
        if ($values[0] >= $values[2] || $values[1] >= $values[3]) {
            return null;
        }

        return $values;
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
