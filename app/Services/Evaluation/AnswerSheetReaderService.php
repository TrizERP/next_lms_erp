<?php

namespace App\Services\Evaluation;

use App\Services\Homework\Exceptions\DocumentExtractionException;
use App\Services\Homework\GeminiClient;

/**
 * Reads one scanned answer sheet and returns three things: who the sheet
 * belongs to, what the student put against each question, and where on the
 * page each of those answers sits.
 *
 * One vision call does all three because they are one act of reading -- a
 * second pass over the same scan would cost twice and could disagree with
 * itself about which page an answer was on.
 *
 * This works for both sheet styles the module supports:
 *
 *  - an OMR/MCQ sheet, where an answer is a darkened bubble and what comes
 *    back is a letter; and
 *  - a written answer book, where an answer is a paragraph and what comes back
 *    is the transcription.
 *
 * It is told the question list (numbers, marks and, for objective questions,
 * which option letters exist) but never the marking key -- see
 * AnswerKeyService::readerView(). Scoring is a separate step against the full
 * key, so nothing the reader transcribes can be steered by knowing the answer.
 */
class AnswerSheetReaderService
{
    public function __construct(private readonly GeminiClient $gemini)
    {
    }

    /**
     * @param  array<int,array<string,mixed>>  $readerQuestions  From AnswerKeyService::readerView().
     * @param  array<string,mixed>  $paper  Paper meta, for the identity block hint.
     * @return array{student: array{roll_no:string, enrollment_no:string, name:string, confidence:float}, responses: array<int, array{question_no:int, attempted:bool, selected_options:array<int,string>, answer_text:string, page:int, box_2d: array{0:int,1:int,2:int,3:int}|null}>}
     *
     * @throws DocumentExtractionException
     */
    public function read(string $absolutePath, string $mimeType, array $readerQuestions, array $paper = []): array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new DocumentExtractionException("Answer sheet not found or unreadable: {$absolutePath}");
        }

        $bytes = @file_get_contents($absolutePath);

        if ($bytes === false) {
            throw new DocumentExtractionException("Unable to read the answer sheet file: {$absolutePath}");
        }

        $result = $this->call($this->buildPrompt($readerQuestions, $paper), $mimeType, $bytes);

        return $this->parse($result, $readerQuestions);
    }

    private function call(string $prompt, string $mimeType, string $bytes): string
    {
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
                'temperature' => 0.0,
                'maxOutputTokens' => 12000,
                'responseMimeType' => 'application/json',
            ]);
        } catch (\RuntimeException $exception) {
            // The client already phrases its failures for a teacher, and
            // `failure_reason` keeps only the first 250 characters, so
            // prefixing it with plumbing pushes the useful half off the end.
            throw new DocumentExtractionException($exception->getMessage(), 0, $exception);
        } catch (\Throwable $exception) {
            throw new DocumentExtractionException('Reading the answer sheet failed: ' . $exception->getMessage(), 0, $exception);
        }

        return $result['text'];
    }

    private function buildPrompt(array $readerQuestions, array $paper): string
    {
        $context = trim(implode(' / ', array_filter([
            (string) ($paper['paper_name'] ?? ''),
            (string) ($paper['standard_name'] ?? ''),
            (string) ($paper['subject_name'] ?? ''),
        ])));

        $lines = [];

        foreach ($readerQuestions as $question) {
            $letters = $question['option_letters'] ?? [];

            if ($question['kind'] === AnswerKeyService::KIND_OBJECTIVE && $letters !== []) {
                $shape = 'OBJECTIVE, options ' . implode('/', $letters)
                    . ($question['multiple_answer'] ? ' (more than one may be marked)' : ' (exactly one)');
            } else {
                $shape = 'WRITTEN answer';
            }

            $lines[] = sprintf(
                'Q%d [%s, %s marks]: %s',
                $question['question_no'],
                $shape,
                rtrim(rtrim(number_format((float) $question['max_marks'], 2, '.', ''), '0'), '.') ?: '0',
                $question['question'] !== '' ? $question['question'] : '(question text not available)'
            );
        }

        $questionList = implode("\n", $lines);
        $contextLine = $context !== '' ? "This sheet is for: {$context}.\n" : '';

        return <<<PROMPT
This file is a scan or photo of ONE student's answer sheet for a school exam.
It may be an OMR/MCQ sheet with darkened bubbles, a written answer book, or a
paper that mixes both.

{$contextLine}
The paper has these questions, in this order:
{$questionList}

Do two things.

1. IDENTITY. Find the student's details in the header/identity block of the
   sheet -- typically a roll number, a GR/enrollment number and a name, either
   handwritten in boxes or bubbled in a grid. Report exactly what is written.
   Do not invent a name or a number that is not on the sheet; leave the field
   as an empty string instead. `confidence` is how sure you are that you read
   the identity block correctly, 0 to 100.

2. RESPONSES. For EVERY question number listed above, report what the student
   put.
   - For an OBJECTIVE question, `selected_options` holds the option letters the
     student marked (a darkened bubble, a tick, a circled letter, or a letter
     written in an answer box). Use the letters exactly as listed above. If the
     student marked nothing, return an empty array and set `attempted` false.
     If a bubble is smudged or two are marked when only one is allowed, report
     every letter you can see marked -- do not pick a favourite.
   - For a WRITTEN question, `answer_text` is the student's answer transcribed
     as written, including their own spelling and any working. Transcribe what
     is there; do not correct it, complete it or improve it.
   - `page` is the 1-based page of this file the answer appears on.
   - `box_2d` is a bounding box tightly around the STUDENT'S ANSWER (not the
     printed question), normalized to a 0-1000 scale for that page, in
     [ymin, xmin, ymax, xmax] order. Omit `box_2d` for an answer whose position
     you cannot pin down, rather than guessing -- but never omit the answer.
   - Include an entry for a question the student left blank, with
     `attempted` false. A missing entry and a blank answer are different
     things and we need to tell them apart.

Respond with ONLY a single valid JSON object -- no markdown fences, no
commentary -- in exactly this shape:
{
  "student": {
    "roll_no": "<as written, or empty string>",
    "enrollment_no": "<as written, or empty string>",
    "name": "<as written, or empty string>",
    "confidence": <number 0-100>
  },
  "responses": [
    {
      "question_no": <integer>,
      "attempted": <true|false>,
      "selected_options": ["<letter>"],
      "answer_text": "<transcription, or empty string>",
      "page": <integer>,
      "box_2d": [<ymin>, <xmin>, <ymax>, <xmax>]
    }
  ]
}
PROMPT;
    }

    /** @return array<string,mixed> */
    private function parse(string $rawText, array $readerQuestions): array
    {
        $decoded = json_decode($this->stripMarkdownFences($rawText), true);

        if (! is_array($decoded) || ! isset($decoded['responses']) || ! is_array($decoded['responses'])) {
            throw new DocumentExtractionException('The answer sheet reader returned a payload that could not be parsed as JSON.');
        }

        $student = is_array($decoded['student'] ?? null) ? $decoded['student'] : [];
        $known = [];

        foreach ($readerQuestions as $question) {
            $known[(int) $question['question_no']] = $question;
        }

        $responses = [];

        foreach ($decoded['responses'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $questionNo = (int) ($row['question_no'] ?? 0);

            // A response for a question this paper does not have is a
            // hallucinated row, not a mark -- drop it rather than let it
            // create an answer record with nothing to score against.
            if ($questionNo < 1 || ! isset($known[$questionNo]) || isset($responses[$questionNo])) {
                continue;
            }

            $letters = $this->normalizeLetters(
                $row['selected_options'] ?? null,
                $known[$questionNo]['option_letters'] ?? []
            );
            $text = trim((string) ($row['answer_text'] ?? ''));
            $attempted = array_key_exists('attempted', $row)
                ? (bool) $row['attempted']
                : ($letters !== [] || $text !== '');

            $responses[$questionNo] = [
                'question_no' => $questionNo,
                'attempted' => $attempted && ($letters !== [] || $text !== ''),
                'selected_options' => $letters,
                'answer_text' => $text,
                'page' => max(1, (int) ($row['page'] ?? 1)),
                'box_2d' => $this->normalizeBox($row['box_2d'] ?? null),
            ];
        }

        if ($responses === []) {
            throw new DocumentExtractionException('No answers could be found on this sheet. Check that the scan is the right way up and in focus.');
        }

        ksort($responses);

        return [
            'student' => [
                'roll_no' => trim((string) ($student['roll_no'] ?? '')),
                'enrollment_no' => trim((string) ($student['enrollment_no'] ?? '')),
                'name' => trim((string) ($student['name'] ?? '')),
                'confidence' => max(0.0, min(100.0, (float) ($student['confidence'] ?? 0))),
            ],
            'responses' => array_values($responses),
        ];
    }

    /**
     * Option letters, uppercased and kept only where the paper actually has
     * that option -- a reader that answers "E" to a four-option question has
     * misread the sheet, and scoring a letter that does not exist would quietly
     * mark the student wrong for the reader's mistake.
     *
     * @param  array<int,string>  $allowed
     * @return array<int,string>
     */
    private function normalizeLetters(mixed $value, array $allowed): array
    {
        if (! is_array($value)) {
            return [];
        }

        $allowed = array_map('strtoupper', $allowed);
        $letters = [];

        foreach ($value as $entry) {
            $letter = strtoupper(trim((string) $entry));

            if ($letter === '' || ($allowed !== [] && ! in_array($letter, $allowed, true))) {
                continue;
            }

            $letters[$letter] = true;
        }

        $letters = array_keys($letters);
        sort($letters);

        return $letters;
    }

    /** @return array{0:int,1:int,2:int,3:int}|null */
    private function normalizeBox(mixed $box): ?array
    {
        if (! is_array($box) || count($box) !== 4) {
            return null;
        }

        $values = array_map(static fn ($value) => (int) $value, array_values($box));

        foreach ($values as $value) {
            if ($value < 0 || $value > 1000) {
                return null;
            }
        }

        // [ymin, xmin, ymax, xmax] -- ymin must sit above ymax, xmin left of xmax.
        if ($values[0] >= $values[2] || $values[1] >= $values[3]) {
            return null;
        }

        return $values;
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
