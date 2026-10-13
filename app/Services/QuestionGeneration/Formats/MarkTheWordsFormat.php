<?php

namespace App\Services\QuestionGeneration\Formats;

use App\Services\QuestionGeneration\AbstractQuestionFormat;
use App\Services\QuestionGeneration\FormatSchema;

/**
 * Mark the words -- a short passage and an instruction saying which words to find in it.
 *
 * STORAGE (catalogue: lms_question_type_id 1, so it is MCQ-typed, but it carries NO
 * answer_master rows: the answer key is the envelope, as for fill_blank):
 *   - `question_title`       "<instruction>\n<passage>": a stem-only consumer (exam paper,
 *     homework, the bank card) has the whole task, instruction included
 *   - `answer.instruction`, `answer.passage`   the two halves, kept apart so the validator
 *     can prove no answer sits in the instruction
 *   - `answer.answers`       the words to mark: single words, each present in the passage
 *   - `answer.model_answer`  the answers joined with "; " (what the reader splits)
 *
 * THE READER MARKS THEM IN PLACE. A mark-the-words row has no drawn gap, so
 * `blanksPassage()` in lib/h5p/question-bank-h5p-map.ts would otherwise append the answers
 * to the end of the stem and refuse the row ("nothing to find"). For this code it instead
 * wraps every whole-word occurrence of each answer where it stands. The validator below
 * mirrors that reader: single-word answers, whole-word matching with the same token rule,
 * and at least four words left unmarked.
 *
 * PLAYS AS: H5P Mark the words (default), Flash cards, Course presentation.
 */
class MarkTheWordsFormat extends AbstractQuestionFormat
{
    protected const BATCH_SIZE = 8;
    protected const TEMPERATURE = 0.4;

    public const MIN_ANSWERS = 1;
    public const MAX_ANSWERS = 5;
    public const MIN_PASSAGE_WORDS = 20;
    public const MAX_PASSAGE_WORDS = 80;
    /** No answer may be more than this many occurrences, and marked words stay under this share. */
    public const MAX_OCCURRENCES_PER_ANSWER = 3;
    public const MAX_MARKED_SHARE = 0.4;

    /** The reader's own notion of a word: letters, digits, underscore, apostrophes and hyphen. */
    public const TOKEN_PATTERN = '/[\p{L}\p{N}_\'\x{2019}-]+/u';

    public function code(): string
    {
        return 'mark_the_words';
    }

    public function label(): string
    {
        return 'Mark the Words';
    }

    public function fallbackQuestionTypeId(): int
    {
        return 1;
    }

    public function defaultMarks(): int
    {
        return 2;
    }

    public function allowedBloomLevels(): array
    {
        return ['Remember', 'Understand', 'Apply'];
    }

    public function taskLabel(): string
    {
        return 'mark-the-words';
    }

    public function subTypeFor(string $level): string
    {
        return 'Mark the Words';
    }

    public function constructionRules(int $total): string
    {
        $common = $this->commonRules('"Tests <ability_ref> at <bloom_level>; the passage hides <misconception>."');
        $minW = self::MIN_PASSAGE_WORDS;
        $maxW = self::MAX_PASSAGE_WORDS;
        $maxA = self::MAX_ANSWERS;
        $maxO = self::MAX_OCCURRENCES_PER_ANSWER;

        return <<<RULES
question_title -- repeat the instruction. It is rebuilt from your instruction and passage either way.

answer.instruction -- ONE sentence telling the student exactly which words to mark, for example
  "Mark the word that names the gas the leaf releases." It must describe a property of the words; it must NOT contain
  any of the answers.
answer.passage -- {$minW} to {$maxW} words of connected prose (two to four sentences) built from the
  slice, containing the words to mark. Plain text only: no asterisks, no markup, no line breaks.
answer.answers -- JSON array of 1 to {$maxA} words to mark.
- Each answer is a SINGLE word (letters, digits, apostrophe or hyphen; no spaces) that appears in the
  passage exactly as written, and no more than {$maxO} times.
- Every answer must really satisfy the instruction, and no unmarked word in the passage may satisfy it:
  there must be exactly one correct set of words.
- Marked words make up well under half of the passage. The rest are ordinary words.
- Never put ";" "|" "*" or "," inside an answer.
answer.model_answer -- the answers joined with "; ".
answer.sub_type -- always "Mark the Words".
points -- always 2.

{$common}

EXAMPLE ROW (structure only; do not reuse its content)
{
  "question_title": "Mark the word that names the gas the leaf releases.",
  "description": "Tests the product of photosynthesis at Remember; the passage also names a gas the leaf takes in.",
  "subconcept": "<exact knowledge string>",
  "points": 2, "multiple_answer": 0, "hint_text": null,
  "learning_outcome": ["<exact outcome>"],
  "answer": {
    "v": "ans-2.0", "question_type": "mark_the_words", "sub_type": "Mark the Words",
    "bloom_level": "Remember", "dok_level": 1, "difficulty": "Easy", "stimulus": null,
    "estimated_time_seconds": 60,
    "instruction": "Mark the word that names the gas the leaf releases.",
    "passage": "During photosynthesis a green leaf takes in carbon dioxide from the air and water from the soil. Using energy from sunlight, the leaf makes glucose and releases oxygen back into the air. Most of the glucose is stored as starch for later use.",
    "answers": ["oxygen"],
    "model_answer": "oxygen",
    "explanation": "Oxygen is the gas the leaf releases; carbon dioxide is the gas it takes in.",
    "remediation": "Reteach which substances in photosynthesis are gases and which are not.",
    "knowledge_refs": ["<exact knowledge>"], "ability_ref": null, "misconception_refs": ["<exact misconception>"]
  }
}
RULES;
    }

    public function responseSchema(): string
    {
        return FormatSchema::build(
            $this->code(),
            [
                'sub_type' => ['const' => 'Mark the Words'],
                'instruction' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 200],
                'passage' => ['type' => 'string', 'minLength' => 80, 'maxLength' => 700],
                'answers' => [
                    'type' => 'array', 'minItems' => self::MIN_ANSWERS, 'maxItems' => self::MAX_ANSWERS,
                    'items' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 30],
                ],
                'model_answer' => ['type' => 'string', 'minLength' => 1],
                'estimated_time_seconds' => ['type' => 'integer', 'minimum' => 20, 'maximum' => 240],
            ],
            ['sub_type', 'instruction', 'passage', 'answers', 'model_answer'],
            ['const' => 2],
            10,
            300
        );
    }

    /** @return list<string> lower-cased word tokens, by the same rule the reader uses */
    protected function tokens(string $text): array
    {
        preg_match_all(self::TOKEN_PATTERN, $text, $matches);

        return array_map(fn ($t) => mb_strtolower($t), $matches[0]);
    }

    public function validateRow(array $row): ?string
    {
        $ans = $this->answer($row);

        $instruction = $this->text($ans['instruction'] ?? '');
        $passage = $this->text($ans['passage'] ?? '');

        if (mb_strlen($instruction) < 10 || mb_strlen($instruction) > 200) {
            return 'instruction is missing or the wrong length';
        }
        if (preg_match('/[*\n\r]/', $passage)) {
            return 'passage must be plain text with no asterisks or line breaks';
        }

        $passageTokens = $this->tokens($passage);
        $count = count($passageTokens);
        if ($count < self::MIN_PASSAGE_WORDS || $count > self::MAX_PASSAGE_WORDS) {
            return "passage is {$count} words; it must be " . self::MIN_PASSAGE_WORDS . '-' . self::MAX_PASSAGE_WORDS;
        }

        $answers = $ans['answers'] ?? null;
        if (!is_array($answers) || count($answers) < self::MIN_ANSWERS || count($answers) > self::MAX_ANSWERS) {
            return 'answers must hold ' . self::MIN_ANSWERS . ' to ' . self::MAX_ANSWERS . ' words';
        }

        $instructionTokens = array_flip($this->tokens($instruction));
        $frequencies = array_count_values($passageTokens);
        $seen = [];
        $marked = 0;

        foreach ($answers as $answer) {
            $word = $this->text($answer);
            $tokens = $this->tokens($word);

            if ($word === '' || count($tokens) !== 1 || $tokens[0] !== mb_strtolower($word)) {
                return 'every answer must be a single plain word';
            }
            if (mb_strlen($word) < 2) {
                return 'an answer is too short to mark';
            }
            $key = $tokens[0];
            if (isset($seen[$key])) {
                return 'two answers are the same word';
            }
            $seen[$key] = true;

            $times = $frequencies[$key] ?? 0;
            if ($times === 0) {
                return "the answer \"{$word}\" does not appear in the passage as a whole word";
            }
            if ($times > self::MAX_OCCURRENCES_PER_ANSWER) {
                return "the answer \"{$word}\" appears {$times} times in the passage";
            }
            if (isset($instructionTokens[$key])) {
                return "the answer \"{$word}\" appears in the instruction, which would give it away";
            }
            $marked += $times;
        }

        if ($marked > $count * self::MAX_MARKED_SHARE) {
            return 'too much of the passage is marked: the rest must be ordinary words to choose between';
        }
        // The reader refuses a passage with fewer than four unmarked words.
        if ($count - $marked < 4) {
            return 'fewer than four words are left unmarked';
        }

        if (mb_strlen($this->text($ans['explanation'] ?? '')) < 30) {
            return 'explanation is required (at least 30 characters)';
        }
        if (empty($ans['knowledge_refs']) || !is_array($ans['knowledge_refs'])) {
            return 'knowledge_refs required';
        }

        return null;
    }

    public function prepareRow(array $row): array
    {
        $ans = $this->answer($row);
        $instruction = $this->text($ans['instruction']);
        $passage = $this->text($ans['passage']);
        $answers = array_values(array_map(fn ($a) => $this->text($a), $ans['answers']));

        $ans['instruction'] = $instruction;
        $ans['passage'] = $passage;
        $ans['answers'] = $answers;
        $ans['model_answer'] = implode('; ', $answers);
        $ans['sub_type'] = 'Mark the Words';

        $row['answer'] = $ans;
        // The stem carries the instruction AND the passage, so a stem-only consumer has the whole task.
        $row['question_title'] = $instruction . "\n" . $passage;

        return $row;
    }
}
