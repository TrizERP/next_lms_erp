<?php

namespace App\Services\QuestionGeneration\Formats;

use App\Services\QuestionGeneration\AbstractQuestionFormat;
use App\Services\QuestionGeneration\FormatSchema;

/**
 * Numerical response -- a worked problem whose answer is one number.
 *
 * STORAGE (catalogue: lms_question_type_id 2): no `answer_master` rows.
 *   - `question_title`  the problem, stating the unit the answer is wanted in
 *   - `answer.model_answer`  the bare number ("-12", "3.5", "3/4"), never with a
 *     unit or a sentence: it is the string a learner types into a blank and the
 *     string PAL compares against
 *   - `answer.unit`, `answer.solution_steps`  the workings, for the teacher and
 *     for feedback; not read by the player
 *
 * PLAYS AS: H5P Fill in the blanks (default -- the typed value is marked against
 * the stored answer), Drag the words, Flash cards, Course presentation. The
 * Arithmetic quiz generates its own sums and cannot carry a bank question, which is
 * why the map in lib/h5p/question-bank-h5p-map.ts substitutes Blanks (and says so).
 *
 * MARKS: 1 to 3 (catalogue default 2). Problems with more working than that are
 * Short or Long answers, not numerical responses.
 */
class NumericalFormat extends AbstractQuestionFormat
{
    protected const BATCH_SIZE = 5;
    protected const TEMPERATURE = 0.3;

    /** A plain number or a simple fraction: -12, 3.5, 0.25, 3/4. No units, words or thousands separators. */
    public const NUMBER_PATTERN = '/^-?\d+(?:\.\d+)?(?:\/\d+)?$/';

    public function code(): string
    {
        return 'numerical';
    }

    public function label(): string
    {
        return 'Numerical Response';
    }

    public function fallbackQuestionTypeId(): int
    {
        return 2;
    }

    public function defaultMarks(): int
    {
        return 2;
    }

    public function marksRange(): array
    {
        return [1, 3];
    }

    public function allowedBloomLevels(): array
    {
        // A calculation applies a rule; it is not a recall or a bare comprehension item.
        return ['Apply', 'Analyze', 'Evaluate'];
    }

    public function taskLabel(): string
    {
        return 'numerical-response';
    }

    public function subTypeFor(string $level): string
    {
        return 'Numerical';
    }

    public function constructionRules(int $total): string
    {
        $common = $this->commonRules('"Tests <ability_ref> at <bloom_level>, <points> marks; common error: <misconception>."');

        return <<<RULES
question_title (the problem)
- A self-contained problem with ONE numerical answer. Give every number the solver needs
  IN THE PROBLEM. Numbers may be introduced only as data stated in the problem itself; every
  relation, rule or formula needed to solve it must come from the CONCEPT SLICE.
- Say the unit the answer is wanted in (for example "Give your answer in metres.").
- Apply and Analyze problems embed a scenario from `real_world_applications` or `evidence`.
- <= 60 words. Indian English, SI units. The answer must be exactly checkable.

answer.model_answer -- THE NUMBER ONLY, as plain text: "-12", "3.5", "0.25" or "3/4".
- No unit, no words, no "=", no thousands separators, no trailing full stop.
answer.unit -- the unit as text (for example "m"), or null when the answer is a pure number.
answer.solution_steps -- JSON array of 2 to 6 short strings: the working, one step each.
  The last step must arrive at model_answer.
answer.sub_type -- always "Numerical".
points -- 1 to 3, as the QUOTA TABLE gives for the item's level.

Wrong numbers are the commonest failure here: recompute every answer before you emit it.

{$common}

EXAMPLE ROW (structure only; do not reuse its content)
{
  "question_title": "A diver descends 3 m every minute. By how many metres does her depth change in 4 minutes? Give the change as a signed integer.",
  "description": "Tests multiplying integers with unlike signs at Apply, 2 marks; common error: sign dropped.",
  "subconcept": "<exact knowledge string>",
  "points": 2, "multiple_answer": 0, "hint_text": "Is a descent a positive or a negative change?",
  "learning_outcome": ["<exact outcome>"],
  "answer": {
    "v": "ans-2.0", "question_type": "numerical", "sub_type": "Numerical",
    "bloom_level": "Apply", "dok_level": 2, "difficulty": "Medium", "stimulus": null,
    "estimated_time_seconds": 90,
    "model_answer": "-12", "unit": "m",
    "solution_steps": ["Descent is a negative change: -3 m per minute.", "4 x (-3) = -12."],
    "explanation": "Each minute changes depth by -3 m, so four minutes change it by 4 x (-3) = -12 m.",
    "remediation": "Reteach that a positive times a negative is negative.",
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
                'sub_type' => ['const' => 'Numerical'],
                'model_answer' => ['type' => 'string', 'pattern' => '^-?\\d+(?:\\.\\d+)?(?:/\\d+)?$'],
                'unit' => ['type' => ['string', 'null'], 'maxLength' => 20],
                'solution_steps' => [
                    'type' => 'array', 'minItems' => 2, 'maxItems' => 6,
                    'items' => ['type' => 'string', 'minLength' => 3],
                ],
                'estimated_time_seconds' => ['type' => 'integer', 'minimum' => 30, 'maximum' => 600],
            ],
            ['sub_type', 'model_answer', 'unit', 'solution_steps'],
            ['type' => 'integer', 'minimum' => 1, 'maximum' => 3],
            15,
            500
        );
    }

    public function validateRow(array $row): ?string
    {
        $ans = $this->answer($row);
        $title = $this->text($row['question_title'] ?? '');

        if (mb_strlen($title) < 15) {
            return 'the problem is missing or too short';
        }
        if ($this->words($title) > 90) {
            return 'the problem is longer than 90 words';
        }

        $number = $this->text($ans['model_answer'] ?? '');
        if (!preg_match(self::NUMBER_PATTERN, $number)) {
            return 'model_answer must be the number only (for example -12, 3.5 or 3/4)';
        }
        if (str_contains($number, '/') && (int) explode('/', $number)[1] === 0) {
            return 'model_answer divides by zero';
        }

        $unit = $ans['unit'] ?? null;
        if ($unit !== null && (!is_string($unit) || mb_strlen($unit) > 20)) {
            return 'unit must be short text or null';
        }

        $steps = $ans['solution_steps'] ?? null;
        if (!is_array($steps) || count($steps) < 2 || count($steps) > 6) {
            return 'solution_steps must hold 2 to 6 steps';
        }
        foreach ($steps as $step) {
            if ($this->text($step) === '') {
                return 'a solution step is empty';
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

        $ans['model_answer'] = $this->text($ans['model_answer']);
        $ans['unit'] = is_string($ans['unit'] ?? null) && trim($ans['unit']) !== '' ? trim($ans['unit']) : null;
        $ans['solution_steps'] = array_values(array_map(fn ($s) => $this->text($s), $ans['solution_steps']));
        $ans['sub_type'] = 'Numerical';

        $row['answer'] = $ans;

        return $row;
    }
}
