<?php

namespace App\Services\QuestionGeneration\Formats;

/**
 * Plot / draw / construct -- a task whose answer is a method, 2-4 marks.
 *
 * Catalogue: lms_question_type_id 2, default_marks 3. The model cannot draw, so what is
 * stored is the written brief and a reference answer that states the steps and the result
 * of the construction; each marking point is one step or one feature of the finished
 * figure. Plays as the H5P map already says for `construction`: Course presentation (the
 * brief on the slide, the reference answer in the notes), shown as the closest built type.
 */
class ConstructionFormat extends AbstractNarrativeFormat
{
    protected const BATCH_SIZE = 3;

    public function code(): string
    {
        return 'construction';
    }

    public function label(): string
    {
        return 'Construction';
    }

    public function defaultMarks(): int
    {
        return 3;
    }

    public function marksRange(): array
    {
        return [2, 4];
    }

    public function allowedBloomLevels(): array
    {
        return ['Apply', 'Analyze', 'Create'];
    }

    protected function modelAnswerWords(): array
    {
        return [30, 150];
    }

    protected function lengthGuidance(): string
    {
        return 'A construction item asks the student to plot, draw or construct something, given every measurement in the question. Because the answer is a drawing, the model answer is the ordered steps AND a description of the finished figure; each marking point is one step or one checkable feature of the result. Worth 2 to 4 marks.';
    }

    protected function commandVerbHint(): string
    {
        return 'Use "Plot", "Draw", "Construct" or "Represent". Apply: Plot / Represent. Analyze: Construct and justify. Create: Construct a figure that satisfies the given conditions.';
    }

    protected function exampleRow(): string
    {
        return <<<'EX'
{
  "question_title": "Represent the product 3 x (-2) on a number line, showing each move clearly.",
  "description": "Tests multiplication as repeated moves at Apply, 3 marks; common error: moving the wrong way.",
  "subconcept": "<exact knowledge string>",
  "points": 3, "multiple_answer": 0, "hint_text": "Decide which side of the origin a move of -2 takes you to.",
  "learning_outcome": ["<exact outcome>"],
  "answer": {
    "v": "ans-2.0", "question_type": "construction", "sub_type": "Construction",
    "bloom_level": "Apply", "dok_level": 2, "difficulty": "Medium", "stimulus": null,
    "estimated_time_seconds": 180,
    "model_answer": "Draw a horizontal line and mark the origin at 0 with a uniform scale of 1 unit per division. Starting at 0, make three moves of 2 units each towards the left, because each move is -2. Mark the points -2, -4 and -6 after the moves. The final point is -6, so 3 x (-2) = -6.",
    "marking_points": [
      {"mark": 1, "criterion": "Draws the line with the origin and a uniform scale", "accept": ["equal divisions", "labelled origin"], "reject": ["unequal spacing"], "knowledge_ref": "<exact knowledge>"},
      {"mark": 1, "criterion": "Makes three moves of 2 units to the left", "accept": ["three jumps of -2"], "reject": ["moves to the right"], "knowledge_ref": "<exact knowledge>"},
      {"mark": 1, "criterion": "Marks the final point -6 and states the product", "accept": ["-6 labelled", "3 x (-2) = -6"], "reject": ["+6"], "knowledge_ref": "<exact knowledge>"}
    ],
    "keywords": [{"term": "origin", "weight": 0.3, "synonyms": ["zero point"]}, {"term": "scale", "weight": 0.2, "synonyms": ["unit"]}, {"term": "left", "weight": 0.3, "synonyms": ["negative direction"]}, {"term": "final point", "weight": 0.2, "synonyms": ["endpoint"]}],
    "common_errors": [{"misconception_ref": "<exact misconception>", "erroneous_answer": "Moves to the right and ends at +6.", "mark_ceiling": 1}],
    "full_credit_threshold": 2,
    "explanation": "Repeated moves of -2 take the point left, so three of them end at -6.",
    "remediation": "Reteach multiplication as repeated movement in a signed direction.",
    "knowledge_refs": ["<exact knowledge>"], "ability_ref": "<exact ability>", "misconception_refs": ["<exact misconception>"]
  }
}
EX;
    }
}
