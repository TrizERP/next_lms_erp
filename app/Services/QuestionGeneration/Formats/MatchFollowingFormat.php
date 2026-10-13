<?php

namespace App\Services\QuestionGeneration\Formats;

use App\Services\QuestionGeneration\AbstractQuestionFormat;
use App\Services\QuestionGeneration\FormatSchema;

/**
 * Match the following -- four to six left/right pairs.
 *
 * STORAGE (catalogue: lms_question_type_id 2): no `answer_master` rows.
 *   - `answer.pairs`  [{left, right}] in answer-key order. THE source of truth: the
 *     bank API exposes it and matchPairs() in lib/h5p/question-bank-h5p-map.ts
 *     prefers it to parsing text. Parsing is what this avoids -- matchPairs() splits
 *     on "-", ":", "=", "->" and the dashes, so a half such as "x-axis" cannot
 *     survive a round trip through a string.
 *   - `question_title`  the instruction plus both columns, the right column in a
 *     shuffled, deterministic order, so a consumer that shows only the stem (exam
 *     paper, homework) still has a complete, answerable item
 *   - `answer.model_answer`  the readable key in the house style: "a-(ii), b-(i), ..."
 *
 * PLAYS AS: H5P Memory game (default -- pairs the two columns and needs no geometry),
 * Flash cards, Course presentation.
 */
class MatchFollowingFormat extends AbstractQuestionFormat
{
    protected const BATCH_SIZE = 3;
    protected const TEMPERATURE = 0.5;

    public const MIN_PAIRS = 4;
    public const MAX_PAIRS = 6;

    private const ROMAN = ['i', 'ii', 'iii', 'iv', 'v', 'vi'];

    public function code(): string
    {
        return 'match_following';
    }

    public function label(): string
    {
        return 'Match the Following';
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
        return 'match-the-following';
    }

    public function subTypeFor(string $level): string
    {
        return 'Match the Following';
    }

    public function constructionRules(int $total): string
    {
        $common = $this->commonRules('"Tests <ability_ref> at <bloom_level>; pairs separate <misconception>."');
        $min = self::MIN_PAIRS;
        $max = self::MAX_PAIRS;

        return <<<RULES
question_title -- ONE instruction line only, for example "Match each term in Column A with its
  description in Column B." Do not list the items in it: the two columns are added for you.

answer.pairs -- JSON array of {$min} to {$max} objects {"left": "...", "right": "..."}.
- ONE-TO-ONE: every left matches exactly one right, and every right fits exactly one left.
  No right may also fit another left. No two lefts or two rights may be the same.
- left is a term, name, symbol or item (at most 8 words). right is its description, definition,
  value or counterpart (at most 12 words). Both at most 80 characters.
- The right must NOT contain the left's key word: that gives the pair away.
- At least one pair must separate two things students confuse (see `misconceptions[]`).
- Use plain text. Do not number or letter the items, and do not use the characters
  "|" or ";" inside them.
- Write the pairs in any order: the right column is shuffled for display.

answer.model_answer -- the key for the displayed columns is written for you; give a short
  restatement of the pairing, for example "Heart - pumps blood; Lung - gas exchange".
answer.sub_type -- always "Match the Following".
points -- always 1.

{$common}

EXAMPLE ROW (structure only; do not reuse its content)
{
  "question_title": "Match each term in Column A with its description in Column B.",
  "description": "Tests vocabulary of integer products at Remember; pairs separate unlike and like signs.",
  "subconcept": "<exact knowledge string>",
  "points": 1, "multiple_answer": 0, "hint_text": null,
  "learning_outcome": ["<exact outcome>"],
  "answer": {
    "v": "ans-2.0", "question_type": "match_following", "sub_type": "Match the Following",
    "bloom_level": "Remember", "dok_level": 1, "difficulty": "Easy", "stimulus": null,
    "estimated_time_seconds": 90,
    "pairs": [
      {"left": "Positive x positive", "right": "Positive product"},
      {"left": "Negative x negative", "right": "Positive result from like signs"},
      {"left": "Positive x negative", "right": "Negative product"},
      {"left": "Zero x any integer", "right": "Always zero"}
    ],
    "model_answer": "Positive x positive - positive product; Negative x negative - positive result; Positive x negative - negative product; Zero x any integer - zero",
    "explanation": "The sign of a product depends on whether the signs of the factors are alike or unlike.",
    "remediation": "Reteach the four sign combinations side by side.",
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
                'sub_type' => ['const' => 'Match the Following'],
                'pairs' => [
                    'type' => 'array', 'minItems' => self::MIN_PAIRS, 'maxItems' => self::MAX_PAIRS,
                    'items' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'required' => ['left', 'right'],
                        'properties' => [
                            'left' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
                            'right' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
                        ],
                    ],
                ],
                'model_answer' => ['type' => 'string', 'minLength' => 5],
                'estimated_time_seconds' => ['type' => 'integer', 'minimum' => 30, 'maximum' => 300],
            ],
            ['sub_type', 'pairs', 'model_answer'],
            ['const' => 1],
            10,
            250
        );
    }

    public function validateRow(array $row): ?string
    {
        $ans = $this->answer($row);
        $instruction = $this->text($row['question_title'] ?? '');

        if (mb_strlen($instruction) < 10) {
            return 'the instruction is missing or too short';
        }
        if ($this->words($instruction) > 30) {
            return 'the instruction is too long: it must not list the items';
        }

        $pairs = $ans['pairs'] ?? null;
        if (!is_array($pairs) || count($pairs) < self::MIN_PAIRS || count($pairs) > self::MAX_PAIRS) {
            return 'pairs must hold ' . self::MIN_PAIRS . ' to ' . self::MAX_PAIRS . ' entries';
        }

        $lefts = [];
        $rights = [];
        foreach ($pairs as $pair) {
            $left = is_array($pair) ? $this->text($pair['left'] ?? '') : '';
            $right = is_array($pair) ? $this->text($pair['right'] ?? '') : '';

            if ($left === '' || $right === '') {
                return 'a pair has an empty side';
            }
            if (mb_strlen($left) > 80 || mb_strlen($right) > 80) {
                return 'a pair side is longer than 80 characters';
            }
            if ($this->words($left) > 10 || $this->words($right) > 14) {
                return 'a pair side is too wordy for a matching card';
            }
            if (preg_match('/[|;]/', $left . $right)) {
                return 'a pair contains a reserved character (| ;)';
            }
            if (mb_strtolower($left) === mb_strtolower($right)) {
                return 'a pair matches a side with itself';
            }

            $lefts[] = mb_strtolower($left);
            $rights[] = mb_strtolower($right);
        }

        if (count(array_unique($lefts)) !== count($lefts)) {
            return 'two lefts are the same';
        }
        if (count(array_unique($rights)) !== count($rights)) {
            return 'two rights are the same';
        }
        if (array_intersect($lefts, $rights) !== []) {
            return 'a right is also a left, so the matching is ambiguous';
        }

        if (mb_strlen($this->text($ans['model_answer'] ?? '')) < 5) {
            return 'model_answer is required';
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

        $pairs = [];
        foreach ($ans['pairs'] as $pair) {
            $pairs[] = ['left' => $this->text($pair['left']), 'right' => $this->text($pair['right'])];
        }

        $order = $this->displayOrder($pairs);

        // Where each pair's right-hand side landed in the shuffled column.
        $position = array_flip($order);
        $keyParts = [];
        $columnA = [];
        $columnB = [];
        foreach ($pairs as $i => $pair) {
            $letter = chr(ord('a') + $i);
            $columnA[] = "({$letter}) {$pair['left']}";
            $keyParts[] = "{$letter}-(" . self::ROMAN[$position[$i]] . ')';
        }
        foreach ($order as $shown => $pairIndex) {
            $columnB[] = '(' . self::ROMAN[$shown] . ') ' . $pairs[$pairIndex]['right'];
        }

        $instruction = $this->text($row['question_title']);

        $ans['pairs'] = $pairs;
        $ans['sub_type'] = 'Match the Following';
        // The model's own restatement is kept for the teacher; the key readers use
        // is the one that matches the columns actually shown.
        $ans['model_answer_note'] = $this->text($ans['model_answer'] ?? '');
        $ans['model_answer'] = implode(', ', $keyParts);

        $row['answer'] = $ans;
        $row['question_title'] = $instruction
            . "\nColumn A: " . implode('; ', $columnA)
            . "\nColumn B: " . implode('; ', $columnB);

        return $row;
    }

    /**
     * Which pair's right-hand side sits at each display position: a deterministic
     * shuffle (so the same pairs always render the same way), never the identity
     * order, which would hand the key to anyone reading the columns.
     *
     * @param  list<array{left: string, right: string}>  $pairs
     * @return list<int> display position => pair index
     */
    protected function displayOrder(array $pairs): array
    {
        $seed = md5(implode('|', array_column($pairs, 'left')));
        $order = array_keys($pairs);

        usort($order, fn (int $a, int $b) => strcmp(md5($seed . $pairs[$a]['right']), md5($seed . $pairs[$b]['right'])));

        if ($order === array_keys($pairs)) {
            array_push($order, array_shift($order));
        }

        return array_values($order);
    }
}
