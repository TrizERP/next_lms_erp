<?php

namespace App\Services\QuestionGeneration\Formats;

use App\Services\QuestionGeneration\AbstractQuestionFormat;
use App\Services\QuestionGeneration\FormatSchema;

/**
 * True / false -- one statement, a verdict, a reason.
 *
 * STORAGE (catalogue: lms_question_type_id 1, so it is MCQ-typed):
 *   - `question_title`  the statement
 *   - `answer.model_answer`  exactly "True" or "False" -- what the typed-answer
 *     readers (PAL, QuestionBankSource's 'answer' requirement, trueFalseAnswer())
 *     compare, and why it must be the bare word and not "True. Because ..."
 *   - two `answer_master` rows, True and False, one flagged correct -- built by
 *     prepareRow() as `answer.options`, so the rows the MCQ-path readers need exist
 *     without the model having to write options it would only get wrong
 *
 * PLAYS AS: H5P True/False (default), Single choice set, Course presentation,
 * Flash cards -- decided by lib/h5p/question-bank-h5p-map.ts, not here.
 */
class TrueFalseFormat extends AbstractQuestionFormat
{
    protected const BATCH_SIZE = 10;
    protected const TEMPERATURE = 0.4;

    public function code(): string
    {
        return 'true_false';
    }

    public function label(): string
    {
        return 'True / False';
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
        // A verdict on a statement is recall, comprehension, application or
        // analysis. Evaluate / Create need an argued judgement a bare True/False
        // cannot carry.
        return ['Remember', 'Understand', 'Apply', 'Analyze'];
    }

    public function taskLabel(): string
    {
        return 'true/false';
    }

    public function subTypeFor(string $level): string
    {
        return 'True/False';
    }

    public function constructionRules(int $total): string
    {
        $common = $this->commonRules('"Tests <ability_ref> at <bloom_level>; the false statements target <misconception>."');
        $half = (int) ceil($total / 2);

        return <<<RULES
question_title (the statement)
- ONE declarative statement. Never a question, never an instruction. Must not end with "?".
- Self-contained: judgeable as true or false without any other text.
- <= 35 words. No double negatives. Avoid the absolutes "always", "never", "all", "none", "only".
- Apply and Analyze statements embed a scenario drawn from `real_world_applications` or
  `evidence`. Remember and Understand statements must not.

BALANCE
- Across this response, at most {$half} statements may share the same verdict. Mix True and False.

TRUE statements
- Restate a `knowledge_items` entry in new words or a new setting. Do not copy it verbatim.

FALSE statements
- Build each from a `misconceptions[]` entry (preferred) or from a knowledge item altered
  so that it is wrong in exactly one respect. A student holding the misconception should
  judge it TRUE. Put the misconception in `answer.misconception_refs`.
- Never make a statement false by a trivial wording trick, a typo or an unrelated fact.

answer.model_answer -- EXACTLY the word "True" or "False". Nothing else: no punctuation,
                     no explanation. The explanation belongs in `answer.explanation`.
answer.explanation -- 2-3 sentences. For a FALSE statement, state the correct fact.
answer.sub_type -- always "True/False".
Do NOT write options. They are generated from `model_answer`.
points -- always 1.

{$common}

EXAMPLE ROW (structure only; do not reuse its content)
{
  "question_title": "A diver descending 3 m every minute is 12 m deeper after 4 minutes, so the change in depth is positive 12 m.",
  "description": "Tests sign of a product at Apply; the false statement targets 'a negative times a positive is positive'.",
  "subconcept": "<exact knowledge string>",
  "points": 1, "multiple_answer": 0, "hint_text": "Think about which direction a descent moves.",
  "learning_outcome": ["<exact outcome>"],
  "answer": {
    "v": "ans-2.0", "question_type": "true_false", "sub_type": "True/False",
    "bloom_level": "Apply", "dok_level": 2, "difficulty": "Medium", "stimulus": null,
    "estimated_time_seconds": 30, "model_answer": "False",
    "explanation": "Descending is a movement in the negative direction, so 4 x (-3) = -12 and the change is negative 12 m.",
    "remediation": "Reteach that the product of a positive and a negative integer is negative.",
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
                'sub_type' => ['const' => 'True/False'],
                'model_answer' => ['enum' => ['True', 'False']],
                'estimated_time_seconds' => ['type' => 'integer', 'minimum' => 15, 'maximum' => 120],
            ],
            ['sub_type', 'model_answer'],
            ['const' => 1],
            10,
            400
        );
    }

    public function validateRow(array $row): ?string
    {
        $ans = $this->answer($row);
        $title = $this->text($row['question_title'] ?? '');

        if ($title === '' || mb_strlen($title) < 10) {
            return 'the statement is missing or too short';
        }
        if (str_ends_with($title, '?')) {
            return 'a true/false item must be a statement, not a question';
        }
        if ($this->words($title) > 45) {
            return 'the statement is longer than 45 words';
        }

        $verdict = strtolower($this->text($ans['model_answer'] ?? ''));
        if (!in_array($verdict, ['true', 'false'], true)) {
            return 'model_answer must be exactly "True" or "False"';
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
        $isTrue = strtolower($this->text($ans['model_answer'] ?? '')) === 'true';
        $explanation = $this->text($ans['explanation'] ?? '');

        $ans['model_answer'] = $isTrue ? 'True' : 'False';
        $ans['sub_type'] = 'True/False';
        $ans['statement_truth'] = $isTrue;
        $ans['correct_option'] = $isTrue ? 'A' : 'B';
        $ans['options'] = [
            [
                'label' => 'A', 'text' => 'True', 'is_correct' => $isTrue,
                'distractor_type' => $isTrue ? 'correct' : 'plausible',
                'rationale' => $isTrue ? $explanation : '',
            ],
            [
                'label' => 'B', 'text' => 'False', 'is_correct' => !$isTrue,
                'distractor_type' => !$isTrue ? 'correct' : 'plausible',
                'rationale' => !$isTrue ? $explanation : '',
            ],
        ];

        $row['answer'] = $ans;

        return $row;
    }
}
