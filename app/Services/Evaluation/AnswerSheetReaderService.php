<?php

namespace App\Services\Evaluation;

use App\Services\Homework\Exceptions\DocumentExtractionException;
use App\Services\Homework\Exceptions\ProviderBusyException;
use App\Services\Homework\Exceptions\RateLimitedException;
use App\Services\Homework\GeminiClient;
use Illuminate\Http\Client\ConnectionException;

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
     * @param  array<int,array<string,mixed>>  $readerQuestions  From AnswerKeyService::readerView().
     * @param  array<string,mixed>  $paper  Paper meta, for the identity block hint.
     * @return array{student: array{roll_no:string, enrollment_no:string, name:string, confidence:float}, responses: array<int, array{question_no:int, attempted:bool, selected_options:array<int,string>, answer_text:string, page:int, box_2d: array{0:int,1:int,2:int,3:int}|null}>}
     *
     * @throws DocumentExtractionException  The sheet itself could not be read.
     * @throws ProviderBusyException|RateLimitedException|ConnectionException  The provider could not answer right now; worth retrying later.
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

        $prompt = $this->buildPrompt($readerQuestions, $paper);

        // Two goes at getting parseable JSON. A reading that comes back as broken
        // JSON is usually a one-off; the second go raises the temperature a
        // little so it is not the identical request. A response cut off at the
        // token limit is NOT retried: asking again for the same length gets the
        // same truncation, and the teacher needs to be told rather than kept
        // waiting.
        foreach ([0.0, 0.2] as $attempt => $temperature) {
            $result = $this->call($prompt, $mimeType, $bytes, $temperature);

            try {
                return $this->parse($result['text'], $readerQuestions);
            } catch (DocumentExtractionException $exception) {
                if ($result['finish_reason'] === 'MAX_TOKENS') {
                    throw new DocumentExtractionException(
                        'This answer sheet has more writing than can be read in one pass, so the reading was cut short. '
                        . 'Try splitting it into smaller files.',
                        0,
                        $exception
                    );
                }

                if ($attempt === 1 || ! $this->isBadJson($exception)) {
                    throw $exception;
                }
            }
        }

        // Unreachable: the loop returns or throws on its second pass.
        throw new DocumentExtractionException('The answer sheet could not be read.');
    }

    /**
     * Reads ONLY who the sheet belongs to -- the first of the two stages a sheet
     * goes through, so that a teacher can confirm the student before any marking
     * is paid for.
     *
     * It asks for the identity details and nothing else: no question list, no
     * answers, a small output budget. The whole file is sent, because the details
     * are not always on page one (a cover sheet, the back of the answer book, a
     * header repeated further in), and `page` says where they were found so the
     * teacher can be taken straight there.
     *
     * Identity is read from what is WRITTEN on the sheet. The prompt forbids
     * recognising a student from their handwriting or anything else, and the
     * matcher downstream only ever compares the text that came back.
     *
     * @param  array<string,mixed>  $paper  Paper meta, as a hint about what kind of sheet this is.
     * @return array{student: array{roll_no:string, enrollment_no:string, name:string, confidence:float}, page:int, multiple_students:bool}
     *
     * @throws DocumentExtractionException  The file could not be read or its reply parsed.
     * @throws ProviderBusyException|RateLimitedException|ConnectionException  The provider could not answer right now; worth retrying later.
     */
    public function readIdentity(string $absolutePath, string $mimeType, array $paper = []): array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new DocumentExtractionException("Answer sheet not found or unreadable: {$absolutePath}");
        }

        $bytes = @file_get_contents($absolutePath);

        if ($bytes === false) {
            throw new DocumentExtractionException("Unable to read the answer sheet file: {$absolutePath}");
        }

        $prompt = $this->buildIdentityPrompt($paper);

        foreach ([0.0, 0.2] as $attempt => $temperature) {
            $result = $this->call($prompt, $mimeType, $bytes, $temperature, 600);

            try {
                return $this->parseIdentity($result['text']);
            } catch (DocumentExtractionException $exception) {
                if ($attempt === 1 || ! $this->isBadJson($exception)) {
                    throw $exception;
                }
            }
        }

        // Unreachable: the loop returns or throws on its second pass.
        throw new DocumentExtractionException('The student details could not be read from this sheet.');
    }

    /** The one parse failure worth a second request: the payload was not usable JSON. */
    private function isBadJson(DocumentExtractionException $exception): bool
    {
        return str_contains($exception->getMessage(), 'could not be parsed as JSON');
    }

    /** @return array{text:string, finish_reason:string} */
    private function call(string $prompt, string $mimeType, string $bytes, float $temperature, int $maxOutputTokens = 12000): array
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
                'temperature' => $temperature,
                'maxOutputTokens' => $maxOutputTokens,
                'responseMimeType' => 'application/json',
            ]);
        } catch (ProviderBusyException|RateLimitedException|ConnectionException $transient) {
            // Not wrapped: these mean "try again shortly" (busy, rate limited,
            // timed out), and the job can only tell them from a genuinely
            // unreadable sheet if they arrive as themselves.
            throw $transient;
        } catch (\RuntimeException $exception) {
            // The client already phrases its failures for a teacher, and
            // `failure_reason` keeps only the first 250 characters, so
            // prefixing it with plumbing pushes the useful half off the end.
            throw new DocumentExtractionException($exception->getMessage(), 0, $exception);
        } catch (\Throwable $exception) {
            throw new DocumentExtractionException('Reading the answer sheet failed: ' . $exception->getMessage(), 0, $exception);
        }

        return [
            'text' => $result['text'],
            'finish_reason' => (string) ($result['finish_reason'] ?? ''),
        ];
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
                'Item %d [%s, %s marks]: %s',
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
The paper has these questions, in this order. "Item N" is OUR internal position
number for the question. It is NOT necessarily the number printed on the sheet:
a sheet may number its questions Q.1(A), Q.1(B), Q.1(C), Q.2, Q.3 ... in its
own way. Match each item to the sheet by reading the question text and options,
never by assuming the printed number equals N.
{$questionList}

Do two things.

1. IDENTITY. Find the student's details in the header/identity block of the
   sheet -- typically a roll number, a GR/enrollment number and a name, either
   handwritten in boxes or bubbled in a grid. They are usually on the first
   page but may be on a cover sheet or a later page, so look through the whole
   file. Report exactly what is written.
   Do not invent a name or a number that is not on the sheet; leave the field
   as an empty string instead. Never work out who the student is from their
   handwriting or anything other than the details written on the sheet.
   `confidence` is how sure you are that you read the identity block
   correctly, 0 to 100.

2. RESPONSES. For EVERY item listed above, report what the student put. Use the
   item's own number N as `question_no`.
   - For an OBJECTIVE question, `selected_options` holds the option letters the
     student marked (a darkened bubble, a tick, a circled letter, or a letter
     written in an answer box). Use the letters exactly as listed above. If the
     student marked nothing, return an empty array and set `attempted` false.
     If a bubble is smudged or two are marked when only one is allowed, report
     every letter you can see marked -- do not pick a favourite.
   - For a WRITTEN question, `answer_text` is the student's answer transcribed
     as written, including their own spelling and any working. Transcribe what
     is there; do not correct it, complete it or improve it.
   - `question_label` is the question number exactly as PRINTED or WRITTEN on the
     sheet beside that question or answer, for example "Q.1(A)" or "2". Copy
     what the sheet shows. If you cannot see a number for it, return an empty
     string. Never fill it in from the item number N, and never guess it from
     the order of the answers.
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
      "question_no": <integer, the item number N>,
      "question_label": "<number printed on the sheet, or empty string>",
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

    private function buildIdentityPrompt(array $paper): string
    {
        $context = trim(implode(' / ', array_filter([
            (string) ($paper['paper_name'] ?? ''),
            (string) ($paper['standard_name'] ?? ''),
            (string) ($paper['subject_name'] ?? ''),
        ])));

        $contextLine = $context !== '' ? "This sheet is for: {$context}.\n" : '';

        return <<<PROMPT
This file is a scan or photo of ONE student's answer sheet for a school exam.
{$contextLine}
Your only job is to read WHO the sheet belongs to. Ignore the questions and
the answers.

Look through EVERY page of the file. The student's details are often on the
first page or a cover sheet, but they can be on a later page, on the back of
the sheet, or repeated in a header. Use the page where they are clearest.

Report exactly what is written -- typically a roll number, a GR / enrollment /
admission number and the student's name, handwritten in boxes or on lines, or
bubbled in a grid.
- Do not invent or complete a name or a number. If a field is missing or you
  cannot read it, return an empty string for it.
- Never work out who the student is from their handwriting, the layout, the
  answers, or anything other than the identity details written on the sheet.
- Do not copy a name or number from the printed question paper, a worked
  example, a school stamp, or the examiner's or invigilator's marks.
- `confidence` is how sure you are that you read these details correctly,
  0 to 100.
- `page` is the 1-based page you found them on, or 0 if you found none.
- `multiple_students` is true ONLY if the file clearly contains details for
  more than one different student.

Respond with ONLY a single valid JSON object -- no markdown fences, no
commentary -- in exactly this shape:
{
  "roll_no": "<as written, or empty string>",
  "enrollment_no": "<as written, or empty string>",
  "name": "<as written, or empty string>",
  "confidence": <number 0-100>,
  "page": <integer>,
  "multiple_students": <true|false>
}
PROMPT;
    }

    /** @return array{student: array{roll_no:string, enrollment_no:string, name:string, confidence:float}, page:int, multiple_students:bool} */
    private function parseIdentity(string $rawText): array
    {
        $decoded = json_decode($this->stripMarkdownFences($rawText), true);

        if (! is_array($decoded)) {
            throw new DocumentExtractionException('The answer sheet reader returned a payload that could not be parsed as JSON.');
        }

        // Tolerate the details arriving under a "student" key, as the full read
        // returns them, as well as flat.
        $student = is_array($decoded['student'] ?? null) ? $decoded['student'] : $decoded;

        return [
            'student' => [
                'roll_no' => trim((string) ($student['roll_no'] ?? '')),
                'enrollment_no' => trim((string) ($student['enrollment_no'] ?? '')),
                'name' => trim((string) ($student['name'] ?? '')),
                'confidence' => max(0.0, min(100.0, (float) ($student['confidence'] ?? 0))),
            ],
            'page' => max(0, (int) ($student['page'] ?? $decoded['page'] ?? 0)),
            'multiple_students' => filter_var($decoded['multiple_students'] ?? $student['multiple_students'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
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
                'question_label' => QuestionLabel::normalise($row['question_label'] ?? null),
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

        // Two answers cannot both be Q.2: a repeated label means the reader confused them, so
        // neither is trusted and both fall back to "not read" rather than showing a guess.
        $labels = QuestionLabel::withoutDuplicates(array_map(static fn (array $row) => $row['question_label'], $responses));

        foreach ($responses as $questionNo => $row) {
            $responses[$questionNo]['question_label'] = $labels[$questionNo];
        }

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
