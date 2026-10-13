<?php

namespace App\Services\QuestionGeneration\Formats;

use App\Services\QuestionGeneration\AbstractQuestionFormat;
use App\Services\QuestionGeneration\FormatSchema;

/**
 * Fill in the blank -- a sentence with one to three gaps, each a short term or value.
 *
 * STORAGE (catalogue: lms_question_type_id 2): no `answer_master` rows.
 *   - `question_title`  the sentence, each gap drawn as a run of underscores
 *   - `answer.answers`  the answer for each gap, in order
 *   - `answer.model_answer`  the answers joined with "; " -- the one key
 *     blanksPassage() in lib/h5p/question-bank-h5p-map.ts reads (it splits on ";" and
 *     "|"), and what PAL and QuestionBankSource's 'answer' requirement look for
 *
 * THE VALIDATOR MIRRORS THAT READER on purpose. blanksPassage() treats any run of
 * three or more underscores, dots or hyphens as a blank, and convertibilityAs()
 * refuses an answer longer than six words or sixty characters. A row the reader
 * would reject, or miscount, is rejected here instead of being stored unplayable.
 *
 * PLAYS AS: H5P Fill in the blanks (default), Drag the words, Mark the words,
 * Flash cards, Course presentation.
 */
class FillBlankFormat extends AbstractQuestionFormat
{
    protected const BATCH_SIZE = 10;
    protected const TEMPERATURE = 0.4;

    /** The reader's own blank pattern (blanksPassage): underscores, dots or hyphens, three or more. */
    public const BLANK_PATTERN = '/(_{3,}|\.{3,}|-{3,})/';

    /** The reader's isShortSolution limits. */
    public const MAX_ANSWER_WORDS = 6;
    public const MAX_ANSWER_CHARS = 60;

    public function code(): string
    {
        return 'fill_blank';
    }

    public function label(): string
    {
        return 'Fill in the Blank';
    }

    public function fallbackQuestionTypeId(): int
    {
        return 2;
    }

    public function defaultMarks(): int
    {
        return 1;
    }

    public function allowedBloomLevels(): array
    {
        return ['Remember', 'Understand', 'Apply'];
    }

    public function taskLabel(): string
    {
        return 'fill-in-the-blank';
    }

    public function subTypeFor(string $level): string
    {
        return 'Fill in the Blank';
    }

    public function constructionRules(int $total): string
    {
        $common = $this->commonRules('"Tests <ability_ref> at <bloom_level>; blank tests <term>."');

        return <<<RULES
question_title (the sentence with its gaps)
- One sentence, or two short ones, with 1 to 3 gaps. Draw EVERY gap as exactly six underscores: ______
- Use no other run of three or more dots, hyphens or underscores anywhere in the sentence
  (no "...", no "---"): the player would read each as another gap.
- Each gap replaces ONE key term, name or value taken from the slice -- a word or short phrase,
  never a clause. Do not blank a function word ("the", "of", "is").
- The remaining words must leave exactly one defensible answer for each gap.
- Do not start the sentence with a gap. <= 35 words in total.
- The answer must not already appear elsewhere in the sentence.
- Apply-level sentences embed a short scenario from `real_world_applications` or `evidence`.

answer.answers -- JSON array of strings, one per gap, in the order the gaps appear.
- Each answer is at most 4 words and 40 characters.
- Never put ";" or "|" or "*" inside an answer.
- The number of answers EQUALS the number of gaps.
answer.accepted_alternatives -- JSON array with one entry per gap: an array of 0 to 3 other
  accepted spellings or synonyms for that gap (use [] when there are none).
answer.model_answer -- the answers joined with "; " in order (for example "referee; judge").
answer.sub_type -- always "Fill in the Blank".
points -- always 1.

{$common}

EXAMPLE ROW (structure only; do not reuse its content)
{
  "question_title": "When two integers have unlike signs, their product is always ______.",
  "description": "Tests the sign rule at Remember.",
  "subconcept": "<exact knowledge string>",
  "points": 1, "multiple_answer": 0, "hint_text": null,
  "learning_outcome": ["<exact outcome>"],
  "answer": {
    "v": "ans-2.0", "question_type": "fill_blank", "sub_type": "Fill in the Blank",
    "bloom_level": "Remember", "dok_level": 1, "difficulty": "Easy", "stimulus": null,
    "estimated_time_seconds": 30,
    "answers": ["negative"], "accepted_alternatives": [["less than zero"]], "model_answer": "negative",
    "explanation": "A positive number times a negative number gives a result below zero.",
    "remediation": "Reteach the sign rule for products with unlike signs.",
    "knowledge_refs": ["<exact knowledge>"], "ability_ref": null, "misconception_refs": []
  }
}
RULES;
    }

    public function responseSchema(): string
    {
        return FormatSchema::build(
            $this->code(),
            [
                'sub_type' => ['const' => 'Fill in the Blank'],
                'answers' => [
                    'type' => 'array', 'minItems' => 1, 'maxItems' => 3,
                    'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 60],
                ],
                'accepted_alternatives' => [
                    'type' => 'array', 'maxItems' => 3,
                    'items' => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string']],
                ],
                'model_answer' => ['type' => 'string', 'minLength' => 1],
                'estimated_time_seconds' => ['type' => 'integer', 'minimum' => 15, 'maximum' => 120],
            ],
            ['sub_type', 'answers', 'accepted_alternatives', 'model_answer'],
            ['const' => 1],
            15,
            300
        );
    }

    public function validateRow(array $row): ?string
    {
        $ans = $this->answer($row);
        $title = $this->text($row['question_title'] ?? '');

        if (mb_strlen($title) < 15) {
            return 'the sentence is missing or too short';
        }

        $answers = $ans['answers'] ?? null;
        if (!is_array($answers) || $answers === [] || count($answers) > 3) {
            return 'answers must hold 1 to 3 entries';
        }

        // The reader counts a blank as ANY run of 3+ underscores, dots or hyphens.
        $drawn = preg_match_all(self::BLANK_PATTERN, $title);
        if ($drawn !== count($answers)) {
            return "the sentence draws {$drawn} gap(s) but there are " . count($answers) . ' answer(s)';
        }
        if (preg_match('/^\s*(_{3,}|\.{3,}|-{3,})/', $title)) {
            return 'the sentence must not start with a gap';
        }

        $plainStem = preg_replace(self::BLANK_PATTERN, ' ', $title) ?? $title;

        foreach ($answers as $answer) {
            $answer = $this->text($answer);

            if ($answer === '') {
                return 'an answer is empty';
            }
            if (preg_match('/[;|*]/', $answer)) {
                return 'an answer contains a reserved character (; | *)';
            }
            if ($this->words($answer) > self::MAX_ANSWER_WORDS || mb_strlen($answer) > self::MAX_ANSWER_CHARS) {
                return 'an answer is too long to be a blank (max ' . self::MAX_ANSWER_WORDS . ' words / ' . self::MAX_ANSWER_CHARS . ' characters)';
            }
            if (mb_strlen($answer) >= 4 && mb_stripos($plainStem, $answer) !== false) {
                return 'an answer already appears in the sentence';
            }
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
        $answers = array_values(array_map(fn ($a) => $this->text($a), $ans['answers']));

        $ans['answers'] = $answers;
        $ans['model_answer'] = implode('; ', $answers);
        $ans['sub_type'] = 'Fill in the Blank';

        // Alternatives are kept for a future matcher; no reader uses them yet.
        $alternatives = is_array($ans['accepted_alternatives'] ?? null) ? $ans['accepted_alternatives'] : [];
        $ans['accepted_alternatives'] = array_values(array_map(
            fn ($set) => is_array($set) ? array_values(array_filter(array_map(fn ($a) => $this->text($a), $set))) : [],
            $alternatives
        ));

        $row['answer'] = $ans;

        return $row;
    }
}
