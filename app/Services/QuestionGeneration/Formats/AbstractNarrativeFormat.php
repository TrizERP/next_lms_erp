<?php

namespace App\Services\QuestionGeneration\Formats;

use App\Services\QuestionGeneration\AbstractQuestionFormat;
use App\Services\QuestionGeneration\FormatSchema;

/**
 * The shared body of the three written-answer formats: Very Short, Short, Long.
 *
 * They differ only in length, marks and which Bloom levels suit them, so those are
 * the abstract hooks. The ENVELOPE is the one the legacy narrative generator has
 * always written (`model_answer`, one `marking_points` entry per mark, 4-8
 * `keywords`, `common_errors`), because the keyword-overlap auto-scorer and the
 * marking-point rubric read exactly that.
 *
 * STORAGE (catalogue: lms_question_type_id 2): no `answer_master` rows;
 * `answer.model_answer` carries the full-credit answer.
 *
 * PLAYS AS: the Written-answer player (EssayPlayer) -- it shows the prompt, takes a
 * response and reveals the model answer; it marks nothing because nothing here can
 * mark prose. Very Short and Short answers with a word-sized model answer also play
 * as Fill in the blanks / Flash cards, decided per row by the H5P map.
 */
abstract class AbstractNarrativeFormat extends AbstractQuestionFormat
{
    protected const TEMPERATURE = 0.6;

    /** Wording for the one-line "what this length looks like" in the rules. */
    abstract protected function lengthGuidance(): string;

    /** @return array{0: int, 1: int} inclusive word limits for model_answer */
    abstract protected function modelAnswerWords(): array;

    abstract protected function commandVerbHint(): string;

    abstract protected function exampleRow(): string;

    public function fallbackQuestionTypeId(): int
    {
        return 2;
    }

    public function subTypeFor(string $level): string
    {
        return $this->label();
    }

    public function taskLabel(): string
    {
        return strtolower($this->label());
    }

    public function constructionRules(int $total): string
    {
        $common = $this->commonRules('"Tests <ability_ref> at <bloom_level>, <points> marks; common error: <misconception>."');
        [$minWords, $maxWords] = $this->modelAnswerWords();
        [$minMarks, $maxMarks] = $this->marksRange();
        $sub = $this->label();
        $verbs = $this->commandVerbHint();
        $length = $this->lengthGuidance();
        $example = $this->exampleRow();

        return <<<RULES
These are {$sub} items. {$length}

question_title
- Open with a command verb that matches the Bloom level, taken from the `verb` field of the
  `abilities[]` entry you cite. {$verbs}
- The number of things demanded EQUALS `points`. A 3-mark item asks for three creditable
  elements. Never "some" or "a few".
- Apply level and above open with a 1-2 sentence scenario from `real_world_applications` or
  `evidence`. Recall items must not.

points -- between {$minMarks} and {$maxMarks}, as the QUOTA TABLE gives for the item's level.

answer.sub_type -- always "{$sub}".
answer.model_answer
- A full-credit student answer, {$minWords} to {$maxWords} words, in student voice.
- No meta-language ("The answer is...", "Students should..."). Slice content only.

answer.marking_points -- the load-bearing field. One entry per mark; the count EQUALS `points`.
    mark           always 1. Half-marks are not permitted.
    criterion      what the student must have written to earn it
    accept         2-4 acceptable alternative phrasings
    reject         1-2 near-answers that must NOT earn the mark
    knowledge_ref  the knowledge item this mark tests

answer.keywords -- 4 to 8 scoring keywords, each with `weight` (0.0-1.0) and `synonyms[]`.
  A keyword appearing in `question_title` is disqualified -- it rewards copying the question.

answer.common_errors -- for each related `misconceptions[]` entry: what the erroneous answer
  looks like, and the `mark_ceiling` it should receive.

answer.full_credit_threshold -- minimum marks to count as "mastered". Default ceil(0.75 x points).

{$common}

EXAMPLE ROW (structure only; do not reuse its content)
{$example}
RULES;
    }

    public function responseSchema(): string
    {
        [$minMarks, $maxMarks] = $this->marksRange();

        return FormatSchema::build(
            $this->code(),
            [
                'sub_type' => ['const' => $this->label()],
                'model_answer' => ['type' => 'string', 'minLength' => 2],
                'marking_points' => [
                    'type' => 'array', 'minItems' => 1, 'maxItems' => 6,
                    'items' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'required' => ['mark', 'criterion', 'accept', 'reject', 'knowledge_ref'],
                        'properties' => [
                            'mark' => ['const' => 1],
                            'criterion' => ['type' => 'string', 'minLength' => 5],
                            'accept' => ['type' => 'array', 'minItems' => 2, 'maxItems' => 4, 'items' => ['type' => 'string']],
                            'reject' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 2, 'items' => ['type' => 'string']],
                            'knowledge_ref' => ['type' => 'string'],
                        ],
                    ],
                ],
                'keywords' => [
                    'type' => 'array', 'minItems' => 4, 'maxItems' => 8,
                    'items' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'required' => ['term', 'weight', 'synonyms'],
                        'properties' => [
                            'term' => ['type' => 'string'],
                            'weight' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                            'synonyms' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
                'common_errors' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'required' => ['erroneous_answer', 'mark_ceiling'],
                        'properties' => [
                            'misconception_ref' => ['type' => ['string', 'null']],
                            'erroneous_answer' => ['type' => 'string'],
                            'mark_ceiling' => ['type' => 'integer', 'minimum' => 0],
                        ],
                    ],
                ],
                'full_credit_threshold' => ['type' => 'integer', 'minimum' => 1],
                'estimated_time_seconds' => ['type' => 'integer', 'minimum' => 30, 'maximum' => 900],
            ],
            ['sub_type', 'model_answer', 'marking_points', 'keywords', 'common_errors', 'full_credit_threshold'],
            ['type' => 'integer', 'minimum' => $minMarks, 'maximum' => $maxMarks],
            15,
            1200
        );
    }

    public function validateRow(array $row): ?string
    {
        $ans = $this->answer($row);
        $title = $this->text($row['question_title'] ?? '');

        if (mb_strlen($title) < 15) {
            return 'the question is missing or too short';
        }

        $model = $this->text($ans['model_answer'] ?? '');
        if ($model === '') {
            return 'model_answer is required';
        }

        [$minWords, $maxWords] = $this->modelAnswerWords();
        $words = $this->words($model);
        // Lenient by a margin: the prompt states the target range, the validator only
        // refuses an answer that is plainly the wrong KIND (a paragraph for a
        // very-short item, a single word for a long one).
        if ($words < max(1, (int) floor($minWords / 2)) || $words > (int) ceil($maxWords * 1.5)) {
            return "model_answer is {$words} words; a {$this->label()} answer runs {$minWords}-{$maxWords}";
        }

        $points = (int) $row['points'];
        $marking = $ans['marking_points'] ?? null;
        if (!is_array($marking) || count($marking) !== $points) {
            return 'marking_points count must equal points';
        }
        foreach ($marking as $point) {
            if (!is_array($point) || $this->text($point['criterion'] ?? '') === '') {
                return 'a marking point has no criterion';
            }
        }

        $keywords = $ans['keywords'] ?? null;
        if (!is_array($keywords)) {
            return 'need >= 4 keywords';
        }
        // A keyword that appears in the question is disqualified (it rewards copying
        // it), so only the others count -- prepareRow() drops the offenders.
        if (count($this->eligibleKeywords($keywords, $title)) < 4) {
            return 'need >= 4 keywords that do not appear in the question';
        }

        if (empty($ans['knowledge_refs']) || !is_array($ans['knowledge_refs'])) {
            return 'knowledge_refs required';
        }

        return null;
    }

    public function prepareRow(array $row): array
    {
        $ans = $this->answer($row);

        $ans['sub_type'] = $this->label();
        $ans['model_answer'] = $this->text($ans['model_answer']);

        $ans['keywords'] = array_slice(
            $this->eligibleKeywords($ans['keywords'], $this->text($row['question_title'] ?? '')),
            0,
            8
        );

        if (empty($ans['full_credit_threshold']) || !is_int($ans['full_credit_threshold'])) {
            $ans['full_credit_threshold'] = (int) ceil(0.75 * (int) $row['points']);
        }

        $row['answer'] = $ans;

        return $row;
    }

    /**
     * The keywords that may score: a non-empty term that is not already in the question.
     *
     * @param  array<int, mixed>  $keywords
     * @return list<array<string, mixed>>
     */
    protected function eligibleKeywords(array $keywords, string $title): array
    {
        $title = mb_strtolower($title);
        $eligible = [];

        foreach ($keywords as $keyword) {
            if (!is_array($keyword)) {
                continue;
            }
            $term = mb_strtolower($this->text($keyword['term'] ?? ''));
            if ($term === '' || (mb_strlen($term) >= 4 && str_contains($title, $term))) {
                continue;
            }
            $eligible[] = $keyword;
        }

        return $eligible;
    }
}
