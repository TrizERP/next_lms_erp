<?php

namespace App\Services\QuestionGeneration\Formats;

use App\Services\QuestionGeneration\AbstractQuestionFormat;
use App\Services\QuestionGeneration\FormatSchema;

/**
 * Case study -- a short real-world stimulus and two or three lettered sub-parts.
 *
 * A SINGLE ROW, not a parent with children. 994 extracted case studies are stored
 * exactly this way (code `case_study`, sub-parts inline in the stem), whereas the
 * parent/child pair (`case_study_parent` / `case_study_child`) is held together
 * only by id adjacency and a parent_question_id that lives in the extraction-owned
 * sidecar. Writing one row needs no link, no migration and cannot be split by a
 * concurrent insert.
 *
 * STORAGE (catalogue: lms_question_type_id 2): no `answer_master` rows.
 *   - `question_title`  the stimulus followed by "(a) ... [m marks]" lines, built here
 *     from the model's parts so a stem-only consumer has the whole case
 *   - `answer.stimulus`, `answer.sub_parts`, `answer.sub_part_labels`  the parts, kept
 *     apart (sub_part_labels is exposed by the bank API)
 *   - `answer.model_answer`  "a) ...\nb) ..." -- one answer per part, in the style of
 *     the extracted rows; the course-presentation player puts it in the slide notes
 *
 * MARKS: 3 to 8. The catalogue's `case_study` default_marks is NULL (the standard
 * `case_study_parent` row says 4), so 4 is the registry's own default.
 *
 * PLAYS AS: H5P Course presentation (default).
 */
class CaseStudyFormat extends AbstractQuestionFormat
{
    protected const BATCH_SIZE = 2;
    protected const TEMPERATURE = 0.6;

    public const MIN_STIMULUS_WORDS = 50;
    public const MAX_STIMULUS_WORDS = 110;

    public function code(): string
    {
        return 'case_study';
    }

    public function label(): string
    {
        return 'Case Study';
    }

    public function fallbackQuestionTypeId(): int
    {
        return 2;
    }

    public function defaultMarks(): int
    {
        return 4;
    }

    public function marksRange(): array
    {
        return [3, 8];
    }

    public function allowedBloomLevels(): array
    {
        return ['Apply', 'Analyze', 'Evaluate', 'Create'];
    }

    public function taskLabel(): string
    {
        return 'case-study';
    }

    public function subTypeFor(string $level): string
    {
        return 'Case Study';
    }

    public function constructionRules(int $total): string
    {
        $common = $this->commonRules('"Tests <ability_ref> at <bloom_level>, <points> marks; common error: <misconception>."');
        $min = self::MIN_STIMULUS_WORDS;
        $max = self::MAX_STIMULUS_WORDS;

        return <<<RULES
Each item is ONE case: a stimulus and 2 or 3 lettered sub-parts. It is stored as a single row.

question_title -- a short heading for the case (<= 12 words). The full stem is built for you from
  the stimulus and the sub-parts.

answer.stimulus -- {$min} to {$max} words of real-world context, assembled ONLY from `evidence` or
  `real_world_applications`. Every sub-part must be unanswerable without reading it.

answer.sub_parts -- JSON array of 2 or 3 objects:
    label         "a", "b", "c" in order
    text          the sub-part question, opening with a command verb that suits the item's Bloom
                  level and ladder; one task only
    marks         whole marks for this part (at least 1)
    model_answer  a full-credit answer to this part, in student voice, slice content only
- The sub-part marks SUM to `points`. Later parts may build on earlier ones but each must be
  answerable from the stimulus and the slice alone.

answer.marking_points -- one entry per mark across the whole case; the count EQUALS `points`.
    mark           always 1
    criterion      what the student must have written to earn it
    accept         2-4 acceptable alternative phrasings
    reject         1-2 near-answers that must NOT earn the mark
    knowledge_ref  the knowledge item this mark tests

answer.sub_type -- always "Case Study".
points -- 3 to 8, as the QUOTA TABLE gives for the item's level. The default is 4.

{$common}

EXAMPLE ROW (structure only; do not reuse its content)
{
  "question_title": "Diver's descent",
  "description": "Tests multiplying integers with unlike signs at Apply, 4 marks; common error: sign dropped.",
  "subconcept": "<exact knowledge string>",
  "points": 4, "multiple_answer": 0, "hint_text": "Decide first whether a descent is a positive or negative change.",
  "learning_outcome": ["<exact outcome>"],
  "answer": {
    "v": "ans-2.0", "question_type": "case_study", "sub_type": "Case Study",
    "bloom_level": "Apply", "dok_level": 2, "difficulty": "Medium",
    "stimulus": "A training pool records the position of a diver relative to the surface. The surface is 0 m and any point below it is a negative number. During a drill, the diver starts at the surface and descends at a steady 3 m every minute for 4 minutes. The coach notes the position after each minute in a table so that trainees can compare it with the instructor's plan and discuss how signed numbers describe movement in opposite directions along a line.",
    "estimated_time_seconds": 300,
    "sub_parts": [
      {"label": "a", "text": "Write the diver's change in position each minute as a signed integer.", "marks": 1, "model_answer": "-3 m, because the diver moves below the surface."},
      {"label": "b", "text": "Calculate the diver's position after 4 minutes.", "marks": 2, "model_answer": "4 x (-3) = -12, so the diver is at -12 m."},
      {"label": "c", "text": "Explain why the product in part (b) is negative.", "marks": 1, "model_answer": "The factors have unlike signs, so the product is negative."}
    ],
    "marking_points": [
      {"mark": 1, "criterion": "Gives -3 m per minute", "accept": ["-3", "negative 3"], "reject": ["+3"], "knowledge_ref": "<exact knowledge>"},
      {"mark": 1, "criterion": "Multiplies 4 by -3", "accept": ["4 x -3"], "reject": ["4 + 3"], "knowledge_ref": "<exact knowledge>"},
      {"mark": 1, "criterion": "States the position is -12 m", "accept": ["-12 m"], "reject": ["12 m"], "knowledge_ref": "<exact knowledge>"},
      {"mark": 1, "criterion": "Links the negative sign to unlike signs", "accept": ["unlike signs"], "reject": ["it is a descent only"], "knowledge_ref": "<exact knowledge>"}
    ],
    "explanation": "Each minute changes position by -3 m, so four minutes give 4 x (-3) = -12 m, negative because the signs differ.",
    "remediation": "Reteach the sign rule using movement above and below a reference level.",
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
                'sub_type' => ['const' => 'Case Study'],
                'stimulus' => ['type' => 'string', 'minLength' => 200, 'maxLength' => 900],
                'sub_parts' => [
                    'type' => 'array', 'minItems' => 2, 'maxItems' => 3,
                    'items' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'required' => ['label', 'text', 'marks', 'model_answer'],
                        'properties' => [
                            'label' => ['enum' => ['a', 'b', 'c']],
                            'text' => ['type' => 'string', 'minLength' => 10],
                            'marks' => ['type' => 'integer', 'minimum' => 1],
                            'model_answer' => ['type' => 'string', 'minLength' => 2],
                        ],
                    ],
                ],
                'marking_points' => [
                    'type' => 'array', 'minItems' => 3, 'maxItems' => 8,
                    'items' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'required' => ['mark', 'criterion', 'accept', 'reject', 'knowledge_ref'],
                        'properties' => [
                            'mark' => ['const' => 1],
                            'criterion' => ['type' => 'string', 'minLength' => 5],
                            'accept' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 4, 'items' => ['type' => 'string']],
                            'reject' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 2, 'items' => ['type' => 'string']],
                            'knowledge_ref' => ['type' => 'string'],
                        ],
                    ],
                ],
                'estimated_time_seconds' => ['type' => 'integer', 'minimum' => 120, 'maximum' => 900],
            ],
            ['sub_type', 'stimulus', 'sub_parts', 'marking_points'],
            ['type' => 'integer', 'minimum' => 3, 'maximum' => 8],
            3,
            200,
            // The common 'stimulus' allows null; a case study cannot exist without one.
            ['stimulus' => ['type' => 'string', 'minLength' => 200, 'maxLength' => 900]]
        );
    }

    public function validateRow(array $row): ?string
    {
        $ans = $this->answer($row);

        if ($this->text($row['question_title'] ?? '') === '') {
            return 'the case heading is missing';
        }

        $stimulus = $this->text($ans['stimulus'] ?? '');
        $words = $this->words($stimulus);
        if ($words < self::MIN_STIMULUS_WORDS || $words > self::MAX_STIMULUS_WORDS) {
            return "stimulus is {$words} words; it must be " . self::MIN_STIMULUS_WORDS . '-' . self::MAX_STIMULUS_WORDS;
        }

        $parts = $ans['sub_parts'] ?? null;
        if (!is_array($parts) || count($parts) < 2 || count($parts) > 3) {
            return 'sub_parts must hold 2 or 3 parts';
        }

        $expected = ['a', 'b', 'c'];
        $sum = 0;
        foreach (array_values($parts) as $i => $part) {
            if (!is_array($part) || ($part['label'] ?? null) !== $expected[$i]) {
                return 'sub-part labels must run a, b, c in order';
            }
            if (mb_strlen($this->text($part['text'] ?? '')) < 10) {
                return "sub-part {$expected[$i]} has no question";
            }
            if ($this->text($part['model_answer'] ?? '') === '') {
                return "sub-part {$expected[$i]} has no model answer";
            }
            $marks = $part['marks'] ?? null;
            if (!is_int($marks) || $marks < 1) {
                return "sub-part {$expected[$i]} needs whole marks of at least 1";
            }
            $sum += $marks;
        }

        $points = (int) $row['points'];
        if ($sum !== $points) {
            return "sub-part marks sum to {$sum} but points is {$points}";
        }

        $marking = $ans['marking_points'] ?? null;
        if (!is_array($marking) || count($marking) !== $points) {
            return 'marking_points count must equal points';
        }
        foreach ($marking as $point) {
            if (!is_array($point) || $this->text($point['criterion'] ?? '') === '') {
                return 'a marking point has no criterion';
            }
        }

        if (empty($ans['knowledge_refs']) || !is_array($ans['knowledge_refs'])) {
            return 'knowledge_refs required';
        }

        return null;
    }

    public function prepareRow(array $row): array
    {
        $ans = $this->answer($row);
        $stimulus = $this->text($ans['stimulus']);

        $parts = [];
        $lines = [];
        $labels = [];
        $answers = [];
        foreach (array_values($ans['sub_parts']) as $part) {
            $label = (string) $part['label'];
            $text = $this->text($part['text']);
            $marks = (int) $part['marks'];
            $modelAnswer = $this->text($part['model_answer']);

            $parts[] = ['label' => $label, 'text' => $text, 'marks' => $marks, 'model_answer' => $modelAnswer];
            $labels[] = $label;
            $lines[] = "({$label}) {$text} [{$marks} " . ($marks === 1 ? 'mark' : 'marks') . ']';
            $answers[] = "{$label}) {$modelAnswer}";
        }

        $ans['stimulus'] = $stimulus;
        $ans['sub_parts'] = $parts;
        $ans['sub_part_labels'] = $labels;
        $ans['model_answer'] = implode("\n", $answers);
        $ans['sub_type'] = 'Case Study';

        $row['answer'] = $ans;
        $row['question_title'] = $stimulus . "\n\n" . implode("\n", $lines);

        return $row;
    }
}
