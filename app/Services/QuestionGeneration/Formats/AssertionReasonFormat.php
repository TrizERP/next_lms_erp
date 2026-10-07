<?php

namespace App\Services\QuestionGeneration\Formats;

use App\Services\QuestionGeneration\AbstractQuestionFormat;
use App\Services\QuestionGeneration\FormatSchema;

/**
 * Assertion & Reason -- the CBSE four-option verdict item.
 *
 * STORAGE (catalogue: lms_question_type_id 1, so it is MCQ-typed):
 *   - `answer.assertion` and `answer.reason`  kept apart, as the readers expect
 *     (getQuestionBank, shape() and PalQuestionForms::describe expose them)
 *   - `question_title`  both halves, "Assertion (A): ...\nReason (R): ...", so a
 *     consumer that shows only the stem (exam paper, homework) still has the whole
 *     item. composedStem() in lib/h5p/question-bank-h5p-map.ts does not append a
 *     half the stem already contains.
 *   - four `answer_master` rows with the fixed CBSE wording, one flagged correct
 *
 * THE MODEL DOES NOT WRITE THE OPTIONS OR PICK THE LETTER BLINDLY. It states three
 * facts -- is the assertion true, is the reason true, does the reason explain the
 * assertion -- and the letter must follow from them (validateRow checks it). That
 * catches the commonest failure of this item type: a key that contradicts its own
 * reasoning. "Both false" is not one of the four standard choices, so it is refused.
 *
 * PLAYS AS: H5P Single choice set (default), Course presentation, Flash cards.
 */
class AssertionReasonFormat extends AbstractQuestionFormat
{
    protected const BATCH_SIZE = 5;
    protected const TEMPERATURE = 0.5;

    /** The four standard CBSE choices, in order. */
    public const OPTIONS = [
        'A' => 'Both A and R are true, and R is the correct explanation of A',
        'B' => 'Both A and R are true, but R is not the correct explanation of A',
        'C' => 'A is true but R is false',
        'D' => 'A is false but R is true',
    ];

    public function code(): string
    {
        return 'assertion_reason';
    }

    public function label(): string
    {
        return 'Assertion & Reason';
    }

    public function fallbackQuestionTypeId(): int
    {
        return 1;
    }

    public function defaultMarks(): int
    {
        return 1;
    }

    public function allowedBloomLevels(): array
    {
        // The CBSE pattern (and the existing MCQ prompt) reserves the item for
        // reasoning about relationships, not recall.
        return ['Understand', 'Analyze', 'Evaluate'];
    }

    public function taskLabel(): string
    {
        return 'assertion-reason';
    }

    public function subTypeFor(string $level): string
    {
        return 'Assertion-Reason';
    }

    public function constructionRules(int $total): string
    {
        $common = $this->commonRules('"Tests <ability_ref> at <bloom_level>; targets <misconception>."');
        $perLetter = (int) ceil($total / 3);

        return <<<RULES
You write the ASSERTION and the REASON and three facts about them. You do NOT write the four
options: the standard CBSE options are attached for you.

answer.assertion -- one statement about the concept, <= 200 characters.
answer.reason -- one statement offered as its explanation, <= 200 characters.
- Both are single declarative sentences taken from, or built on, the slice's `knowledge_items`.
- Build the pair so that a slice misconception leads a student to a predictable wrong letter.

THE THREE FACTS (answer these truthfully; the letter must follow from them)
answer.assertion_true -- true if the assertion is correct.
answer.reason_true -- true if the reason is correct.
answer.reason_explains_assertion -- true only if the reason is the correct explanation of
                                    the assertion (meaningful only when both are true).

THE LETTER (answer.correct_option) follows from the facts:
  A  both true, and the reason explains the assertion
  B  both true, but the reason does not explain the assertion
  C  assertion true, reason false
  D  assertion false, reason true
Never make both statements false: no standard option covers it.

VARIETY
- No letter may be the key for more than {$perLetter} items in this response.
- Include both pairs where the reason explains the assertion and pairs where it does not.

question_title -- write exactly: "Assertion (A): <assertion>" on one line, then "Reason (R): <reason>"
                  on the next. It is rebuilt from your assertion and reason either way.
answer.sub_type -- always "Assertion-Reason".
points -- always 1.

{$common}

EXAMPLE ROW (structure only; do not reuse its content)
{
  "question_title": "Assertion (A): The product of 5 and -3 is negative.\\nReason (R): Multiplying numbers with unlike signs gives a negative result.",
  "description": "Tests the sign rule at Understand; targets 'a negative times a positive is positive'.",
  "subconcept": "<exact knowledge string>",
  "points": 1, "multiple_answer": 0, "hint_text": "Compare the reason with the rule for unlike signs.",
  "learning_outcome": ["<exact outcome>"],
  "answer": {
    "v": "ans-2.0", "question_type": "assertion_reason", "sub_type": "Assertion-Reason",
    "bloom_level": "Understand", "dok_level": 2, "difficulty": "Medium", "stimulus": null,
    "estimated_time_seconds": 60,
    "assertion": "The product of 5 and -3 is negative.",
    "reason": "Multiplying numbers with unlike signs gives a negative result.",
    "assertion_true": true, "reason_true": true, "reason_explains_assertion": true,
    "correct_option": "A",
    "explanation": "5 and -3 have unlike signs, so their product is negative, which is exactly what the reason states.",
    "remediation": "Reteach the sign rule and why it explains each individual product.",
    "knowledge_refs": ["<exact knowledge>"], "ability_ref": "<exact ability>", "misconception_refs": ["<exact misconception>"]
  }
}
RULES;
    }

    public function responseSchema(): string
    {
        return FormatSchema::build(
            $this->code(),
            [
                'sub_type' => ['const' => 'Assertion-Reason'],
                'assertion' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 200],
                'reason' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 200],
                'assertion_true' => ['type' => 'boolean'],
                'reason_true' => ['type' => 'boolean'],
                'reason_explains_assertion' => ['type' => 'boolean'],
                'correct_option' => ['enum' => ['A', 'B', 'C', 'D']],
                'estimated_time_seconds' => ['type' => 'integer', 'minimum' => 20, 'maximum' => 180],
            ],
            ['sub_type', 'assertion', 'reason', 'assertion_true', 'reason_true', 'reason_explains_assertion', 'correct_option'],
            ['const' => 1],
            10,
            500
        );
    }

    /** The letter the three facts imply, or null when they describe no standard option. */
    public function impliedOption(bool $assertionTrue, bool $reasonTrue, bool $explains): ?string
    {
        if ($assertionTrue && $reasonTrue) {
            return $explains ? 'A' : 'B';
        }
        if ($assertionTrue) {
            return 'C';
        }

        return $reasonTrue ? 'D' : null;
    }

    public function validateRow(array $row): ?string
    {
        $ans = $this->answer($row);

        $assertion = $this->text($ans['assertion'] ?? '');
        $reason = $this->text($ans['reason'] ?? '');

        if (mb_strlen($assertion) < 10 || mb_strlen($reason) < 10) {
            return 'assertion and reason are both required';
        }
        if (mb_strlen($assertion) > 250 || mb_strlen($reason) > 250) {
            return 'assertion or reason is too long';
        }
        if (mb_strtolower($assertion) === mb_strtolower($reason)) {
            return 'the reason repeats the assertion';
        }

        foreach (['assertion_true', 'reason_true', 'reason_explains_assertion'] as $fact) {
            if (!is_bool($ans[$fact] ?? null)) {
                return "{$fact} must be true or false";
            }
        }

        $implied = $this->impliedOption($ans['assertion_true'], $ans['reason_true'], $ans['reason_explains_assertion']);
        if ($implied === null) {
            return 'both statements false: no standard option covers it';
        }

        $given = $ans['correct_option'] ?? null;
        if (!in_array($given, array_keys(self::OPTIONS), true)) {
            return 'invalid correct_option';
        }
        if ($given !== $implied) {
            return "correct_option {$given} contradicts the stated facts (they imply {$implied})";
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
        $assertion = $this->text($ans['assertion']);
        $reason = $this->text($ans['reason']);
        $correct = $ans['correct_option'];
        $explanation = $this->text($ans['explanation'] ?? '');

        $options = [];
        foreach (self::OPTIONS as $label => $text) {
            $isCorrect = $label === $correct;
            $options[] = [
                'label' => $label,
                'text' => $text,
                'is_correct' => $isCorrect,
                'distractor_type' => $isCorrect ? 'correct' : 'plausible',
                'rationale' => $isCorrect ? $explanation : '',
            ];
        }

        $ans['assertion'] = $assertion;
        $ans['reason'] = $reason;
        $ans['sub_type'] = 'Assertion-Reason';
        $ans['options'] = $options;
        // The bank API returns the raw envelope as the model answer when this key is
        // absent, and a flash-card back or a teacher view would then show JSON.
        $ans['model_answer'] = $correct . ') ' . self::OPTIONS[$correct];

        $row['answer'] = $ans;
        // The stem carries both halves so a stem-only consumer has the whole item.
        $row['question_title'] = "Assertion (A): {$assertion}\nReason (R): {$reason}";

        return $row;
    }
}
