<?php

namespace App\Services\QuestionGeneration\Formats;

use App\Services\QuestionGeneration\FormatSchema;

/**
 * Drag the words -- a sentence with gaps, a bank of words to drag into them, and a few
 * words that belong in none.
 *
 * It is a fill-in-the-blank with distractors, so it IS FillBlankFormat for everything the
 * two share (the drawn gaps, the answer limits, the leak check) and adds only what Drag
 * the words needs: the distractor bank.
 *
 * STORAGE (catalogue: lms_question_type_id 1, so it is MCQ-typed, but, exactly like
 * fill_blank, it carries NO answer_master rows: the answer key is the envelope).
 *   - `question_title`       the sentence, each gap drawn as ______
 *   - `answer.answers`       the word for each gap, in order
 *   - `answer.model_answer`  the answers joined with "; " (what blanksPassage reads)
 *   - `answer.distractors`   the words that fit no gap; exposed to the client, where the
 *     map turns them into the player's plain comma-separated bank
 *
 * PLAYS AS: H5P Drag the words (default), Fill in the blanks, Flash cards, Course
 * presentation -- decided by the frontend map, not here.
 */
class DragTextFormat extends FillBlankFormat
{
    protected const BATCH_SIZE = 8;

    /** Chips are small: a draggable word is at most this many words / characters. */
    public const MAX_WORD_PHRASE_WORDS = 3;
    public const MAX_WORD_PHRASE_CHARS = 30;

    public const MIN_DISTRACTORS = 1;
    public const MAX_DISTRACTORS = 4;

    public function code(): string
    {
        return 'drag_text';
    }

    public function label(): string
    {
        return 'Drag the Words';
    }

    public function fallbackQuestionTypeId(): int
    {
        return 1;
    }

    public function defaultMarks(): int
    {
        return 2;
    }

    public function taskLabel(): string
    {
        return 'drag-the-words';
    }

    public function subTypeFor(string $level): string
    {
        return 'Drag the Words';
    }

    public function constructionRules(int $total): string
    {
        $common = $this->commonRules('"Tests <ability_ref> at <bloom_level>; distractors target <misconception>."');
        $minD = self::MIN_DISTRACTORS;
        $maxD = self::MAX_DISTRACTORS;

        return <<<RULES
question_title (the sentence with its gaps)
- One sentence, or two short ones, with 1 to 3 gaps. Draw EVERY gap as exactly six underscores: ______
- Use no other run of three or more dots, hyphens or underscores anywhere in the sentence.
- Each gap replaces ONE key term taken from the slice -- a word or a short phrase, never a clause.
  The words around a gap must leave exactly one correct choice from the word bank.
- Do not start the sentence with a gap. <= 35 words in total.
- The answers must not already appear elsewhere in the sentence.

answer.answers -- JSON array of strings, one per gap, in the order the gaps appear.
- Each answer is at most 3 words and 30 characters.
- Never put ";" "|" "*" or "," inside an answer.
- The number of answers EQUALS the number of gaps.
answer.distractors -- JSON array of {$minD} to {$maxD} words that fit NO gap.
- A distractor must be plausible to a student holding a slice misconception, yet wrong in every
  gap. Prefer a `misconceptions[]` term or a near-miss from a different knowledge item.
- At most 3 words and 30 characters each; never ";" "|" "*" or ","; none equal to an answer;
  no two the same.
answer.model_answer -- the answers joined with "; " in order.
answer.sub_type -- always "Drag the Words".
points -- always 2.

{$common}

EXAMPLE ROW (structure only; do not reuse its content)
{
  "question_title": "Plants make their own food by ______, using sunlight, water and ______ from the air.",
  "description": "Tests the inputs of photosynthesis at Remember; distractors target 'plants take in oxygen'.",
  "subconcept": "<exact knowledge string>",
  "points": 2, "multiple_answer": 0, "hint_text": null,
  "learning_outcome": ["<exact outcome>"],
  "answer": {
    "v": "ans-2.0", "question_type": "drag_text", "sub_type": "Drag the Words",
    "bloom_level": "Remember", "dok_level": 1, "difficulty": "Easy", "stimulus": null,
    "estimated_time_seconds": 45,
    "answers": ["photosynthesis", "carbon dioxide"], "distractors": ["respiration", "oxygen", "nitrogen"],
    "model_answer": "photosynthesis; carbon dioxide",
    "explanation": "Plants combine carbon dioxide and water using light in the process of photosynthesis.",
    "remediation": "Reteach which gas plants take in and which they release.",
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
                'sub_type' => ['const' => 'Drag the Words'],
                'answers' => [
                    'type' => 'array', 'minItems' => 1, 'maxItems' => 3,
                    'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => self::MAX_WORD_PHRASE_CHARS],
                ],
                'distractors' => [
                    'type' => 'array', 'minItems' => self::MIN_DISTRACTORS, 'maxItems' => self::MAX_DISTRACTORS,
                    'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => self::MAX_WORD_PHRASE_CHARS],
                ],
                'model_answer' => ['type' => 'string', 'minLength' => 1],
                'estimated_time_seconds' => ['type' => 'integer', 'minimum' => 15, 'maximum' => 180],
            ],
            ['sub_type', 'answers', 'distractors', 'model_answer'],
            ['const' => 2],
            15,
            300
        );
    }

    public function validateRow(array $row): ?string
    {
        // The gaps, the answers' limits, the leak check, the explanation: all shared.
        $reason = parent::validateRow($row);
        if ($reason !== null) {
            return $reason;
        }

        $ans = $this->answer($row);
        $answers = array_map(fn ($a) => mb_strtolower($this->text($a)), $ans['answers']);

        foreach ($ans['answers'] as $answer) {
            $answer = $this->text($answer);
            if ($this->words($answer) > self::MAX_WORD_PHRASE_WORDS || mb_strlen($answer) > self::MAX_WORD_PHRASE_CHARS) {
                return 'an answer is too long to drag (max ' . self::MAX_WORD_PHRASE_WORDS . ' words / ' . self::MAX_WORD_PHRASE_CHARS . ' characters)';
            }
            if (str_contains($answer, ',')) {
                return 'an answer contains a comma';
            }
        }

        $distractors = $ans['distractors'] ?? null;
        if (!is_array($distractors) || count($distractors) < self::MIN_DISTRACTORS || count($distractors) > self::MAX_DISTRACTORS) {
            return 'distractors must hold ' . self::MIN_DISTRACTORS . ' to ' . self::MAX_DISTRACTORS . ' words';
        }

        $seen = [];
        foreach ($distractors as $distractor) {
            $word = $this->text($distractor);
            $key = mb_strtolower($word);

            if ($word === '') {
                return 'a distractor is empty';
            }
            if (preg_match('/[;|*,]/', $word)) {
                return 'a distractor contains a reserved character (; | * ,)';
            }
            if ($this->words($word) > self::MAX_WORD_PHRASE_WORDS || mb_strlen($word) > self::MAX_WORD_PHRASE_CHARS) {
                return 'a distractor is too long to drag';
            }
            if (in_array($key, $answers, true)) {
                return 'a distractor repeats an answer';
            }
            if (isset($seen[$key])) {
                return 'two distractors are the same';
            }
            $seen[$key] = true;
        }

        return null;
    }

    public function prepareRow(array $row): array
    {
        $row = parent::prepareRow($row);
        $ans = $row['answer'];

        // Alternatives belong to typing a blank; a dragged word has none.
        unset($ans['accepted_alternatives']);
        $ans['sub_type'] = 'Drag the Words';
        $ans['distractors'] = array_values(array_map(fn ($d) => $this->text($d), $ans['distractors']));

        $row['answer'] = $ans;

        return $row;
    }
}
